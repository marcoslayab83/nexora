<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'costable_type',
        'costable_id',
        'old_cost_usd',
        'new_cost_usd',
        'trigger_source',
        'reference_id',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_cost_usd' => 'decimal:6',
            'new_cost_usd' => 'decimal:6',
            'created_at' => 'datetime',
        ];
    }
}
