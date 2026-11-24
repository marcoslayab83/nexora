<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBatch extends Model
{
    protected $fillable = [
        'batch_code',
        'product_id',
        'user_id',
        'planned_qty',
        'produced_qty',
        'waste_qty',
        'total_cost_usd',
        'unit_cost_usd',
        'status',
        'notes',
        'produced_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cost_usd' => 'decimal:4',
            'unit_cost_usd' => 'decimal:6',
            'produced_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class);
    }
}
