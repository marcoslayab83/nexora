<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('tax_id', 40)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 40)->nullable()->unique();
            $table->string('name', 150);
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('default_purchase_unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('stock_qty', 14, 4)->default(0);
            $table->decimal('min_stock_qty', 14, 4)->default(0);
            $table->decimal('avg_cost_usd', 14, 6)->default(0);
            $table->decimal('last_cost_usd', 14, 6)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_number', 60)->nullable();
            $table->string('currency', 5)->default('USD');
            $table->decimal('exchange_rate_bs', 16, 6);
            $table->decimal('total_original', 14, 2)->default(0);
            $table->decimal('total_usd', 14, 4)->default(0);
            $table->string('status', 20)->default('completed')->index();
            $table->text('notes')->nullable();
            $table->timestamp('purchased_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->string('purchasable_type', 30);
            $table->unsignedBigInteger('purchasable_id');
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('qty_purchased', 14, 4);
            $table->decimal('qty_base', 14, 4);
            $table->decimal('unit_cost_original', 14, 4);
            $table->decimal('unit_cost_usd', 14, 6);
            $table->decimal('total_cost_usd', 14, 4);
            $table->timestamps();

            $table->index(['purchasable_type', 'purchasable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('raw_materials');
        Schema::dropIfExists('suppliers');
    }
};
