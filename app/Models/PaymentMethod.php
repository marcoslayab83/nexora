<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'code',
        'currency',
        'is_cash',
        'requires_reference',
        'commission_pct',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_cash' => 'boolean',
            'requires_reference' => 'boolean',
            'commission_pct' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
