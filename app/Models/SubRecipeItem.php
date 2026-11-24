<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubRecipeItem extends Model
{
    protected $fillable = [
        'sub_recipe_id',
        'raw_material_id',
        'unit_id',
        'quantity',
        'base_quantity',
        'line_cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'base_quantity' => 'decimal:4',
            'line_cost_usd' => 'decimal:6',
        ];
    }

    public function subRecipe(): BelongsTo
    {
        return $this->belongsTo(SubRecipe::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
