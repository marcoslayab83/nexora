<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Setting;
use DomainException;

class InventoryService
{
    public function recordRawMaterialMovement(
        RawMaterial $rawMaterial,
        float $qtyDelta,
        string $movementType,
        float $unitCostUsd = 0,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null,
        ?string $notes = null
    ): InventoryMovement {
        $stockBefore = (float) $rawMaterial->stock_qty;
        $stockAfter = round($stockBefore + $qtyDelta, 4);

        if ($qtyDelta < 0 && $stockAfter < 0 && ! Setting::getBool('allow_negative_stock', false)) {
            $unitSymbol = $rawMaterial->baseUnit?->symbol ?? 'und';
            throw new DomainException(
                "Stock insuficiente de materia prima '{$rawMaterial->name}'. Disponible: {$stockBefore} {$unitSymbol}, requerido: ".abs($qtyDelta)." {$unitSymbol}."
            );
        }

        $rawMaterial->update(['stock_qty' => $stockAfter]);

        return InventoryMovement::query()->create([
            'inventoriable_type' => 'raw_material',
            'inventoriable_id' => $rawMaterial->id,
            'movement_type' => $movementType,
            'qty_delta' => round($qtyDelta, 4),
            'unit_cost_usd' => round($unitCostUsd, 6),
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'user_id' => $userId,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    public function recordProductMovement(
        Product $product,
        float $qtyDelta,
        string $movementType,
        float $unitCostUsd = 0,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null,
        ?string $notes = null
    ): InventoryMovement {
        $stockBefore = (float) $product->stock_qty;
        $stockAfter = round($stockBefore + $qtyDelta, 4);

        if ($qtyDelta < 0 && $stockAfter < 0 && ! Setting::getBool('allow_negative_stock', false)) {
            throw new DomainException(
                "Stock insuficiente para '{$product->name}'. Disponible: {$stockBefore}, requerido: ".abs($qtyDelta).'.'
            );
        }

        $product->update(['stock_qty' => $stockAfter]);

        return InventoryMovement::query()->create([
            'inventoriable_type' => 'product',
            'inventoriable_id' => $product->id,
            'movement_type' => $movementType,
            'qty_delta' => round($qtyDelta, 4),
            'unit_cost_usd' => round($unitCostUsd, 6),
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'user_id' => $userId,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
