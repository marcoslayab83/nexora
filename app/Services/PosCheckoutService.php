<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\Combo;
use App\Models\ComboSlotOption;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleComboSelection;
use App\Models\SaleItem;
use App\Models\SalePayment;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosCheckoutService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Processes a complete POS sale with individual products, flexible mixed combos,
     * historical cost capture, split/mixed payments, and BCV rate snapshot.
     *
     * @param  array<int, array<string, mixed>>  $cartItems
     * @param  array<int, array{payment_method_id: int, amount_original: float, reference_number?: string|null}>  $payments
     */
    public function checkout(
        CashSession $session,
        int $userId,
        array $cartItems,
        array $payments,
        float $discountUsd = 0,
        float $changeBs = 0,
        float $changeUsd = 0,
        ?string $notes = null,
        ?int $customerId = null,
        string $channel = 'pos'
    ): Sale {
        if ($session->status !== 'open') {
            throw new DomainException('Debes tener una caja abierta para registrar ventas.');
        }
        if (empty($cartItems)) {
            throw new \InvalidArgumentException('El carrito de venta está vacío.');
        }
        if (empty($payments)) {
            throw new \InvalidArgumentException('Debes registrar al menos un método de pago.');
        }

        return DB::transaction(function () use (
            $session,
            $userId,
            $cartItems,
            $payments,
            $discountUsd,
            $changeBs,
            $changeUsd,
            $notes,
            $customerId,
            $channel
        ) {
            $lockedSession = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($lockedSession->status !== 'open') {
                throw new DomainException('La sesión de caja ya fue cerrada.');
            }

            $rate = ExchangeRate::currentRate('USD');
            if ($rate <= 0) {
                $rate = 1.0;
            }

            $sale = Sale::query()->create([
                'invoice_number' => 'NX-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
                'cash_session_id' => $lockedSession->id,
                'user_id' => $userId,
                'customer_id' => $customerId,
                'channel' => $channel,
                'exchange_rate_bs' => $rate,
                'subtotal_usd' => 0,
                'discount_usd' => round(max(0, $discountUsd), 4),
                'total_usd' => 0,
                'total_bs' => 0,
                'total_cost_usd' => 0,
                'net_profit_usd' => 0,
                'change_bs' => round(max(0, $changeBs), 2),
                'change_usd' => round(max(0, $changeUsd), 2),
                'status' => 'completed',
                'notes' => $notes,
                'sold_at' => now(),
            ]);

            $subtotalUsd = 0.0;
            $totalCostUsd = 0.0;

            foreach ($cartItems as $line) {
                $qty = max(1, (int) ($line['quantity'] ?? 1));
                $itemType = $line['item_type'] ?? 'product';

                if ($itemType === 'product') {
                    $product = Product::query()->lockForUpdate()->findOrFail($line['product_id']);
                    $unitPrice = (float) $product->sale_price_usd;
                    $unitCost = $product->effectiveUnitCostUsd();
                    $lineTotalPrice = round($unitPrice * $qty, 4);
                    $lineTotalCost = round($unitCost * $qty, 4);

                    SaleItem::query()->create([
                        'sale_id' => $sale->id,
                        'item_type' => 'product',
                        'product_id' => $product->id,
                        'combo_id' => null,
                        'description' => $product->name,
                        'quantity' => $qty,
                        'unit_price_usd' => $unitPrice,
                        'unit_cost_usd' => $unitCost,
                        'total_price_usd' => $lineTotalPrice,
                        'total_cost_usd' => $lineTotalCost,
                        'profit_usd' => round($lineTotalPrice - $lineTotalCost, 4),
                    ]);

                    $this->inventoryService->recordProductMovement(
                        $product,
                        -$qty,
                        'sale_out',
                        $unitCost,
                        'Sale',
                        $sale->id,
                        $userId,
                        "Venta {$sale->invoice_number}"
                    );

                    $subtotalUsd += $lineTotalPrice;
                    $totalCostUsd += $lineTotalCost;
                } elseif ($itemType === 'combo') {
                    $combo = Combo::query()->with('slots.options')->findOrFail($line['combo_id']);
                    $selectionsInput = $line['selections'] ?? [];

                    $resolvedCombo = $this->resolveComboSelections($combo, $qty, $selectionsInput);
                    $unitComboPrice = round((float) $combo->promo_price_usd + $resolvedCombo['extra_per_combo_usd'], 4);
                    $lineTotalPrice = round($unitComboPrice * $qty, 4);
                    $lineTotalCost = $resolvedCombo['total_cost_usd'];
                    $unitComboCost = round($lineTotalCost / $qty, 6);

                    $saleItem = SaleItem::query()->create([
                        'sale_id' => $sale->id,
                        'item_type' => 'combo',
                        'product_id' => null,
                        'combo_id' => $combo->id,
                        'description' => $combo->name,
                        'quantity' => $qty,
                        'unit_price_usd' => $unitComboPrice,
                        'unit_cost_usd' => $unitComboCost,
                        'total_price_usd' => $lineTotalPrice,
                        'total_cost_usd' => $lineTotalCost,
                        'profit_usd' => round($lineTotalPrice - $lineTotalCost, 4),
                    ]);

                    foreach ($resolvedCombo['selections'] as $sel) {
                        SaleComboSelection::query()->create([
                            'sale_item_id' => $saleItem->id,
                            'combo_slot_id' => $sel['combo_slot_id'],
                            'product_id' => $sel['product']->id,
                            'product_name' => $sel['product']->name,
                            'quantity' => $sel['quantity'],
                            'extra_price_usd' => $sel['extra_price_usd'],
                            'unit_cost_usd' => $sel['unit_cost_usd'],
                            'total_cost_usd' => $sel['total_cost_usd'],
                        ]);

                        $this->inventoryService->recordProductMovement(
                            $sel['product'],
                            -$sel['quantity'],
                            'sale_out',
                            $sel['unit_cost_usd'],
                            'Sale',
                            $sale->id,
                            $userId,
                            "Combo {$combo->name} ({$sale->invoice_number})"
                        );
                    }

                    $subtotalUsd += $lineTotalPrice;
                    $totalCostUsd += $lineTotalCost;
                } else {
                    throw new \InvalidArgumentException("Tipo de línea inválido: {$itemType}");
                }
            }

            $subtotalUsd = round($subtotalUsd, 4);
            $discount = round(min($subtotalUsd, max(0, $discountUsd)), 4);
            $totalUsd = round($subtotalUsd - $discount, 4);
            $totalBs = round($totalUsd * $rate, 2);
            $totalCostUsd = round($totalCostUsd, 4);
            $netProfitUsd = round($totalUsd - $totalCostUsd, 4);

            // Process and validate split/mixed payments
            $totalPaidUsd = 0.0;
            $cashDeltaBs = 0.0;
            $cashDeltaUsd = 0.0;

            foreach ($payments as $p) {
                $amountOriginal = round((float) ($p['amount_original'] ?? 0), 2);
                if ($amountOriginal <= 0) {
                    continue;
                }

                $method = PaymentMethod::query()->findOrFail($p['payment_method_id']);
                if ($method->requires_reference && empty(trim((string) ($p['reference_number'] ?? '')))) {
                    throw new DomainException("El método '{$method->name}' requiere número de referencia.");
                }

                $amountUsdEq = $method->currency === 'VES'
                    ? round($amountOriginal / $rate, 4)
                    : round($amountOriginal, 4);

                SalePayment::query()->create([
                    'sale_id' => $sale->id,
                    'payment_method_id' => $method->id,
                    'currency' => $method->currency,
                    'amount_original' => $amountOriginal,
                    'exchange_rate_used' => $rate,
                    'amount_usd_equivalent' => $amountUsdEq,
                    'reference_number' => $p['reference_number'] ?? null,
                ]);

                $totalPaidUsd += $amountUsdEq;

                if ($method->is_cash) {
                    if ($method->currency === 'VES') {
                        $cashDeltaBs += $amountOriginal;
                    } else {
                        $cashDeltaUsd += $amountOriginal;
                    }
                }
            }

            $changeTotalUsd = $changeUsd + ($changeBs / $rate);
            $netCollectedUsd = $totalPaidUsd - $changeTotalUsd;

            if ($netCollectedUsd < ($totalUsd - 0.02)) {
                throw new DomainException(
                    'El monto pagado ($'.number_format($totalPaidUsd, 2).') no cubre el total de la venta ($'.number_format($totalUsd, 2).').'
                );
            }

            $sale->update([
                'subtotal_usd' => $subtotalUsd,
                'discount_usd' => $discount,
                'total_usd' => $totalUsd,
                'total_bs' => $totalBs,
                'total_cost_usd' => $totalCostUsd,
                'net_profit_usd' => $netProfitUsd,
            ]);

            $lockedSession->update([
                'expected_cash_bs' => round((float) $lockedSession->expected_cash_bs + $cashDeltaBs - $changeBs, 2),
                'expected_cash_usd' => round((float) $lockedSession->expected_cash_usd + $cashDeltaUsd - $changeUsd, 2),
                'total_sales_usd' => round((float) $lockedSession->total_sales_usd + $totalUsd, 4),
                'total_cost_usd' => round((float) $lockedSession->total_cost_usd + $totalCostUsd, 4),
                'estimated_profit_usd' => round((float) $lockedSession->estimated_profit_usd + $netProfitUsd, 4),
            ]);

            if ($customerId) {
                $customer = Customer::query()->lockForUpdate()->find($customerId);
                if ($customer) {
                    $customer->update([
                        'total_orders' => $customer->total_orders + 1,
                        'total_spent_usd' => round((float) $customer->total_spent_usd + $totalUsd, 4),
                        'last_order_at' => now(),
                    ]);
                }
            }

            return $sale->fresh(['items.comboSelections', 'payments.paymentMethod', 'user', 'customer']);
        });
    }

    /**
     * Validates combo slot quantities and locks physical products for accurate deduction and cost calculation.
     *
     * @param  array<int, array{combo_slot_id: int, product_id: int, quantity: int}>  $selectionsInput
     * @return array{selections: array<int, array<string, mixed>>, extra_per_combo_usd: float, total_cost_usd: float}
     */
    protected function resolveComboSelections(Combo $combo, int $comboQty, array $selectionsInput): array
    {
        $bySlot = [];
        foreach ($selectionsInput as $sel) {
            $slotId = (int) $sel['combo_slot_id'];
            $productId = (int) $sel['product_id'];
            $qty = max(0, (int) $sel['quantity']);
            if ($qty === 0) {
                continue;
            }
            $bySlot[$slotId][$productId] = ($bySlot[$slotId][$productId] ?? 0) + $qty;
        }

        $resolved = [];
        $extraPerCombo = 0.0;
        $totalCostUsd = 0.0;

        foreach ($combo->slots as $slot) {
            $slotSelections = $bySlot[$slot->id] ?? [];
            $selectedTotal = array_sum($slotSelections);

            if ($selectedTotal !== (int) $slot->required_qty) {
                throw new DomainException(
                    "En el combo '{$combo->name}', la sección '{$slot->label}' requiere exactamente {$slot->required_qty} unidades (seleccionadas: {$selectedTotal})."
                );
            }

            foreach ($slotSelections as $productId => $perComboQty) {
                /** @var ComboSlotOption|null $option */
                $option = $slot->options->firstWhere('product_id', $productId);
                if (! $option) {
                    throw new DomainException("El producto #{$productId} no es válido para '{$slot->label}'.");
                }

                $product = Product::query()->lockForUpdate()->findOrFail($productId);
                $totalPhysicalQty = $perComboQty * $comboQty;
                $unitCost = $product->effectiveUnitCostUsd();
                $lineCost = round($unitCost * $totalPhysicalQty, 4);

                $extraPerCombo += (float) $option->extra_price_usd * $perComboQty;
                $totalCostUsd += $lineCost;

                $resolved[] = [
                    'combo_slot_id' => $slot->id,
                    'product' => $product,
                    'quantity' => $totalPhysicalQty,
                    'extra_price_usd' => (float) $option->extra_price_usd,
                    'unit_cost_usd' => $unitCost,
                    'total_cost_usd' => $lineCost,
                ];
            }
        }

        return [
            'selections' => $resolved,
            'extra_per_combo_usd' => round($extraPerCombo, 4),
            'total_cost_usd' => round($totalCostUsd, 4),
        ];
    }
}
