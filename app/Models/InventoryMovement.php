<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'inventoriable_type',
        'inventoriable_id',
        'movement_type',
        'qty_delta',
        'unit_cost_usd',
        'stock_before',
        'stock_after',
        'reference_type',
        'reference_id',
        'user_id',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_delta' => 'decimal:4',
            'unit_cost_usd' => 'decimal:6',
            'stock_before' => 'decimal:4',
            'stock_after' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getItemNameAttribute(): string
    {
        if ($this->inventoriable_type === 'raw_material') {
            return RawMaterial::query()->whereKey($this->inventoriable_id)->value('name') ?? 'Insumo #'.$this->inventoriable_id;
        }

        return Product::query()->whereKey($this->inventoriable_id)->value('name') ?? 'Producto #'.$this->inventoriable_id;
    }
}
