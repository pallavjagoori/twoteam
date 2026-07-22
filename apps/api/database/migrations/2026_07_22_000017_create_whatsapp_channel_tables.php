<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number');
            $table->string('phone_number_id')->unique();
            $table->string('business_account_id');
            $table->text('encrypted_credentials');
            $table->string('provider')->default('whatsapp_cloud');
            $table->timestamps();
        });
        Schema::create('whatsapp_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('external_message_id')->nullable()->unique();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('inbound_whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('external_message_id');
            $table->json('payload');
            $table->timestamps();
            $table->unique(['channel_id', 'external_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_whatsapp_messages');
        Schema::dropIfExists('whatsapp_deliveries');
        Schema::dropIfExists('whatsapp_channels');
    }
};
