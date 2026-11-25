<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->nullable();
            $table->string('phone', 30)->unique();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent_usd', 14, 4)->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('remote_jid', 80)->unique();
            $table->string('status', 20)->default('bot')->index(); // bot | human | closed
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('unread_count')->default(0);
            $table->string('last_message_preview', 255)->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->string('direction', 15); // inbound | outbound
            $table->string('message_type', 20)->default('text');
            $table->text('body')->nullable();
            $table->text('media_url')->nullable();
            $table->string('external_id', 120)->nullable()->index();
            $table->string('status', 20)->default('received');
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('customers')->nullOnDelete();
            $table->string('channel', 20)->default('pos')->after('customer_id')->index(); // pos | whatsapp
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('channel');
        });
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('customers');
    }
};
