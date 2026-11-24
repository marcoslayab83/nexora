<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionConsumption extends Model
{
    protected $fillable = [
        'production_batch_id',
        'raw_material_id',
        'source_role',
        'theoretical_qty',
        'actual_qty',
        'unit_cost_usd',
        'total_cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'theoretical_qty' => 'decimal:4',
            'actual_qty' => 'decimal:4',
            'unit_cost_usd' => 'decimal:6',
            'total_cost_usd' => 'decimal:4',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
