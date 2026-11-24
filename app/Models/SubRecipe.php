<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubRecipe extends Model
{
    protected $fillable = [
        'name',
        'type',
        'yield_units',
        'total_cost_usd',
        'unit_cost_usd',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'yield_units' => 'decimal:2',
            'total_cost_usd' => 'decimal:6',
            'unit_cost_usd' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubRecipeItem::class);
    }

    public function productLinks(): HasMany
    {
        return $this->hasMany(ProductSubRecipe::class);
    }

    public function typeLabel(): string
    {
        return $this->type === 'dough' ? 'Masa' : 'Relleno';
    }
}
