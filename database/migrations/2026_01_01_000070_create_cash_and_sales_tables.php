<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('location', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_bs', 14, 2)->default(0);
            $table->decimal('opening_usd', 14, 2)->default(0);
            $table->decimal('expected_cash_bs', 14, 2)->default(0);
            $table->decimal('expected_cash_usd', 14, 2)->default(0);
            $table->decimal('counted_cash_bs', 14, 2)->nullable();
            $table->decimal('counted_cash_usd', 14, 2)->nullable();
            $table->decimal('difference_bs', 14, 2)->nullable();
            $table->decimal('difference_usd', 14, 2)->nullable();
            $table->decimal('total_sales_usd', 14, 4)->default(0);
            $table->decimal('total_cost_usd', 14, 4)->default(0);
            $table->decimal('estimated_profit_usd', 14, 4)->default(0);
            $table->string('status', 20)->default('open')->index();
            $table->text('closing_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 10); // in | out
            $table->string('currency', 5); // VES | USD
            $table->decimal('amount', 14, 2);
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 40)->unique();
            $table->string('currency', 5); // VES | USD
            $table->boolean('is_cash')->default(false);
            $table->boolean('requires_reference')->default(false);
            $table->decimal('commission_pct', 5, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('exchange_rate_bs', 16, 6);
            $table->decimal('subtotal_usd', 14, 4);
            $table->decimal('discount_usd', 14, 4)->default(0);
            $table->decimal('total_usd', 14, 4);
            $table->decimal('total_bs', 16, 2);
            $table->decimal('total_cost_usd', 14, 4);
            $table->decimal('net_profit_usd', 14, 4);
            $table->decimal('change_bs', 14, 2)->default(0);
            $table->decimal('change_usd', 14, 2)->default(0);
            $table->string('status', 20)->default('completed')->index();
            $table->text('notes')->nullable();
            $table->timestamp('sold_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('item_type', 20); // product | combo
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('combo_id')->nullable()->constrained('combos')->nullOnDelete();
            $table->string('description');
            $table->integer('quantity');
            $table->decimal('unit_price_usd', 12, 4);
            $table->decimal('unit_cost_usd', 14, 6);
            $table->decimal('total_price_usd', 14, 4);
            $table->decimal('total_cost_usd', 14, 4);
            $table->decimal('profit_usd', 14, 4);
            $table->timestamps();
        });

        Schema::create('sale_combo_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();
            $table->foreignId('combo_slot_id')->nullable()->constrained('combo_slots')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 150);
            $table->integer('quantity');
            $table->decimal('extra_price_usd', 10, 4)->default(0);
            $table->decimal('unit_cost_usd', 14, 6);
            $table->decimal('total_cost_usd', 14, 4);
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->string('currency', 5); // VES | USD
            $table->decimal('amount_original', 14, 2);
            $table->decimal('exchange_rate_used', 16, 6);
            $table->decimal('amount_usd_equivalent', 14, 4);
            $table->string('reference_number', 60)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_combo_selections');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_registers');
    }
};
