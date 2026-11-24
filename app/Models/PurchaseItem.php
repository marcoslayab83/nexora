<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'purchasable_type',
        'purchasable_id',
        'unit_id',
        'qty_purchased',
        'qty_base',
        'unit_cost_original',
        'unit_cost_usd',
        'total_cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'qty_purchased' => 'decimal:4',
            'qty_base' => 'decimal:4',
            'unit_cost_original' => 'decimal:4',
            'unit_cost_usd' => 'decimal:6',
            'total_cost_usd' => 'decimal:4',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function getItemNameAttribute(): string
    {
        if ($this->purchasable_type === 'raw_material') {
            return RawMaterial::query()->whereKey($this->purchasable_id)->value('name') ?? 'Insumo #'.$this->purchasable_id;
        }

        return Product::query()->whereKey($this->purchasable_id)->value('name') ?? 'Producto #'.$this->purchasable_id;
    }
}
