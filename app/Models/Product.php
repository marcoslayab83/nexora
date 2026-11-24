<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'type',
        'stock_qty',
        'min_stock_qty',
        'cost_usd',
        'avg_stock_cost_usd',
        'last_cost_usd',
        'sale_price_usd',
        'image_path',
        'is_sellable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stock_qty' => 'decimal:4',
            'min_stock_qty' => 'decimal:4',
            'cost_usd' => 'decimal:6',
            'avg_stock_cost_usd' => 'decimal:6',
            'last_cost_usd' => 'decimal:6',
            'sale_price_usd' => 'decimal:4',
            'is_sellable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subRecipeLinks(): HasMany
    {
        return $this->hasMany(ProductSubRecipe::class);
    }

    public function productionBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class);
    }

    public function effectiveUnitCostUsd(): float
    {
        if ($this->type === 'manufactured') {
            return (float) $this->cost_usd > 0
                ? (float) $this->cost_usd
                : (float) $this->avg_stock_cost_usd;
        }

        $method = Setting::getValue('costing_method', 'weighted_average');

        return $method === 'last_cost' && (float) $this->last_cost_usd > 0
            ? (float) $this->last_cost_usd
            : (float) $this->avg_stock_cost_usd;
    }

    public function projectedProfitUsd(): float
    {
        return round((float) $this->sale_price_usd - $this->effectiveUnitCostUsd(), 4);
    }

    public function projectedMarginPercent(): float
    {
        $price = (float) $this->sale_price_usd;
        if ($price <= 0) {
            return 0.0;
        }

        return round(($this->projectedProfitUsd() / $price) * 100, 2);
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock_qty <= (float) $this->min_stock_qty;
    }
}
