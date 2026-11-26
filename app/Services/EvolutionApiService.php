<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Support\CurrencyHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EvolutionApiService
{
    public function baseUrl(): string
    {
        $url = trim((string) Setting::getValue('evolution_api_url', ''));
        if ($url === '') {
            $url = trim((string) env('EVOLUTION_API_URL', ''));
        }

        return rtrim($url, '/');
    }

    public function apiKey(): string
    {
        $key = trim((string) Setting::getValue('evolution_api_key', ''));
        if ($key === '') {
            $key = trim((string) env('EVOLUTION_API_KEY', ''));
        }

        return $key;
    }

    public function instanceName(): string
    {
        $instance = trim((string) Setting::getValue('evolution_instance', ''));
        if ($instance === '') {
            $instance = trim((string) env('EVOLUTION_INSTANCE_NAME', 'nexora'));
        }

        return $instance ?: 'nexora';
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== '';
    }

    /**
     * @return array{ok: bool, state: string, raw?: mixed, error?: string}
     */
    public function getConnectionState(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'state' => 'unconfigured', 'error' => 'Configura URL y API Key de Evolution API en Ajustes.'];
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['apikey' => $this->apiKey()])
                ->get($this->baseUrl().'/instance/connectionState/'.urlencode($this->instanceName()));

            if ($response->successful()) {
                $state = $response->json('instance.state') ?? $response->json('state') ?? 'unknown';

                return ['ok' => true, 'state' => (string) $state, 'raw' => $response->json()];
            }

            return ['ok' => false, 'state' => 'error', 'error' => 'HTTP '.$response->status().': '.$response->body()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'state' => 'offline', 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, external_id?: string|null, error?: string}
     */
    public function sendText(string $phoneOrJid, string $text): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'Evolution API no configurado.'];
        }

        $number = str_contains($phoneOrJid, '@')
            ? Str::before($phoneOrJid, '@')
            : preg_replace('/\D+/', '', $phoneOrJid);

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'apikey' => $this->apiKey(),
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl().'/message/sendText/'.urlencode($this->instanceName()), [
                    'number' => $number,
                    'text' => $text,
                ]);

            if ($response->successful()) {
                $externalId = $response->json('key.id') ?? $response->json('message.key.id');

                return ['ok' => true, 'external_id' => $externalId];
            }

            Log::warning('[NEXORA][WhatsApp] Error enviando mensaje Evolution API', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['ok' => false, 'error' => 'HTTP '.$response->status()];
        } catch (\Throwable $e) {
            Log::warning('[NEXORA][WhatsApp] Excepción enviando mensaje: '.$e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendAndRecordMessage(Conversation $conversation, string $text, ?int $userId = null): Message
    {
        $conversation->loadMissing('customer');
        $target = $conversation->remote_jid ?: ($conversation->customer?->phone ?? '');

        $result = $this->sendText($target, $text);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => $text,
            'external_id' => $result['external_id'] ?? null,
            'status' => $result['ok'] ? 'sent' : ($this->isConfigured() ? 'failed' : 'local_only'),
            'sent_by_user_id' => $userId,
            'sent_at' => now(),
        ]);

        $conversation->update([
            'last_message_preview' => Str::limit($text, 120),
            'last_message_at' => now(),
            'unread_count' => 0,
        ]);

        return $message;
    }

    /**
     * Fetches media base64 from Evolution API v2 when webhook does not embed it directly.
     *
     * @param  array<string, mixed>  $rawData
     * @return array{base64: string, mimetype: string}|null
     */
    public function fetchMediaBase64(array $rawData): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $key = $rawData['key'] ?? null;
        if (! is_array($key) || empty($key['id'])) {
            return null;
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'apikey' => $this->apiKey(),
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl().'/chat/getBase64FromMediaMessage/'.urlencode($this->instanceName()), [
                    'message' => ['key' => $key],
                    'convertToMp4' => false,
                ]);

            if ($response->successful() && $response->json('base64')) {
                return [
                    'base64' => (string) $response->json('base64'),
                    'mimetype' => (string) ($response->json('mimetype') ?? 'image/jpeg'),
                ];
            }
        } catch (\Throwable $e) {
            Log::debug('[NEXORA][WhatsApp] No se pudo descargar media base64: '.$e->getMessage());
        }

        return null;
    }

    public function buildLiveMenuMessage(): string
    {
        $rate = CurrencyHelper::activeRate();
        $lines = [
            '*NEXORA Pastelería* — _Aquí empieza el futuro de tu negocio_',
            'Tasa BCV hoy: '.number_format($rate, 2, ',', '.').' Bs/$',
            '',
        ];

        $combos = Combo::query()->where('is_active', true)->with('slots')->orderBy('name')->get();
        if ($combos->isNotEmpty()) {
            $lines[] = '*COMBOS PROMOCIONALES (A tu gusto):*';
            foreach ($combos as $combo) {
                $usd = number_format((float) $combo->promo_price_usd, 2);
                $bs = number_format(CurrencyHelper::toBs((float) $combo->promo_price_usd, $rate), 2, ',', '.');
                $lines[] = "• *{$combo->name}* — \${$usd} (Bs. {$bs})";
            }
            $lines[] = '';
        }

        $pasteles = Product::query()
            ->where('type', 'manufactured')
            ->where('is_active', true)
            ->where('is_sellable', true)
            ->where('stock_qty', '>', 0)
            ->orderBy('name')
            ->get();

        if ($pasteles->isNotEmpty()) {
            $lines[] = '*PASTELES DISPONIBLES:*';
            foreach ($pasteles as $p) {
                $usd = number_format((float) $p->sale_price_usd, 2);
                $bs = number_format(CurrencyHelper::toBs((float) $p->sale_price_usd, $rate), 2, ',', '.');
                $lines[] = "• {$p->name} — \${$usd} (Bs. {$bs})";
            }
            $lines[] = '';
        }

        $bebidas = Product::query()
            ->where('type', 'resale')
            ->where('is_active', true)
            ->where('is_sellable', true)
            ->where('stock_qty', '>', 0)
            ->orderBy('name')
            ->get();

        if ($bebidas->isNotEmpty()) {
            $lines[] = '*BEBIDAS Y SNACKS:*';
            foreach ($bebidas as $b) {
                $usd = number_format((float) $b->sale_price_usd, 2);
                $bs = number_format(CurrencyHelper::toBs((float) $b->sale_price_usd, $rate), 2, ',', '.');
                $lines[] = "• {$b->name} — \${$usd} (Bs. {$bs})";
            }
            $lines[] = '';
        }

        $lines[] = 'Responde con tu pedido y con gusto te atendemos.';

        return implode("\n", $lines);
    }

    public function buildSaleReceiptMessage(Sale $sale): string
    {
        $sale->loadMissing('items.comboSelections');
        $lines = [
            '*NEXORA — Comprobante de Pedido*',
            "Ticket: *{$sale->invoice_number}*",
            'Fecha: '.$sale->sold_at->format('d/m/Y H:i'),
            '',
            '*Detalle:*',
        ];

        foreach ($sale->items as $item) {
            $price = number_format((float) $item->total_price_usd, 2);
            $lines[] = "• {$item->quantity}x {$item->description} (\${$price})";
            foreach ($item->comboSelections as $sel) {
                $lines[] = "   ↳ {$sel->quantity}x {$sel->product_name}";
            }
        }

        $lines[] = '';
        $lines[] = '*Total:* $'.number_format((float) $sale->total_usd, 2).' / Bs. '.number_format((float) $sale->total_bs, 2, ',', '.');
        $lines[] = '¡Gracias por preferirnos!';

        return implode("\n", $lines);
    }
}
