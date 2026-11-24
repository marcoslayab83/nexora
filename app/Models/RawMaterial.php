<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawMaterial extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'base_unit_id',
        'default_purchase_unit_id',
        'stock_qty',
        'min_stock_qty',
        'avg_cost_usd',
        'last_cost_usd',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stock_qty' => 'decimal:4',
            'min_stock_qty' => 'decimal:4',
            'avg_cost_usd' => 'decimal:6',
            'last_cost_usd' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function defaultPurchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'default_purchase_unit_id');
    }

    public function subRecipeItems(): HasMany
    {
        return $this->hasMany(SubRecipeItem::class);
    }

    public function activeCost(?string $method = null): float
    {
        $method ??= Setting::getValue('costing_method', 'weighted_average');

        return $method === 'last_cost'
            ? (float) $this->last_cost_usd
            : (float) $this->avg_cost_usd;
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock_qty <= (float) $this->min_stock_qty;
    }
}
