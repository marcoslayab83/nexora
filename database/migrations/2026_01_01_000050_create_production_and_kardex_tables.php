<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 40)->unique();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('planned_qty');
            $table->integer('produced_qty');
            $table->integer('waste_qty')->default(0);
            $table->decimal('total_cost_usd', 14, 4)->default(0);
            $table->decimal('unit_cost_usd', 14, 6)->default(0);
            $table->string('status', 20)->default('completed')->index();
            $table->text('notes')->nullable();
            $table->timestamp('produced_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('production_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained('production_batches')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->restrictOnDelete();
            $table->string('source_role', 20); // dough | filling
            $table->decimal('theoretical_qty', 14, 4);
            $table->decimal('actual_qty', 14, 4);
            $table->decimal('unit_cost_usd', 14, 6);
            $table->decimal('total_cost_usd', 14, 4);
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('inventoriable_type', 30); // raw_material | product
            $table->unsignedBigInteger('inventoriable_id');
            $table->string('movement_type', 30)->index();
            $table->decimal('qty_delta', 14, 4);
            $table->decimal('unit_cost_usd', 14, 6)->default(0);
            $table->decimal('stock_before', 14, 4);
            $table->decimal('stock_after', 14, 4);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inventoriable_type', 'inventoriable_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('production_consumptions');
        Schema::dropIfExists('production_batches');
    }
};
