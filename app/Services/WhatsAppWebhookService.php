<?php

namespace App\Services;

use App\Models\BusinessAgentSetting;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\WhatsAppOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsAppWebhookService
{
    public function __construct(
        protected EvolutionApiService $evolution,
        protected SalesAgentService $salesAgent
    ) {}

    /**
     * Handles incoming Evolution API webhook payloads (messages.upsert).
     *
     * @param  array<string, mixed>  $payload
     * @return array{processed: bool, reason?: string, message_id?: int}
     */
    public function handle(array $payload): array
    {
        $event = strtolower((string) ($payload['event'] ?? ''));
        if ($event !== '' && ! in_array($event, ['messages.upsert', 'messages_upsert'], true)) {
            return ['processed' => false, 'reason' => "Ignored event: {$event}"];
        }

        $data = $payload['data'] ?? $payload;
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        $key = $data['key'] ?? [];
        $remoteJid = (string) ($key['remoteJid'] ?? '');
        $remoteJidAlt = (string) ($key['remoteJidAlt'] ?? '');

        if ($remoteJid === '' || str_ends_with($remoteJid, '@g.us') || $remoteJid === 'status@broadcast') {
            return ['processed' => false, 'reason' => 'Ignored empty, group, or broadcast JID'];
        }

        // Prefer real phone JID when WhatsApp sends @lid
        $effectiveJid = str_ends_with($remoteJid, '@lid') && $remoteJidAlt !== '' ? $remoteJidAlt : $remoteJid;

        $fromMe = (bool) ($key['fromMe'] ?? false);
        $externalId = $key['id'] ?? null;
        $pushName = trim((string) ($data['pushName'] ?? ''));
        $msgContent = $data['message'] ?? [];

        [$body, $messageType, $mediaUrl] = $this->extractBodyTypeAndMedia($msgContent);
        if ($body === '' && $messageType === 'text') {
            return ['processed' => false, 'reason' => 'Empty message payload'];
        }

        if ($externalId && Message::query()->where('external_id', $externalId)->exists()) {
            return ['processed' => false, 'reason' => 'Duplicate external_id'];
        }

        $phone = Customer::normalizePhone(Str::before($effectiveJid, '@'));
        if ($phone === '') {
            return ['processed' => false, 'reason' => 'Invalid phone'];
        }

        $sentAt = isset($data['messageTimestamp']) && is_numeric($data['messageTimestamp'])
            ? Carbon::createFromTimestamp((int) $data['messageTimestamp'])
            : now();

        $defaultBusinessSetting = BusinessAgentSetting::activeDefault();

        [$conversation, $savedMessage] = DB::transaction(function () use (
            $phone,
            $effectiveJid,
            $pushName,
            $fromMe,
            $body,
            $messageType,
            $mediaUrl,
            $externalId,
            $sentAt,
            $defaultBusinessSetting
        ) {
            $customer = Customer::query()->firstOrCreate(
                ['phone' => $phone],
                ['name' => $pushName !== '' && ! $fromMe ? $pushName : null]
            );

            if (! $fromMe && $pushName !== '' && empty($customer->name)) {
                $customer->update(['name' => $pushName]);
            }

            $conversation = Conversation::query()->firstOrCreate(
                ['remote_jid' => $effectiveJid],
                [
                    'customer_id' => $customer->id,
                    'business_agent_setting_id' => $defaultBusinessSetting?->id,
                    'status' => 'bot',
                    'agent_state' => WhatsAppOrder::STATE_GREETING,
                    'unread_count' => 0,
                ]
            );

            $preview = $body !== '' ? Str::limit($body, 120) : "[{$messageType}]";

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => $fromMe ? 'outbound' : 'inbound',
                'message_type' => $messageType,
                'body' => $body !== '' ? $body : "[{$messageType}]",
                'media_url' => $mediaUrl,
                'external_id' => $externalId,
                'status' => $fromMe ? 'sent' : 'received',
                'sent_at' => $sentAt,
            ]);

            $conversation->update([
                'last_message_preview' => $preview,
                'last_message_at' => $sentAt,
                'unread_count' => $fromMe ? 0 : ($conversation->unread_count + 1),
            ]);

            return [$conversation, $message];
        });

        if (! $fromMe && $conversation->status === 'bot') {
            $imageBase64 = null;
            $imageMime = null;

            if ($messageType === 'image') {
                $embeddedBase64 = $msgContent['base64'] ?? $data['base64'] ?? null;
                if (is_string($embeddedBase64) && $embeddedBase64 !== '') {
                    $imageBase64 = $embeddedBase64;
                    $imageMime = (string) ($msgContent['imageMessage']['mimetype'] ?? 'image/jpeg');
                } else {
                    $fetched = $this->evolution->fetchMediaBase64($data);
                    if ($fetched) {
                        $imageBase64 = $fetched['base64'];
                        $imageMime = $fetched['mimetype'];
                    }
                }
            }

            $this->salesAgent->processInboundMessage(
                $conversation->fresh(['businessAgentSetting', 'customer', 'activeOrder']),
                $savedMessage,
                $imageBase64,
                $imageMime
            );
        }

        return ['processed' => true, 'message_id' => $savedMessage->id];
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array{0: string, 1: string, 2: string|null}
     */
    protected function extractBodyTypeAndMedia(array $msg): array
    {
        if (isset($msg['conversation']) && is_string($msg['conversation'])) {
            return [trim($msg['conversation']), 'text', null];
        }
        if (isset($msg['extendedTextMessage']['text'])) {
            return [trim((string) $msg['extendedTextMessage']['text']), 'text', null];
        }
        if (isset($msg['imageMessage'])) {
            $caption = trim((string) ($msg['imageMessage']['caption'] ?? 'Imagen recibida (comprobante/foto)'));
            $url = $msg['imageMessage']['url'] ?? null;

            return [$caption !== '' ? $caption : 'Imagen recibida', 'image', is_string($url) ? $url : null];
        }
        if (isset($msg['audioMessage'])) {
            return ['Audio recibido', 'audio', null];
        }
        if (isset($msg['documentMessage'])) {
            $caption = trim((string) ($msg['documentMessage']['caption'] ?? 'Documento recibido'));
            $url = $msg['documentMessage']['url'] ?? null;

            return [$caption !== '' ? $caption : 'Documento recibido', 'document', is_string($url) ? $url : null];
        }

        return ['', 'text', null];
    }
}
