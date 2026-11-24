<?php

namespace App\Services;

use App\Models\CostHistory;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\RawMaterial;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected CostCascadeService $costCascadeService
    ) {}

    /**
     * @param  array{supplier_id?: int|null, invoice_number?: string|null, currency: string, exchange_rate_bs?: float|null, notes?: string|null, purchased_at?: mixed}  $header
     * @param  array<int, array{purchasable_type: string, purchasable_id: int, unit_id: int, qty_purchased: float, unit_cost_original: float}>  $items
     */
    public function createPurchase(array $header, array $items, ?int $userId = null): Purchase
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('La compra debe incluir al menos un ítem.');
        }

        return DB::transaction(function () use ($header, $items, $userId) {
            $currency = strtoupper($header['currency'] ?? 'USD');
            $rate = (float) ($header['exchange_rate_bs'] ?? ExchangeRate::currentRate('USD'));
            if ($rate <= 0) {
                $rate = 1.0;
            }

            $purchase = Purchase::query()->create([
                'supplier_id' => $header['supplier_id'] ?? null,
                'user_id' => $userId,
                'invoice_number' => $header['invoice_number'] ?? null,
                'currency' => $currency,
                'exchange_rate_bs' => $rate,
                'total_original' => 0,
                'total_usd' => 0,
                'status' => 'completed',
                'notes' => $header['notes'] ?? null,
                'purchased_at' => $header['purchased_at'] ?? now(),
            ]);

            $totalOriginal = 0.0;
            $totalUsd = 0.0;

            foreach ($items as $row) {
                $qtyPurchased = (float) $row['qty_purchased'];
                $unitCostOriginal = (float) $row['unit_cost_original'];
                if ($qtyPurchased <= 0 || $unitCostOriginal < 0) {
                    throw new \InvalidArgumentException('Cantidad y costo de compra deben ser válidos.');
                }

                $unit = Unit::query()->findOrFail($row['unit_id']);
                $qtyBase = $unit->toBaseQty($qtyPurchased);
                if ($qtyBase <= 0) {
                    throw new \InvalidArgumentException('Cantidad base inválida.');
                }

                $lineOriginal = round($qtyPurchased * $unitCostOriginal, 4);
                $lineTotalUsd = $currency === 'VES'
                    ? round($lineOriginal / $rate, 4)
                    : round($lineOriginal, 4);

                $unitCostUsdPerBase = round($lineTotalUsd / $qtyBase, 6);

                PurchaseItem::query()->create([
                    'purchase_id' => $purchase->id,
                    'purchasable_type' => $row['purchasable_type'],
                    'purchasable_id' => $row['purchasable_id'],
                    'unit_id' => $unit->id,
                    'qty_purchased' => $qtyPurchased,
                    'qty_base' => $qtyBase,
                    'unit_cost_original' => $unitCostOriginal,
                    'unit_cost_usd' => $unitCostUsdPerBase,
                    'total_cost_usd' => $lineTotalUsd,
                ]);

                $totalOriginal += $lineOriginal;
                $totalUsd += $lineTotalUsd;

                if ($row['purchasable_type'] === 'raw_material') {
                    $this->processRawMaterialPurchase(
                        (int) $row['purchasable_id'],
                        $qtyBase,
                        $unitCostUsdPerBase,
                        $lineTotalUsd,
                        $purchase->id,
                        $userId
                    );
                } else {
                    $this->processResaleProductPurchase(
                        (int) $row['purchasable_id'],
                        $qtyBase,
                        $unitCostUsdPerBase,
                        $lineTotalUsd,
                        $purchase->id,
                        $userId
                    );
                }
            }

            $purchase->update([
                'total_original' => round($totalOriginal, 2),
                'total_usd' => round($totalUsd, 4),
            ]);

            return $purchase->fresh(['items', 'supplier']);
        });
    }

    protected function processRawMaterialPurchase(
        int $rawMaterialId,
        float $qtyBase,
        float $unitCostUsdPerBase,
        float $lineTotalUsd,
        int $purchaseId,
        ?int $userId
    ): void {
        $rm = RawMaterial::query()->lockForUpdate()->findOrFail($rawMaterialId);
        $currentStock = max(0.0, (float) $rm->stock_qty);
        $oldAvg = (float) $rm->avg_cost_usd;

        $newAvg = $currentStock > 0
            ? round((($currentStock * $oldAvg) + $lineTotalUsd) / ($currentStock + $qtyBase), 6)
            : $unitCostUsdPerBase;

        $rm->update([
            'avg_cost_usd' => $newAvg,
            'last_cost_usd' => $unitCostUsdPerBase,
        ]);

        if (abs($oldAvg - $newAvg) > 0.0000005) {
            CostHistory::query()->create([
                'costable_type' => 'raw_material',
                'costable_id' => $rm->id,
                'old_cost_usd' => $oldAvg,
                'new_cost_usd' => $newAvg,
                'trigger_source' => 'purchase',
                'reference_id' => $purchaseId,
                'notes' => "Compra #{$purchaseId}",
                'created_at' => now(),
            ]);
        }

        $this->inventoryService->recordRawMaterialMovement(
            $rm,
            $qtyBase,
            'purchase_in',
            $unitCostUsdPerBase,
            'Purchase',
            $purchaseId,
            $userId,
            "Ingreso por compra #{$purchaseId}"
        );

        $this->costCascadeService->recalculateFromRawMaterial($rm->fresh(), 'purchase', $purchaseId);
    }

    protected function processResaleProductPurchase(
        int $productId,
        float $qtyBase,
        float $unitCostUsdPerBase,
        float $lineTotalUsd,
        int $purchaseId,
        ?int $userId
    ): void {
        $product = Product::query()->lockForUpdate()->findOrFail($productId);
        $currentStock = max(0.0, (float) $product->stock_qty);
        $oldAvg = (float) $product->avg_stock_cost_usd;

        $newAvg = $currentStock > 0
            ? round((($currentStock * $oldAvg) + $lineTotalUsd) / ($currentStock + $qtyBase), 6)
            : $unitCostUsdPerBase;

        $product->update([
            'cost_usd' => $newAvg,
            'avg_stock_cost_usd' => $newAvg,
            'last_cost_usd' => $unitCostUsdPerBase,
        ]);

        if (abs($oldAvg - $newAvg) > 0.0000005) {
            CostHistory::query()->create([
                'costable_type' => 'product',
                'costable_id' => $product->id,
                'old_cost_usd' => $oldAvg,
                'new_cost_usd' => $newAvg,
                'trigger_source' => 'purchase',
                'reference_id' => $purchaseId,
                'notes' => "Compra reventa #{$purchaseId}",
                'created_at' => now(),
            ]);
        }

        $this->inventoryService->recordProductMovement(
            $product,
            $qtyBase,
            'purchase_in',
            $unitCostUsdPerBase,
            'Purchase',
            $purchaseId,
            $userId,
            "Compra reventa #{$purchaseId}"
        );
    }
}
