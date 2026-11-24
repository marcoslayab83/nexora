<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $fillable = ['name', 'symbol', 'base_unit_id', 'conversion_factor'];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
        ];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function derivedUnits(): HasMany
    {
        return $this->hasMany(Unit::class, 'base_unit_id');
    }

    /**
     * Converts a quantity expressed in this unit into its root base unit.
     */
    public function toBaseQty(float $qty): float
    {
        return round($qty * (float) $this->conversion_factor, 4);
    }

    /**
     * Resolves the root base unit ID (self if already base).
     */
    public function rootBaseUnitId(): int
    {
        return $this->base_unit_id ?? $this->id;
    }
}
