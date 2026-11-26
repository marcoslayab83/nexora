<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessAgentSetting extends Model
{
    protected $fillable = [
        'branch_code',
        'business_name',
        'gratitude_greeting',
        'extensive_business_context',
        'delivery_provider_phone',
        'delivery_required_fields',
        'llm_provider',
        'llm_api_key',
        'llm_model',
        'llm_base_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'delivery_required_fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function whatsappOrders(): HasMany
    {
        return $this->hasMany(WhatsAppOrder::class);
    }

    public static function defaultDeliveryFields(): array
    {
        return [
            'Nombre del cliente',
            'Teléfono',
            'Dirección exacta',
            'Punto de referencia',
            'Método de pago / Monto a cobrar',
            'Detalle del paquete',
        ];
    }

    public static function activeDefault(): ?self
    {
        return static::query()->where('is_active', true)->orderBy('id')->first()
            ?? static::query()->orderBy('id')->first();
    }
}
