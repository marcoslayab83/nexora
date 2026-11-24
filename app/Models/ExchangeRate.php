<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    protected $fillable = [
        'currency_code',
        'rate_bs',
        'source',
        'is_active',
        'effective_at',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'rate_bs' => 'decimal:6',
            'is_active' => 'boolean',
            'effective_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function currentRate(string $currencyCode = 'USD'): float
    {
        $rate = static::query()
            ->where('currency_code', $currencyCode)
            ->where('is_active', true)
            ->latest('effective_at')
            ->value('rate_bs');

        return $rate ? (float) $rate : 1.0;
    }
}
