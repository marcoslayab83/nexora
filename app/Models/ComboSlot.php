<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComboSlot extends Model
{
    protected $fillable = ['combo_id', 'label', 'required_qty', 'sort_order'];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ComboSlotOption::class);
    }
}
