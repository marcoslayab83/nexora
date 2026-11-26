<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppOrder extends Model
{
    public const STATE_GREETING = 'GREETING';

    public const STATE_SELLING_AND_CLOSING = 'SELLING_AND_CLOSING';

    public const STATE_PAYMENT_VERIFICATION = 'PAYMENT_VERIFICATION';

    public const STATE_PENDING_STORE_APPROVAL = 'PENDING_STORE_APPROVAL';

    public const STATE_APPROVED_AND_DISPATCHED = 'APPROVED_AND_DISPATCHED';

    public const STATE_CANCELLED = 'CANCELLED';

    protected $table = 'whatsapp_orders';

    protected $fillable = [
        'order_code',
        'business_agent_setting_id',
        'conversation_id',
        'customer_id',
        'sale_id',
        'status',
        'items_json',
        'subtotal_usd',
        'delivery_fee_usd',
        'total_usd',
        'exchange_rate_bs',
        'total_bs',
        'delivery_data',
        'payment_method_id',
        'payment_method_name',
        'payment_currency',
        'payment_amount',
        'payment_reference',
        'payment_bank',
        'payment_date',
        'payment_proof_media_url',
        'payment_raw_text',
        'store_alert_acknowledged',
        'approved_by_user_id',
        'approved_at',
        'dispatched_to_delivery_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'items_json' => 'array',
            'delivery_data' => 'array',
            'subtotal_usd' => 'decimal:4',
            'delivery_fee_usd' => 'decimal:4',
            'total_usd' => 'decimal:4',
            'exchange_rate_bs' => 'decimal:6',
            'total_bs' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'store_alert_acknowledged' => 'boolean',
            'approved_at' => 'datetime',
            'dispatched_to_delivery_at' => 'datetime',
        ];
    }

    public function businessAgentSetting(): BelongsTo
    {
        return $this->belongsTo(BusinessAgentSetting::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATE_PENDING_STORE_APPROVAL;
    }
}
