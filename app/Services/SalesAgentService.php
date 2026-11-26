<?php

namespace App\Services;

use App\Models\BusinessAgentSetting;
use App\Models\Combo;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\WhatsAppOrder;
use App\Support\CurrencyHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SalesAgentService
{
    public function __construct(
        protected EvolutionApiService $evolution
    ) {}

    public function buildDynamicSystemPrompt(
        BusinessAgentSetting $settings,
        Conversation $conversation,
        ?WhatsAppOrder $order = null
    ): string {
        $rate = CurrencyHelper::activeRate();
        $requiredFields = $settings->delivery_required_fields ?: BusinessAgentSetting::defaultDeliveryFields();

        $catalogLines = [];
        $products = Product::query()
            ->where('is_active', true)
            ->where('is_sellable', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        foreach ($products as $p) {
            $usd = number_format((float) $p->sale_price_usd, 2);
            $bs = number_format(CurrencyHelper::toBs((float) $p->sale_price_usd, $rate), 2, ',', '.');
            $catalogLines[] = "- [product_id:{$p->id}] {$p->name} | Stock: ".(int) $p->stock_qty." | Precio: \${$usd} (Bs. {$bs})";
        }

        $combos = Combo::query()->where('is_active', true)->with('slots.options.product')->orderBy('name')->get();
        foreach ($combos as $c) {
            $usd = number_format((float) $c->promo_price_usd, 2);
            $bs = number_format(CurrencyHelper::toBs((float) $c->promo_price_usd, $rate), 2, ',', '.');
            $slotInfo = $c->slots->map(function ($s) {
                $opts = $s->options->map(fn ($o) => "[product_id:{$o->product_id}] {$o->product?->name}")->implode(', ');

                return "{$s->label} (elegir {$s->required_qty}: {$opts})";
            })->implode('; ');
            $catalogLines[] = "- [combo_id:{$c->id}] COMBO {$c->name} | Precio: \${$usd} (Bs. {$bs}) | Opciones: {$slotInfo}";
        }

        $methods = PaymentMethod::active()->get()
            ->map(fn ($m) => "- {$m->name} ({$m->currency})".($m->requires_reference ? ' [Requiere referencia]' : ''))
            ->implode("\n");

        $currentState = $conversation->agent_state ?: WhatsAppOrder::STATE_GREETING;
        $orderSnapshot = $order ? json_encode([
            'order_code' => $order->order_code,
            'status' => $order->status,
            'items' => $order->items_json ?? [],
            'total_usd' => (float) $order->total_usd,
            'total_bs' => (float) $order->total_bs,
            'delivery_data' => $order->delivery_data ?? [],
            'payment_reference' => $order->payment_reference,
        ], JSON_UNESCAPED_UNICODE) : 'Ninguno aún';

        $fieldsList = collect($requiredFields)->map(fn ($f) => "- {$f}")->implode("\n");

        return <<<PROMPT
Eres el Agente Virtual de Cierre de Ventas y Despacho de "{$settings->business_name}".
Responde siempre en español con tono amable, claro y profesional adaptado a Venezuela.

=== SALUDO DE GRATITUD OBLIGATORIO ===
Directriz inicial: "{$settings->gratitude_greeting}"

=== CONTEXTO EXTENSO Y REGLAS DEL NEGOCIO ===
{$settings->extensive_business_context}

=== INVENTARIO Y PRECIOS EN TIEMPO REAL (TASA BCV: {$rate} Bs/USD) ===
{$this->joinLines($catalogLines)}

=== MÉTODOS DE PAGO HABILITADOS ===
{$methods}

=== DATOS OBLIGATORIOS PARA EL DELIVERY ===
{$fieldsList}

=== ESTADO ACTUAL DE LA CONVERSACIÓN ===
Estado estricto actual: {$currentState}
Borrador actual de pedido: {$orderSnapshot}

=== REGLAS ESTRICTAS POR ESTADO ===
1. Si estado es GREETING:
   - OBLIGATORIO iniciar tu respuesta con un saludo cálido de gratitud basado en la directriz inicial antes de atender su consulta.
   - Luego atiende su mensaje y devuelve next_state = "SELLING_AND_CLOSING".
2. Si estado es SELLING_AND_CLOSING:
   - Resuelve dudas usando el contexto extenso y el inventario real (no inventes productos ni precios fuera del catálogo).
   - Arma el pedido en "order_items" usando los product_id o combo_id exactos, calcula totales en USD y Bs. (tasa {$rate}).
   - Solicita TODOS los datos obligatorios para el delivery que falten en "delivery_data".
   - Envía los datos de pago indicados en el contexto del negocio y pide el comprobante (foto o referencia/monto/banco/fecha).
   - SOLO cuando el cliente envíe un comprobante o referencia real de pago, coloca payment_data.has_proof = true y next_state = "PAYMENT_VERIFICATION".
3. Si estado es PAYMENT_VERIFICATION o PENDING_STORE_APPROVAL:
   - Indica al cliente que su comprobante ya fue recibido y está siendo validado por el equipo de la tienda para despachar su pedido.
   - Si envía datos corregidos o adicionales, extráelos en payment_data y mantén next_state = "PENDING_STORE_APPROVAL".
4. Si estado es APPROVED_AND_DISPATCHED:
   - Confirma que su pedido ya fue aprobado y despachado, o pregúntale si desea realizar un nuevo pedido.

=== FORMATO DE RESPUESTA OBLIGATORIO (JSON VÁLIDO SIN MARKDOWN) ===
Responde ÚNICAMENTE con un objeto JSON con esta estructura exacta:
{
  "reply": "Mensaje claro para enviar al cliente por WhatsApp",
  "next_state": "GREETING|SELLING_AND_CLOSING|PAYMENT_VERIFICATION|PENDING_STORE_APPROVAL",
  "order_items": [
    {"item_type": "product", "product_id": 1, "name": "Pastel de Carne Molida", "quantity": 2}
  ],
  "delivery_fee_usd": 0,
  "delivery_data": {
    "Nombre del cliente": "...",
    "Teléfono": "...",
    "Dirección exacta": "...",
    "Punto de referencia": "...",
    "Método de pago / Monto a cobrar": "...",
    "Detalle del paquete": "..."
  },
  "payment_data": {
    "has_proof": false,
    "method_name": "Pago Móvil",
    "currency": "VES",
    "amount": 0,
    "reference": "",
    "bank": "",
    "date": ""
  }
}
PROMPT;
    }

    public function processInboundMessage(
        Conversation $conversation,
        Message $inboundMessage,
        ?string $imageBase64 = null,
        ?string $imageMime = null
    ): ?Message {
        $settings = $conversation->businessAgentSetting ?? BusinessAgentSetting::activeDefault();
        if (! $settings) {
            return null;
        }

        if (! $conversation->business_agent_setting_id) {
            $conversation->update(['business_agent_setting_id' => $settings->id]);
        }

        // If previous order was already dispatched and customer starts a new interaction, open a fresh GREETING cycle
        if ($conversation->agent_state === WhatsAppOrder::STATE_APPROVED_AND_DISPATCHED) {
            $conversation->update(['agent_state' => WhatsAppOrder::STATE_GREETING]);
        }

        $order = $conversation->activeOrder;
        $systemPrompt = $this->buildDynamicSystemPrompt($settings, $conversation, $order);

        $history = $conversation->messages()
            ->latest('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        $llmOutput = $this->callLlm($settings, $systemPrompt, $history, $inboundMessage, $imageBase64, $imageMime);

        if ($llmOutput === null) {
            $llmOutput = $this->runRuleBasedFallback($settings, $conversation, $inboundMessage, $order);
        }

        $nextState = $this->resolveNextState($conversation->agent_state, $llmOutput, $inboundMessage);

        $order = $this->syncOrderState(
            $settings,
            $conversation,
            $order,
            $nextState,
            $llmOutput,
            $inboundMessage
        );

        $conversation->update([
            'agent_state' => $order?->status ?? $nextState,
            'session_context' => [
                'last_order_id' => $order?->id,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);

        $replyText = trim((string) ($llmOutput['reply'] ?? ''));
        if ($replyText === '') {
            return null;
        }

        return $this->evolution->sendAndRecordMessage($conversation, $replyText);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function callLlm(
        BusinessAgentSetting $settings,
        string $systemPrompt,
        $history,
        Message $inboundMessage,
        ?string $imageBase64,
        ?string $imageMime
    ): ?array {
        $apiKey = trim((string) ($settings->llm_api_key ?: env('LLM_API_KEY') ?: env('OPENAI_API_KEY') ?: env('GEMINI_API_KEY') ?: ''));
        if ($apiKey === '') {
            return null;
        }

        try {
            if ($settings->llm_provider === 'gemini') {
                return $this->callGemini($settings, $apiKey, $systemPrompt, $history, $inboundMessage, $imageBase64, $imageMime);
            }

            return $this->callOpenAiCompatible($settings, $apiKey, $systemPrompt, $history, $inboundMessage, $imageBase64, $imageMime);
        } catch (\Throwable $e) {
            Log::warning('[NEXORA][Agent] Fallo llamada LLM: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function callOpenAiCompatible(
        BusinessAgentSetting $settings,
        string $apiKey,
        string $systemPrompt,
        $history,
        Message $inboundMessage,
        ?string $imageBase64,
        ?string $imageMime
    ): ?array {
        $baseUrl = rtrim(trim((string) ($settings->llm_base_url ?: 'https://api.openai.com/v1')), '/');
        $model = trim((string) ($settings->llm_model ?: 'gpt-4o-mini'));

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($history as $msg) {
            if ($msg->id === $inboundMessage->id) {
                continue;
            }
            $messages[] = [
                'role' => $msg->direction === 'inbound' ? 'user' : 'assistant',
                'content' => (string) $msg->body,
            ];
        }

        if ($imageBase64) {
            $mime = $imageMime ?: 'image/jpeg';
            $messages[] = [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => (string) ($inboundMessage->body ?: 'Comprobante de pago adjunto')],
                    ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$imageBase64}"]],
                ],
            ];
        } else {
            $messages[] = ['role' => 'user', 'content' => (string) $inboundMessage->body];
        }

        $response = Http::timeout(25)
            ->withToken($apiKey)
            ->post("{$baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (! $response->successful()) {
            Log::warning('[NEXORA][Agent] OpenAI HTTP '.$response->status().': '.$response->body());

            return null;
        }

        $rawContent = (string) ($response->json('choices.0.message.content') ?? '');

        return $this->decodeJsonResponse($rawContent);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function callGemini(
        BusinessAgentSetting $settings,
        string $apiKey,
        string $systemPrompt,
        $history,
        Message $inboundMessage,
        ?string $imageBase64,
        ?string $imageMime
    ): ?array {
        $model = trim((string) ($settings->llm_model ?: 'gemini-2.0-flash'));
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $contents = [];
        foreach ($history as $msg) {
            if ($msg->id === $inboundMessage->id) {
                continue;
            }
            $contents[] = [
                'role' => $msg->direction === 'inbound' ? 'user' : 'model',
                'parts' => [['text' => (string) $msg->body]],
            ];
        }

        $parts = [['text' => (string) ($inboundMessage->body ?: 'Comprobante de pago adjunto')]];
        if ($imageBase64) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $imageMime ?: 'image/jpeg',
                    'data' => $imageBase64,
                ],
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => $parts];

        $response = Http::timeout(25)->post($endpoint, [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.3,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if (! $response->successful()) {
            Log::warning('[NEXORA][Agent] Gemini HTTP '.$response->status().': '.$response->body());

            return null;
        }

        $rawContent = (string) ($response->json('candidates.0.content.parts.0.text') ?? '');

        return $this->decodeJsonResponse($rawContent);
    }

    /**
     * Deterministic fallback when no LLM API key is configured.
     *
     * @return array<string, mixed>
     */
    protected function runRuleBasedFallback(
        BusinessAgentSetting $settings,
        Conversation $conversation,
        Message $inboundMessage,
        ?WhatsAppOrder $order
    ): array {
        $state = $conversation->agent_state ?: WhatsAppOrder::STATE_GREETING;
        $body = trim((string) $inboundMessage->body);
        $normalized = Str::lower(Str::ascii($body));

        $isPaymentProof = $inboundMessage->message_type === 'image'
            || preg_match('/\b(ref|referencia|comprobante|pago\s*movil|zelle|transferencia|pagu[eé]|banco)\b/i', $normalized) === 1;

        if ($state === WhatsAppOrder::STATE_GREETING) {
            $menu = $this->evolution->buildLiveMenuMessage();
            $fields = collect($settings->delivery_required_fields ?: BusinessAgentSetting::defaultDeliveryFields())
                ->map(fn ($f) => "• {$f}")
                ->implode("\n");

            return [
                'reply' => trim($settings->gratitude_greeting)."\n\n".$menu."\n\nCuando desees ordenar, envíame tu pedido junto con estos datos para el delivery:\n{$fields}",
                'next_state' => $isPaymentProof ? WhatsAppOrder::STATE_PAYMENT_VERIFICATION : WhatsAppOrder::STATE_SELLING_AND_CLOSING,
                'order_items' => [],
                'delivery_data' => [],
                'payment_data' => ['has_proof' => $isPaymentProof],
            ];
        }

        if ($isPaymentProof || $state === WhatsAppOrder::STATE_PENDING_STORE_APPROVAL || $state === WhatsAppOrder::STATE_PAYMENT_VERIFICATION) {
            $extracted = $this->extractPaymentByRegex($body);

            return [
                'reply' => '¡Muchas gracias! Hemos recibido tu comprobante de pago'.($extracted['reference'] ? " (Ref: {$extracted['reference']})" : '').'. Nuestro equipo en tienda está validando el pago para aprobar y despachar tu pedido de inmediato.',
                'next_state' => WhatsAppOrder::STATE_PAYMENT_VERIFICATION,
                'order_items' => $order?->items_json ?? [],
                'delivery_data' => $order?->delivery_data ?? [],
                'payment_data' => [
                    'has_proof' => true,
                    'method_name' => $extracted['method_name'],
                    'currency' => $extracted['currency'],
                    'amount' => $extracted['amount'],
                    'reference' => $extracted['reference'],
                    'bank' => $extracted['bank'],
                    'date' => now()->format('d/m/Y'),
                ],
            ];
        }

        return [
            'reply' => $this->evolution->buildLiveMenuMessage()."\n\nPor favor indícanos qué deseas ordenar, tus datos de entrega y envíanos el comprobante o número de referencia al realizar el pago.",
            'next_state' => WhatsAppOrder::STATE_SELLING_AND_CLOSING,
            'order_items' => $order?->items_json ?? [],
            'delivery_data' => $order?->delivery_data ?? [],
            'payment_data' => ['has_proof' => false],
        ];
    }

    /**
     * @param  array<string, mixed>  $llmOutput
     */
    protected function resolveNextState(string $currentState, array $llmOutput, Message $inboundMessage): string
    {
        $hasProof = (bool) data_get($llmOutput, 'payment_data.has_proof', false)
            || ($inboundMessage->message_type === 'image' && $currentState !== WhatsAppOrder::STATE_GREETING);

        if ($hasProof) {
            return WhatsAppOrder::STATE_PENDING_STORE_APPROVAL;
        }

        $requested = strtoupper(trim((string) ($llmOutput['next_state'] ?? '')));
        if (in_array($requested, [WhatsAppOrder::STATE_PAYMENT_VERIFICATION, WhatsAppOrder::STATE_PENDING_STORE_APPROVAL], true)) {
            return WhatsAppOrder::STATE_PENDING_STORE_APPROVAL;
        }

        if ($currentState === WhatsAppOrder::STATE_PENDING_STORE_APPROVAL) {
            return WhatsAppOrder::STATE_PENDING_STORE_APPROVAL;
        }

        return WhatsAppOrder::STATE_SELLING_AND_CLOSING;
    }

    /**
     * @param  array<string, mixed>  $llmOutput
     */
    protected function syncOrderState(
        BusinessAgentSetting $settings,
        Conversation $conversation,
        ?WhatsAppOrder $order,
        string $nextState,
        array $llmOutput,
        Message $inboundMessage
    ): ?WhatsAppOrder {
        $items = $this->normalizeOrderItems($llmOutput['order_items'] ?? []);
        $deliveryData = is_array($llmOutput['delivery_data'] ?? null) ? $llmOutput['delivery_data'] : [];
        $paymentData = is_array($llmOutput['payment_data'] ?? null) ? $llmOutput['payment_data'] : [];
        $hasProof = (bool) ($paymentData['has_proof'] ?? false) || $nextState === WhatsAppOrder::STATE_PENDING_STORE_APPROVAL;

        if (! $order && empty($items) && empty(array_filter($deliveryData)) && ! $hasProof) {
            return null;
        }

        $rate = CurrencyHelper::activeRate();
        $finalItems = ! empty($items) ? $items : ($order?->items_json ?? []);
        $subtotal = round(collect($finalItems)->sum(fn ($i) => ((float) ($i['unit_price_usd'] ?? 0)) * ((int) ($i['quantity'] ?? 1))), 4);
        $deliveryFee = isset($llmOutput['delivery_fee_usd']) && is_numeric($llmOutput['delivery_fee_usd'])
            ? max(0, (float) $llmOutput['delivery_fee_usd'])
            : (float) ($order?->delivery_fee_usd ?? 0);
        $totalUsd = round($subtotal + $deliveryFee, 4);
        $totalBs = round($totalUsd * $rate, 2);

        $mergedDelivery = array_merge($order?->delivery_data ?? [], array_filter($deliveryData, fn ($v) => $v !== null && trim((string) $v) !== ''));

        $payload = [
            'business_agent_setting_id' => $settings->id,
            'conversation_id' => $conversation->id,
            'customer_id' => $conversation->customer_id,
            'status' => $nextState,
            'items_json' => $finalItems,
            'subtotal_usd' => $subtotal,
            'delivery_fee_usd' => $deliveryFee,
            'total_usd' => $totalUsd,
            'exchange_rate_bs' => $rate,
            'total_bs' => $totalBs,
            'delivery_data' => $mergedDelivery,
        ];

        if ($hasProof) {
            $methodName = trim((string) ($paymentData['method_name'] ?? ''));
            $matchedMethod = $methodName !== ''
                ? PaymentMethod::active()->where('name', 'like', "%{$methodName}%")->first()
                : null;

            $payload['payment_method_id'] = $matchedMethod?->id ?? $order?->payment_method_id;
            $payload['payment_method_name'] = $methodName !== '' ? $methodName : ($matchedMethod?->name ?? $order?->payment_method_name);
            $payload['payment_currency'] = strtoupper((string) ($paymentData['currency'] ?? $matchedMethod?->currency ?? $order?->payment_currency ?? 'VES'));
            $payload['payment_amount'] = is_numeric($paymentData['amount'] ?? null) && (float) $paymentData['amount'] > 0
                ? (float) $paymentData['amount']
                : $order?->payment_amount;
            $payload['payment_reference'] = trim((string) ($paymentData['reference'] ?? '')) ?: $order?->payment_reference;
            $payload['payment_bank'] = trim((string) ($paymentData['bank'] ?? '')) ?: $order?->payment_bank;
            $payload['payment_date'] = trim((string) ($paymentData['date'] ?? '')) ?: ($order?->payment_date ?? now()->format('d/m/Y'));
            $payload['payment_raw_text'] = trim((string) $inboundMessage->body) ?: $order?->payment_raw_text;
            $payload['payment_proof_media_url'] = $inboundMessage->media_url ?: $order?->payment_proof_media_url;
            $payload['store_alert_acknowledged'] = false;
        }

        if ($order) {
            $order->update($payload);

            return $order->fresh();
        }

        return WhatsAppOrder::query()->create([
            'order_code' => 'WA-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(3)),
            ...$payload,
        ]);
    }

    /**
     * Resolves real prices from Nexora Product/Combo catalog to prevent hallucinated totals.
     *
     * @param  array<int, mixed>  $rawItems
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeOrderItems(array $rawItems): array
    {
        $normalized = [];
        foreach ($rawItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $type = ($item['item_type'] ?? 'product') === 'combo' ? 'combo' : 'product';

            if ($type === 'product' && ! empty($item['product_id'])) {
                $prod = Product::query()->find((int) $item['product_id']);
                if ($prod) {
                    $normalized[] = [
                        'item_type' => 'product',
                        'product_id' => $prod->id,
                        'name' => $prod->name,
                        'quantity' => $qty,
                        'unit_price_usd' => (float) $prod->sale_price_usd,
                    ];
                }
            } elseif ($type === 'combo' && ! empty($item['combo_id'])) {
                $combo = Combo::query()->find((int) $item['combo_id']);
                if ($combo) {
                    $normalized[] = [
                        'item_type' => 'combo',
                        'combo_id' => $combo->id,
                        'name' => $combo->name,
                        'quantity' => $qty,
                        'unit_price_usd' => (float) $combo->promo_price_usd,
                        'selections' => $item['selections'] ?? [],
                    ];
                }
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeJsonResponse(string $raw): ?array
    {
        $clean = trim($raw);
        if (str_starts_with($clean, '```')) {
            $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $clean) ?? $clean;
        }

        $decoded = json_decode($clean, true);

        return is_array($decoded) && isset($decoded['reply']) ? $decoded : null;
    }

    /**
     * @return array{method_name: string, currency: string, amount: float|null, reference: string, bank: string}
     */
    protected function extractPaymentByRegex(string $text): array
    {
        $reference = '';
        if (preg_match('/(?:ref(?:erencia)?|nro|comprobante)[:\s#-]*([0-9A-Za-z]{4,20})/i', $text, $m)) {
            $reference = $m[1];
        } elseif (preg_match('/\b(\d{4,12})\b/', $text, $m)) {
            $reference = $m[1];
        }

        $amount = null;
        if (preg_match('/(?:bs\.?|usd|\$|monto)[:\s]*([0-9]+(?:[.,][0-9]{1,2})?)/i', $text, $m)) {
            $amount = (float) str_replace(',', '.', $m[1]);
        }

        $bank = '';
        foreach (['Banesco', 'Mercantil', 'Venezuela', 'BDV', 'Provincial', 'BBVA', 'Bancamiga', 'BNC', 'Zelle'] as $b) {
            if (stripos($text, $b) !== false) {
                $bank = $b;
                break;
            }
        }

        $isUsd = stripos($text, 'zelle') !== false || str_contains($text, '$') || stripos($text, 'usd') !== false;

        return [
            'method_name' => stripos($text, 'zelle') !== false ? 'Zelle' : ($isUsd ? 'Divisas ($)' : 'Pago Móvil'),
            'currency' => $isUsd ? 'USD' : 'VES',
            'amount' => $amount,
            'reference' => $reference,
            'bank' => $bank,
        ];
    }

    /**
     * @param  array<int, string>  $lines
     */
    protected function joinLines(array $lines): string
    {
        return empty($lines) ? '- Sin productos activos.' : implode("\n", $lines);
    }
}
