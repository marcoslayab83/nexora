<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'address',
        'notes',
        'total_orders',
        'total_spent_usd',
        'last_order_at',
    ];

    protected function casts(): array
    {
        return [
            'total_spent_usd' => 'decimal:4',
            'last_order_at' => 'datetime',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function latestConversation(): HasOne
    {
        return $this->hasOne(Conversation::class)->latestOfMany('last_message_at');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function displayName(): string
    {
        return $this->name ?: '+'.$this->phone;
    }

    public static function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '04') && strlen($digits) === 11) {
            $digits = '58'.substr($digits, 1);
        } elseif (str_starts_with($digits, '4') && strlen($digits) === 10) {
            $digits = '58'.$digits;
        }

        return $digits;
    }
}
