<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\ProductionConsumption;
use App\Models\RawMaterial;
use App\Models\Setting;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Calculates raw material consumption preview for a planned batch of a manufactured product.
     *
     * @return array{
     *   lines: array<int, array<string, mixed>>,
     *   aggregated: array<int, array<string, mixed>>,
     *   estimated_total_cost_usd: float,
     *   estimated_unit_cost_usd: float,
     *   has_shortages: bool
     * }
     */
    public function previewRequirements(Product $product, int $plannedQty): array
    {
        if ($product->type !== 'manufactured') {
            throw new DomainException("El producto '{$product->name}' es de reventa y no requiere producción.");
        }
        if ($plannedQty <= 0) {
            throw new \InvalidArgumentException('La cantidad a producir debe ser mayor a cero.');
        }

        $method = Setting::getValue('costing_method', 'weighted_average');
        $product->load('subRecipeLinks.subRecipe.items.rawMaterial.baseUnit');

        if ($product->subRecipeLinks->isEmpty()) {
            throw new DomainException("El producto '{$product->name}' no tiene asignadas sub-recetas de Masa y Relleno.");
        }

        $lines = [];
        $aggregated = [];
        $estimatedTotalCost = 0.0;

        foreach ($product->subRecipeLinks as $link) {
            $subRecipe = $link->subRecipe;
            if (! $subRecipe) {
                continue;
            }

            $yield = max(0.01, (float) $subRecipe->yield_units);
            $multiplier = ($plannedQty * (float) $link->portions_factor) / $yield;

            foreach ($subRecipe->items as $item) {
                $rm = $item->rawMaterial;
                if (! $rm) {
                    continue;
                }

                $qtyNeeded = round((float) $item->base_quantity * $multiplier, 4);
                $unitCost = $rm->activeCost($method);
                $lineCost = round($qtyNeeded * $unitCost, 4);
                $estimatedTotalCost += $lineCost;

                $lines[] = [
                    'raw_material_id' => $rm->id,
                    'raw_material_name' => $rm->name,
                    'unit_symbol' => $rm->baseUnit?->symbol ?? 'und',
                    'source_role' => $link->role,
                    'sub_recipe_name' => $subRecipe->name,
                    'theoretical_qty' => $qtyNeeded,
                    'unit_cost_usd' => $unitCost,
                    'total_cost_usd' => $lineCost,
                ];

                if (! isset($aggregated[$rm->id])) {
                    $aggregated[$rm->id] = [
                        'raw_material_id' => $rm->id,
                        'name' => $rm->name,
                        'unit_symbol' => $rm->baseUnit?->symbol ?? 'und',
                        'required_qty' => 0.0,
                        'stock_qty' => (float) $rm->stock_qty,
                        'shortage_qty' => 0.0,
                    ];
                }

                $aggregated[$rm->id]['required_qty'] = round($aggregated[$rm->id]['required_qty'] + $qtyNeeded, 4);
            }
        }

        $hasShortages = false;
        foreach ($aggregated as &$agg) {
            $diff = $agg['stock_qty'] - $agg['required_qty'];
            if ($diff < 0) {
                $agg['shortage_qty'] = round(abs($diff), 4);
                $hasShortages = true;
            }
        }

        $estimatedTotalCost = round($estimatedTotalCost, 4);

        return [
            'lines' => $lines,
            'aggregated' => array_values($aggregated),
            'estimated_total_cost_usd' => $estimatedTotalCost,
            'estimated_unit_cost_usd' => round($estimatedTotalCost / $plannedQty, 6),
            'has_shortages' => $hasShortages,
        ];
    }

    /**
     * Executes a production batch: deducts raw materials from dough + filling and adds finished pasteles to stock.
     */
    public function executeBatch(
        Product $product,
        int $plannedQty,
        int $producedQty,
        int $wasteQty = 0,
        ?int $userId = null,
        ?string $notes = null
    ): ProductionBatch {
        if ($producedQty <= 0) {
            throw new \InvalidArgumentException('Las unidades producidas deben ser mayores a cero.');
        }

        return DB::transaction(function () use ($product, $plannedQty, $producedQty, $wasteQty, $userId, $notes) {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $preview = $this->previewRequirements($lockedProduct, $plannedQty);
            $method = Setting::getValue('costing_method', 'weighted_average');

            $batch = ProductionBatch::query()->create([
                'batch_code' => 'LOTE-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
                'product_id' => $lockedProduct->id,
                'user_id' => $userId,
                'planned_qty' => $plannedQty,
                'produced_qty' => $producedQty,
                'waste_qty' => $wasteQty,
                'total_cost_usd' => 0,
                'unit_cost_usd' => 0,
                'status' => 'completed',
                'notes' => $notes,
                'produced_at' => now(),
            ]);

            $totalBatchCost = 0.0;

            foreach ($preview['lines'] as $line) {
                $rm = RawMaterial::query()->lockForUpdate()->findOrFail($line['raw_material_id']);
                $actualQty = (float) $line['theoretical_qty'];
                $unitCost = $rm->activeCost($method);
                $lineCost = round($actualQty * $unitCost, 4);
                $totalBatchCost += $lineCost;

                ProductionConsumption::query()->create([
                    'production_batch_id' => $batch->id,
                    'raw_material_id' => $rm->id,
                    'source_role' => $line['source_role'],
                    'theoretical_qty' => $line['theoretical_qty'],
                    'actual_qty' => $actualQty,
                    'unit_cost_usd' => $unitCost,
                    'total_cost_usd' => $lineCost,
                ]);

                $this->inventoryService->recordRawMaterialMovement(
                    $rm,
                    -$actualQty,
                    'production_out',
                    $unitCost,
                    'ProductionBatch',
                    $batch->id,
                    $userId,
                    "Consumo {$line['source_role']} lote {$batch->batch_code} ({$lockedProduct->name})"
                );
            }

            $totalBatchCost = round($totalBatchCost, 4);
            $batchUnitCost = round($totalBatchCost / $producedQty, 6);

            // Update weighted average stock cost of finished product
            $currentStock = max(0.0, (float) $lockedProduct->stock_qty);
            $currentAvgStockCost = (float) $lockedProduct->avg_stock_cost_usd > 0
                ? (float) $lockedProduct->avg_stock_cost_usd
                : (float) $lockedProduct->cost_usd;

            $newAvgStockCost = $currentStock > 0
                ? round((($currentStock * $currentAvgStockCost) + $totalBatchCost) / ($currentStock + $producedQty), 6)
                : $batchUnitCost;

            $lockedProduct->update([
                'avg_stock_cost_usd' => $newAvgStockCost,
                'last_cost_usd' => $batchUnitCost,
            ]);

            $batch->update([
                'total_cost_usd' => $totalBatchCost,
                'unit_cost_usd' => $batchUnitCost,
            ]);

            $this->inventoryService->recordProductMovement(
                $lockedProduct,
                $producedQty,
                'production_in',
                $batchUnitCost,
                'ProductionBatch',
                $batch->id,
                $userId,
                "Producción lote {$batch->batch_code}"
            );

            return $batch->fresh(['product', 'consumptions.rawMaterial']);
        });
    }
}
