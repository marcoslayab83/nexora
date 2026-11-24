<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSubRecipe extends Model
{
    protected $fillable = ['product_id', 'sub_recipe_id', 'role', 'portions_factor'];

    protected function casts(): array
    {
        return [
            'portions_factor' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subRecipe(): BelongsTo
    {
        return $this->belongsTo(SubRecipe::class);
    }
}
