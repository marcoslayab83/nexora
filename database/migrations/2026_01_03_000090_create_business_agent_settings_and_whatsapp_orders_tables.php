<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_agent_settings', function (Blueprint $table) {
            $table->id();
            $table->string('branch_code', 50)->unique();
            $table->string('business_name', 150);
            $table->text('gratitude_greeting');
            $table->longText('extensive_business_context')->nullable();
            $table->string('delivery_provider_phone', 40)->nullable();
            $table->json('delivery_required_fields')->nullable();
            $table->string('llm_provider', 30)->default('openai'); // openai | gemini | openai_compatible
            $table->text('llm_api_key')->nullable();
            $table->string('llm_model', 80)->default('gpt-4o-mini');
            $table->string('llm_base_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('business_agent_setting_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('business_agent_settings')
                ->nullOnDelete();
            $table->string('agent_state', 40)->default('GREETING')->after('status')->index();
            $table->json('session_context')->nullable()->after('agent_state');
        });

        Schema::create('whatsapp_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 40)->unique();
            $table->foreignId('business_agent_setting_id')->nullable()->constrained('business_agent_settings')->nullOnDelete();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('status', 40)->default('SELLING_AND_CLOSING')->index();
            $table->json('items_json')->nullable();
            $table->decimal('subtotal_usd', 14, 4)->default(0);
            $table->decimal('delivery_fee_usd', 14, 4)->default(0);
            $table->decimal('total_usd', 14, 4)->default(0);
            $table->decimal('exchange_rate_bs', 16, 6)->default(1);
            $table->decimal('total_bs', 16, 2)->default(0);
            $table->json('delivery_data')->nullable();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('payment_method_name', 80)->nullable();
            $table->string('payment_currency', 5)->nullable();
            $table->decimal('payment_amount', 14, 2)->nullable();
            $table->string('payment_reference', 80)->nullable();
            $table->string('payment_bank', 80)->nullable();
            $table->string('payment_date', 40)->nullable();
            $table->text('payment_proof_media_url')->nullable();
            $table->text('payment_raw_text')->nullable();
            $table->boolean('store_alert_acknowledged')->default(false)->index();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_to_delivery_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_orders');

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_agent_setting_id');
            $table->dropColumn(['agent_state', 'session_context']);
        });

        Schema::dropIfExists('business_agent_settings');
    }
};
