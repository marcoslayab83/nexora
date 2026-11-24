<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'item_type',
        'product_id',
        'combo_id',
        'description',
        'quantity',
        'unit_price_usd',
        'unit_cost_usd',
        'total_price_usd',
        'total_cost_usd',
        'profit_usd',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_usd' => 'decimal:4',
            'unit_cost_usd' => 'decimal:6',
            'total_price_usd' => 'decimal:4',
            'total_cost_usd' => 'decimal:4',
            'profit_usd' => 'decimal:4',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function comboSelections(): HasMany
    {
        return $this->hasMany(SaleComboSelection::class);
    }
}
