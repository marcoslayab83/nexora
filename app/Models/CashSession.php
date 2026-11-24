<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $fillable = [
        'cash_register_id',
        'user_id',
        'opened_at',
        'closed_at',
        'opening_bs',
        'opening_usd',
        'expected_cash_bs',
        'expected_cash_usd',
        'counted_cash_bs',
        'counted_cash_usd',
        'difference_bs',
        'difference_usd',
        'total_sales_usd',
        'total_cost_usd',
        'estimated_profit_usd',
        'status',
        'closing_notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_bs' => 'decimal:2',
            'opening_usd' => 'decimal:2',
            'expected_cash_bs' => 'decimal:2',
            'expected_cash_usd' => 'decimal:2',
            'counted_cash_bs' => 'decimal:2',
            'counted_cash_usd' => 'decimal:2',
            'difference_bs' => 'decimal:2',
            'difference_usd' => 'decimal:2',
            'total_sales_usd' => 'decimal:4',
            'total_cost_usd' => 'decimal:4',
            'estimated_profit_usd' => 'decimal:4',
        ];
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
