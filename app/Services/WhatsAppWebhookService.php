<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsAppWebhookService
{
    public function __construct(
        protected EvolutionApiService $evolution
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

        if ($remoteJid === '' || str_ends_with($remoteJid, '@g.us') || $remoteJid === 'status@broadcast') {
            return ['processed' => false, 'reason' => 'Ignored empty, group, or broadcast JID'];
        }

        $fromMe = (bool) ($key['fromMe'] ?? false);
        $externalId = $key['id'] ?? null;
        $pushName = trim((string) ($data['pushName'] ?? ''));
        $msgContent = $data['message'] ?? [];

        [$body, $messageType] = $this->extractBodyAndType($msgContent);
        if ($body === '' && $messageType === 'text') {
            return ['processed' => false, 'reason' => 'Empty message payload'];
        }

        if ($externalId && Message::query()->where('external_id', $externalId)->exists()) {
            return ['processed' => false, 'reason' => 'Duplicate external_id'];
        }

        $phone = Customer::normalizePhone(Str::before($remoteJid, '@'));
        if ($phone === '') {
            return ['processed' => false, 'reason' => 'Invalid phone'];
        }

        $sentAt = isset($data['messageTimestamp']) && is_numeric($data['messageTimestamp'])
            ? Carbon::createFromTimestamp((int) $data['messageTimestamp'])
            : now();

        [$conversation, $savedMessage] = DB::transaction(function () use (
            $phone,
            $remoteJid,
            $pushName,
            $fromMe,
            $body,
            $messageType,
            $externalId,
            $sentAt
        ) {
            $customer = Customer::query()->firstOrCreate(
                ['phone' => $phone],
                ['name' => $pushName !== '' && ! $fromMe ? $pushName : null]
            );

            if (! $fromMe && $pushName !== '' && empty($customer->name)) {
                $customer->update(['name' => $pushName]);
            }

            $conversation = Conversation::query()->firstOrCreate(
                ['remote_jid' => $remoteJid],
                [
                    'customer_id' => $customer->id,
                    'status' => 'bot',
                    'unread_count' => 0,
                ]
            );

            $preview = $body !== '' ? Str::limit($body, 120) : "[{$messageType}]";

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => $fromMe ? 'outbound' : 'inbound',
                'message_type' => $messageType,
                'body' => $body !== '' ? $body : "[{$messageType}]",
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

        if (! $fromMe && $conversation->status === 'bot' && Setting::getBool('whatsapp_auto_reply_menu', true)) {
            if ($this->shouldAutoReplyMenu($body)) {
                $this->evolution->sendAndRecordMessage($conversation, $this->evolution->buildLiveMenuMessage());
            }
        }

        return ['processed' => true, 'message_id' => $savedMessage->id];
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array{0: string, 1: string}
     */
    protected function extractBodyAndType(array $msg): array
    {
        if (isset($msg['conversation']) && is_string($msg['conversation'])) {
            return [trim($msg['conversation']), 'text'];
        }
        if (isset($msg['extendedTextMessage']['text'])) {
            return [trim((string) $msg['extendedTextMessage']['text']), 'text'];
        }
        if (isset($msg['imageMessage'])) {
            return [trim((string) ($msg['imageMessage']['caption'] ?? 'Imagen recibida')), 'image'];
        }
        if (isset($msg['audioMessage'])) {
            return ['Audio recibido', 'audio'];
        }
        if (isset($msg['documentMessage'])) {
            return [trim((string) ($msg['documentMessage']['caption'] ?? 'Documento recibido')), 'document'];
        }

        return ['', 'text'];
    }

    protected function shouldAutoReplyMenu(string $body): bool
    {
        $normalized = Str::lower(Str::ascii($body));
        foreach (['hola', 'buenas', 'buenos', 'menu', 'precio', 'precios', 'combo', 'combos', 'pastel', 'pasteles', 'catalogo'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
