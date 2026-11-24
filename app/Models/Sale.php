<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number',
        'cash_session_id',
        'user_id',
        'exchange_rate_bs',
        'subtotal_usd',
        'discount_usd',
        'total_usd',
        'total_bs',
        'total_cost_usd',
        'net_profit_usd',
        'change_bs',
        'change_usd',
        'status',
        'notes',
        'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate_bs' => 'decimal:6',
            'subtotal_usd' => 'decimal:4',
            'discount_usd' => 'decimal:4',
            'total_usd' => 'decimal:4',
            'total_bs' => 'decimal:2',
            'total_cost_usd' => 'decimal:4',
            'net_profit_usd' => 'decimal:4',
            'change_bs' => 'decimal:2',
            'change_usd' => 'decimal:2',
            'sold_at' => 'datetime',
        ];
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }
}
