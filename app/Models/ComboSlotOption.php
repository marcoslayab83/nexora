<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboSlotOption extends Model
{
    protected $fillable = ['combo_slot_id', 'product_id', 'extra_price_usd'];

    protected function casts(): array
    {
        return [
            'extra_price_usd' => 'decimal:4',
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ComboSlot::class, 'combo_slot_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
