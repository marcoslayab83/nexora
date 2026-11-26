<?php

namespace App\Services;

use App\Models\BusinessAgentSetting;
use App\Models\Combo;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\WhatsAppOrder;
use App\Support\CurrencyHelper;
use DomainException;
use Illuminate\Support\Facades\DB;

class WhatsAppOrderDispatchService
{
    public function __construct(
        protected CashService $cashService,
        protected PosCheckoutService $posCheckoutService,
        protected EvolutionApiService $evolution
    ) {}

    /**
     * Human-in-the-Loop approval: records sale/inventory/cash deduction and dispatches
     * WhatsApp notifications to both the customer and the delivery provider.
     *
     * @param  array<int, array<string, mixed>>|null  $itemsOverride
     * @param  array<string, mixed>|null  $deliveryDataOverride
     * @return array{order: WhatsAppOrder, sale: Sale, customer_notified: bool, delivery_notified: bool, delivery_message: string}
     */
    public function authorizeAndDispatch(
        WhatsAppOrder $order,
        int $operatorUserId,
        ?int $paymentMethodId = null,
        ?string $referenceOverride = null,
        ?float $amountOverride = null,
        ?array $itemsOverride = null,
        ?array $deliveryDataOverride = null
    ): array {
        $result = DB::transaction(function () use (
            $order,
            $operatorUserId,
            $paymentMethodId,
            $referenceOverride,
            $amountOverride,
            $itemsOverride,
            $deliveryDataOverride
        ) {
            $lockedOrder = WhatsAppOrder::query()
                ->with(['customer', 'conversation', 'businessAgentSetting'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->status === WhatsAppOrder::STATE_APPROVED_AND_DISPATCHED) {
                throw new DomainException("El pedido {$lockedOrder->order_code} ya fue aprobado y despachado.");
            }

            $session = $this->cashService->anyOpenSession();
            if (! $session) {
                throw new DomainException('Debes abrir una jornada de caja antes de autorizar el pago y descontar inventario.');
            }

            $items = $itemsOverride !== null ? $itemsOverride : ($lockedOrder->items_json ?? []);
            $checkoutItems = $this->prepareCheckoutItems($items);

            if (empty($checkoutItems)) {
                throw new DomainException('El pedido debe tener al menos un producto o combo válido de Nexora para descontar inventario.');
            }

            $method = $paymentMethodId
                ? PaymentMethod::query()->findOrFail($paymentMethodId)
                : ($lockedOrder->paymentMethod ?? PaymentMethod::active()->firstOrFail());

            $rate = CurrencyHelper::activeRate();
            $orderTotalUsd = collect($checkoutItems)->sum(function ($it) {
                if ($it['item_type'] === 'product') {
                    return (float) \App\Models\Product::query()->whereKey($it['product_id'])->value('sale_price_usd') * $it['quantity'];
                }

                return (float) Combo::query()->whereKey($it['combo_id'])->value('promo_price_usd') * $it['quantity'];
            });

            $reference = trim((string) ($referenceOverride ?? $lockedOrder->payment_reference ?? ''));
            if ($method->requires_reference && $reference === '') {
                $reference = $lockedOrder->order_code;
            }

            $payAmount = $amountOverride !== null && $amountOverride > 0
                ? $amountOverride
                : ($lockedOrder->payment_amount && (float) $lockedOrder->payment_amount > 0
                    ? (float) $lockedOrder->payment_amount
                    : ($method->currency === 'VES' ? round($orderTotalUsd * $rate, 2) : round($orderTotalUsd, 2)));

            $sale = $this->posCheckoutService->checkout(
                $session,
                $operatorUserId,
                $checkoutItems,
                [[
                    'payment_method_id' => $method->id,
                    'amount_original' => $payAmount,
                    'reference_number' => $reference ?: null,
                ]],
                0,
                0,
                0,
                "Pedido WhatsApp {$lockedOrder->order_code}",
                $lockedOrder->customer_id,
                'whatsapp'
            );

            $deliveryData = $deliveryDataOverride !== null ? $deliveryDataOverride : ($lockedOrder->delivery_data ?? []);

            $lockedOrder->update([
                'status' => WhatsAppOrder::STATE_APPROVED_AND_DISPATCHED,
                'sale_id' => $sale->id,
                'items_json' => $items,
                'subtotal_usd' => $sale->subtotal_usd,
                'total_usd' => $sale->total_usd,
                'exchange_rate_bs' => $sale->exchange_rate_bs,
                'total_bs' => $sale->total_bs,
                'delivery_data' => $deliveryData,
                'payment_method_id' => $method->id,
                'payment_method_name' => $method->name,
                'payment_currency' => $method->currency,
                'payment_amount' => $payAmount,
                'payment_reference' => $reference ?: $lockedOrder->payment_reference,
                'store_alert_acknowledged' => true,
                'approved_by_user_id' => $operatorUserId,
                'approved_at' => now(),
                'dispatched_to_delivery_at' => now(),
            ]);

            $lockedOrder->conversation?->update([
                'agent_state' => WhatsAppOrder::STATE_APPROVED_AND_DISPATCHED,
            ]);

            return [$lockedOrder->fresh(['customer', 'conversation', 'businessAgentSetting', 'sale.items']), $sale];
        });

        /** @var WhatsAppOrder $approvedOrder */
        /** @var Sale $sale */
        [$approvedOrder, $sale] = $result;

        // 1. Notify customer via WhatsApp
        $customerMsgText = $this->buildCustomerApprovedMessage($approvedOrder, $sale);
        $customerNotified = false;
        if ($approvedOrder->conversation) {
            $sentCustomerMsg = $this->evolution->sendAndRecordMessage($approvedOrder->conversation, $customerMsgText, $operatorUserId);
            $customerNotified = $sentCustomerMsg->status === 'sent';
        }

        // 2. Notify delivery provider via WhatsApp
        $settings = $approvedOrder->businessAgentSetting ?? BusinessAgentSetting::activeDefault();
        $deliveryMessage = $this->buildDeliveryProviderMessage($approvedOrder, $sale, $settings);
        $deliveryNotified = false;

        if ($settings && trim((string) $settings->delivery_provider_phone) !== '') {
            $deliveryRes = $this->evolution->sendText($settings->delivery_provider_phone, $deliveryMessage);
            $deliveryNotified = (bool) ($deliveryRes['ok'] ?? false);
        }

        return [
            'order' => $approvedOrder,
            'sale' => $sale,
            'customer_notified' => $customerNotified,
            'delivery_notified' => $deliveryNotified,
            'delivery_message' => $deliveryMessage,
        ];
    }

    public function buildCustomerApprovedMessage(WhatsAppOrder $order, Sale $sale): string
    {
        $ref = $order->payment_reference ? " (Ref: {$order->payment_reference})" : '';
        $lines = [
            '✅ *¡Pago Aprobado y Pedido en Camino!*',
            "Pedido: *{$order->order_code}* | Ticket: *{$sale->invoice_number}*",
            "Hemos validado tu pago{$ref} exitosamente.",
            '',
            '*Resumen de tu pedido:*',
        ];

        foreach ($sale->items as $item) {
            $lines[] = "• {$item->quantity}x {$item->description}";
        }

        $lines[] = '';
        $lines[] = 'Nuestro equipo ya coordinó el despacho con el delivery. ¡Gracias por tu compra!';

        return implode("\n", $lines);
    }

    public function buildDeliveryProviderMessage(
        WhatsAppOrder $order,
        Sale $sale,
        ?BusinessAgentSetting $settings
    ): string {
        $businessName = $settings?->business_name ?: config('app.name', 'Nexora');
        $requiredFields = $settings?->delivery_required_fields ?: BusinessAgentSetting::defaultDeliveryFields();
        $collected = $order->delivery_data ?? [];

        $packageDetail = $sale->items
            ->map(fn ($i) => "{$i->quantity}x {$i->description}")
            ->implode(', ');

        $lines = [
            "🛵 *NUEVO DESPACHO — {$businessName}*",
            "Orden: *{$order->order_code}* | Ticket: *{$sale->invoice_number}*",
            '',
        ];

        foreach ($requiredFields as $fieldLabel) {
            $val = $this->resolveFieldValue($fieldLabel, $collected, $order, $packageDetail);
            $lines[] = "*{$fieldLabel}:* {$val}";
        }

        $lines[] = '';
        $lines[] = 'Estado del pago: *PAGADO / VERIFICADO EN TIENDA*';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $collected
     */
    protected function resolveFieldValue(string $fieldLabel, array $collected, WhatsAppOrder $order, string $packageDetail): string
    {
        if (isset($collected[$fieldLabel]) && trim((string) $collected[$fieldLabel]) !== '') {
            return trim((string) $collected[$fieldLabel]);
        }

        $needle = mb_strtolower($fieldLabel);
        foreach ($collected as $k => $v) {
            if (trim((string) $v) !== '' && (str_contains(mb_strtolower((string) $k), $needle) || str_contains($needle, mb_strtolower((string) $k)))) {
                return trim((string) $v);
            }
        }

        if (str_contains($needle, 'nombre')) {
            return $order->customer?->displayName() ?? 'Por confirmar';
        }
        if (str_contains($needle, 'tel')) {
            return $order->customer ? '+'.$order->customer->phone : 'Por confirmar';
        }
        if (str_contains($needle, 'paquete') || str_contains($needle, 'detalle') || str_contains($needle, 'pedido')) {
            return $packageDetail ?: 'Según orden';
        }
        if (str_contains($needle, 'pago') || str_contains($needle, 'monto') || str_contains($needle, 'cobrar')) {
            return 'Pagado ('.($order->payment_method_name ?? 'Confirmado').')';
        }

        return 'No especificado';
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function prepareCheckoutItems(array $items): array
    {
        $checkout = [];

        foreach ($items as $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $type = ($item['item_type'] ?? 'product') === 'combo' ? 'combo' : 'product';

            if ($type === 'product' && ! empty($item['product_id'])) {
                $checkout[] = [
                    'item_type' => 'product',
                    'product_id' => (int) $item['product_id'],
                    'quantity' => $qty,
                ];
            } elseif ($type === 'combo' && ! empty($item['combo_id'])) {
                $combo = Combo::query()->with('slots.options')->find((int) $item['combo_id']);
                if (! $combo) {
                    continue;
                }

                $selections = $item['selections'] ?? [];
                if (empty($selections)) {
                    // Auto-fill first eligible options if LLM only captured combo_id without slot breakdown
                    foreach ($combo->slots as $slot) {
                        $firstOpt = $slot->options->first();
                        if ($firstOpt) {
                            $selections[] = [
                                'combo_slot_id' => $slot->id,
                                'product_id' => $firstOpt->product_id,
                                'quantity' => (int) $slot->required_qty,
                            ];
                        }
                    }
                }

                $checkout[] = [
                    'item_type' => 'combo',
                    'combo_id' => $combo->id,
                    'quantity' => $qty,
                    'selections' => $selections,
                ];
            }
        }

        return $checkout;
    }
}
