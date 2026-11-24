<?php

namespace App\Support;

use App\Models\ExchangeRate;
use App\Models\Setting;

class CurrencyHelper
{
    public static function displayCurrency(): string
    {
        $currency = session('display_currency');
        if (in_array($currency, ['USD', 'VES'], true)) {
            return $currency;
        }

        return Setting::getValue('default_display_currency', 'USD') === 'VES' ? 'VES' : 'USD';
    }

    public static function activeRate(): float
    {
        return ExchangeRate::currentRate('USD');
    }

    public static function toBs(float $usd, ?float $rate = null): float
    {
        $rate ??= static::activeRate();

        return round($usd * $rate, 2);
    }

    public static function toUsd(float $bs, ?float $rate = null): float
    {
        $rate ??= static::activeRate();
        if ($rate <= 0) {
            return 0.0;
        }

        return round($bs / $rate, 4);
    }

    public static function format(float $usd, ?string $currency = null, ?float $rate = null, int $decimals = 2): string
    {
        $currency ??= static::displayCurrency();

        if ($currency === 'VES') {
            $bs = static::toBs($usd, $rate);

            return 'Bs. '.number_format($bs, 2, ',', '.');
        }

        return '$'.number_format($usd, $decimals, '.', ',');
    }

    public static function formatOpposite(float $usd, ?string $currency = null, ?float $rate = null): string
    {
        $currency ??= static::displayCurrency();
        $opposite = $currency === 'USD' ? 'VES' : 'USD';

        return static::format($usd, $opposite, $rate);
    }
}
