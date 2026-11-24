<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('type', 20)->index(); // dough | filling
            $table->decimal('yield_units', 10, 2);
            $table->decimal('total_cost_usd', 14, 6)->default(0);
            $table->decimal('unit_cost_usd', 14, 6)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('sub_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_recipe_id')->constrained('sub_recipes')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->decimal('base_quantity', 14, 4);
            $table->decimal('line_cost_usd', 14, 6)->default(0);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('sku', 40)->nullable()->unique();
            $table->string('name', 150);
            $table->string('type', 20)->index(); // manufactured | resale
            $table->decimal('stock_qty', 14, 4)->default(0);
            $table->decimal('min_stock_qty', 14, 4)->default(0);
            $table->decimal('cost_usd', 14, 6)->default(0);
            $table->decimal('avg_stock_cost_usd', 14, 6)->default(0);
            $table->decimal('last_cost_usd', 14, 6)->default(0);
            $table->decimal('sale_price_usd', 12, 4)->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('is_sellable')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_sub_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('sub_recipe_id')->constrained('sub_recipes')->restrictOnDelete();
            $table->string('role', 20); // dough | filling
            $table->decimal('portions_factor', 8, 3)->default(1.000);
            $table->timestamps();

            $table->unique(['product_id', 'role']);
        });

        Schema::create('cost_histories', function (Blueprint $table) {
            $table->id();
            $table->string('costable_type', 30); // raw_material | sub_recipe | product
            $table->unsignedBigInteger('costable_id');
            $table->decimal('old_cost_usd', 14, 6);
            $table->decimal('new_cost_usd', 14, 6);
            $table->string('trigger_source', 40);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['costable_type', 'costable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_histories');
        Schema::dropIfExists('product_sub_recipes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('sub_recipe_items');
        Schema::dropIfExists('sub_recipes');
    }
};
