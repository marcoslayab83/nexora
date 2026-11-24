<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'supplier_id',
        'user_id',
        'invoice_number',
        'currency',
        'exchange_rate_bs',
        'total_original',
        'total_usd',
        'status',
        'notes',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate_bs' => 'decimal:6',
            'total_original' => 'decimal:2',
            'total_usd' => 'decimal:4',
            'purchased_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
