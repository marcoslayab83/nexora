<?php

namespace App\Services;

use App\Models\CostHistory;
use App\Models\Product;
use App\Models\ProductSubRecipe;
use App\Models\RawMaterial;
use App\Models\Setting;
use App\Models\SubRecipe;
use App\Models\SubRecipeItem;
use Illuminate\Support\Facades\DB;

class CostCascadeService
{
    /**
     * Recalculates all affected sub-recipes and manufactured products when a raw material cost changes.
     *
     * @return array{sub_recipes_updated: int, products_updated: int}
     */
    public function recalculateFromRawMaterial(
        RawMaterial $rawMaterial,
        string $triggerSource = 'purchase',
        ?int $referenceId = null
    ): array {
        return DB::transaction(function () use ($rawMaterial, $triggerSource, $referenceId) {
            $subRecipeIds = SubRecipeItem::query()
                ->where('raw_material_id', $rawMaterial->id)
                ->pluck('sub_recipe_id')
                ->unique()
                ->values();

            $subRecipesUpdated = 0;
            $affectedProductIds = collect();

            foreach ($subRecipeIds as $subRecipeId) {
                $subRecipe = SubRecipe::query()->find($subRecipeId);
                if (! $subRecipe) {
                    continue;
                }

                $this->recalculateSubRecipe($subRecipe, $triggerSource, $referenceId, cascadeToProducts: false);
                $subRecipesUpdated++;

                $productIds = ProductSubRecipe::query()
                    ->where('sub_recipe_id', $subRecipe->id)
                    ->pluck('product_id');

                $affectedProductIds = $affectedProductIds->merge($productIds);
            }

            $productsUpdated = 0;
            foreach ($affectedProductIds->unique() as $productId) {
                $product = Product::query()->find($productId);
                if (! $product) {
                    continue;
                }

                $this->recalculateProductCost($product, $triggerSource, $referenceId);
                $productsUpdated++;
            }

            return [
                'sub_recipes_updated' => $subRecipesUpdated,
                'products_updated' => $productsUpdated,
            ];
        });
    }

    public function recalculateSubRecipe(
        SubRecipe $subRecipe,
        string $triggerSource = 'recipe_update',
        ?int $referenceId = null,
        bool $cascadeToProducts = true
    ): SubRecipe {
        return DB::transaction(function () use ($subRecipe, $triggerSource, $referenceId, $cascadeToProducts) {
            $method = Setting::getValue('costing_method', 'weighted_average');
            $subRecipe->load('items.rawMaterial');

            $totalCost = 0.0;

            foreach ($subRecipe->items as $item) {
                $unitCost = $item->rawMaterial?->activeCost($method) ?? 0.0;
                $lineCost = round((float) $item->base_quantity * $unitCost, 6);

                if (abs((float) $item->line_cost_usd - $lineCost) > 0.0000005) {
                    $item->update(['line_cost_usd' => $lineCost]);
                }

                $totalCost += $lineCost;
            }

            $totalCost = round($totalCost, 6);
            $yield = max(0.01, (float) $subRecipe->yield_units);
            $newUnitCost = round($totalCost / $yield, 6);
            $oldUnitCost = (float) $subRecipe->unit_cost_usd;

            $subRecipe->update([
                'total_cost_usd' => $totalCost,
                'unit_cost_usd' => $newUnitCost,
            ]);

            if (abs($oldUnitCost - $newUnitCost) > 0.0000005) {
                CostHistory::query()->create([
                    'costable_type' => 'sub_recipe',
                    'costable_id' => $subRecipe->id,
                    'old_cost_usd' => $oldUnitCost,
                    'new_cost_usd' => $newUnitCost,
                    'trigger_source' => $triggerSource,
                    'reference_id' => $referenceId,
                    'notes' => "Recálculo sub-receta: {$subRecipe->name}",
                    'created_at' => now(),
                ]);
            }

            if ($cascadeToProducts) {
                $productIds = ProductSubRecipe::query()
                    ->where('sub_recipe_id', $subRecipe->id)
                    ->pluck('product_id')
                    ->unique();

                foreach ($productIds as $productId) {
                    if ($product = Product::query()->find($productId)) {
                        $this->recalculateProductCost($product, $triggerSource, $referenceId);
                    }
                }
            }

            return $subRecipe->fresh();
        });
    }

    public function recalculateProductCost(
        Product $product,
        string $triggerSource = 'recipe_update',
        ?int $referenceId = null
    ): Product {
        return DB::transaction(function () use ($product, $triggerSource, $referenceId) {
            if ($product->type !== 'manufactured') {
                return $product;
            }

            $product->load('subRecipeLinks.subRecipe');

            $newCost = 0.0;
            foreach ($product->subRecipeLinks as $link) {
                if (! $link->subRecipe) {
                    continue;
                }
                $newCost += (float) $link->subRecipe->unit_cost_usd * (float) $link->portions_factor;
            }

            $newCost = round($newCost, 6);
            $oldCost = (float) $product->cost_usd;

            $product->update(['cost_usd' => $newCost]);

            if (abs($oldCost - $newCost) > 0.0000005) {
                CostHistory::query()->create([
                    'costable_type' => 'product',
                    'costable_id' => $product->id,
                    'old_cost_usd' => $oldCost,
                    'new_cost_usd' => $newCost,
                    'trigger_source' => $triggerSource,
                    'reference_id' => $referenceId,
                    'notes' => "Recálculo en cascada: {$product->name}",
                    'created_at' => now(),
                ]);
            }

            return $product->fresh();
        });
    }

    public function recalculateAll(string $triggerSource = 'costing_method_change'): void
    {
        DB::transaction(function () use ($triggerSource) {
            SubRecipe::query()->chunkById(50, function ($subRecipes) use ($triggerSource) {
                foreach ($subRecipes as $subRecipe) {
                    $this->recalculateSubRecipe($subRecipe, $triggerSource, null, cascadeToProducts: false);
                }
            });

            Product::query()->where('type', 'manufactured')->chunkById(50, function ($products) use ($triggerSource) {
                foreach ($products as $product) {
                    $this->recalculateProductCost($product, $triggerSource, null);
                }
            });
        });
    }
}
