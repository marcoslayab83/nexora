<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleComboSelection extends Model
{
    protected $fillable = [
        'sale_item_id',
        'combo_slot_id',
        'product_id',
        'product_name',
        'quantity',
        'extra_price_usd',
        'unit_cost_usd',
        'total_cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'extra_price_usd' => 'decimal:4',
            'unit_cost_usd' => 'decimal:6',
            'total_cost_usd' => 'decimal:4',
        ];
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function comboSlot(): BelongsTo
    {
        return $this->belongsTo(ComboSlot::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
