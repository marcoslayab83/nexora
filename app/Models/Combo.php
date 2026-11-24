<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Combo extends Model
{
    protected $fillable = [
        'name',
        'description',
        'promo_price_usd',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'promo_price_usd' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ComboSlot::class)->orderBy('sort_order');
    }

    public function costRangeUsd(): array
    {
        $min = 0.0;
        $max = 0.0;

        foreach ($this->slots()->with('options.product')->get() as $slot) {
            $costs = $slot->options
                ->filter(fn ($opt) => $opt->product !== null)
                ->map(fn ($opt) => $opt->product->effectiveUnitCostUsd())
                ->sort()
                ->values();

            if ($costs->isNotEmpty()) {
                $min += $costs->first() * $slot->required_qty;
                $max += $costs->last() * $slot->required_qty;
            }
        }

        return [
            'min_cost_usd' => round($min, 4),
            'max_cost_usd' => round($max, 4),
            'min_profit_usd' => round((float) $this->promo_price_usd - $max, 4),
            'max_profit_usd' => round((float) $this->promo_price_usd - $min, 4),
        ];
    }
}
