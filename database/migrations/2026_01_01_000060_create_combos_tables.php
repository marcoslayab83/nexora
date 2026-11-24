<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combos', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('promo_price_usd', 12, 4);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('combo_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_id')->constrained('combos')->cascadeOnDelete();
            $table->string('label', 120);
            $table->integer('required_qty');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('combo_slot_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_slot_id')->constrained('combo_slots')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('extra_price_usd', 10, 4)->default(0);
            $table->timestamps();

            $table->unique(['combo_slot_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_slot_options');
        Schema::dropIfExists('combo_slots');
        Schema::dropIfExists('combos');
    }
};
