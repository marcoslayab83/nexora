<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = [
        'customer_id',
        'business_agent_setting_id',
        'remote_jid',
        'status',
        'agent_state',
        'session_context',
        'assigned_user_id',
        'unread_count',
        'last_message_preview',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'session_context' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function businessAgentSetting(): BelongsTo
    {
        return $this->belongsTo(BusinessAgentSetting::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('sent_at')->orderBy('id');
    }

    public function whatsappOrders(): HasMany
    {
        return $this->hasMany(WhatsAppOrder::class);
    }

    public function activeOrder(): HasOne
    {
        return $this->hasOne(WhatsAppOrder::class)
            ->whereNotIn('status', [WhatsAppOrder::STATE_APPROVED_AND_DISPATCHED, WhatsAppOrder::STATE_CANCELLED])
            ->latestOfMany('id');
    }
}
