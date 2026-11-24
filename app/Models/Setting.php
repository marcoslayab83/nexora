<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $record = static::query()->where('key', $key)->first();

        return $record?->value ?? $default;
    }

    public static function setValue(string $key, mixed $value, string $group = 'general'): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value, 'group' => $group]
        );
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = static::getValue($key);
        if ($val === null) {
            return $default;
        }

        return in_array(strtolower((string) $val), ['1', 'true', 'yes', 'on'], true);
    }
}
