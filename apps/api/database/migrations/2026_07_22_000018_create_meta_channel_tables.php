<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->string('external_account_id');
            $table->string('external_account_name')->nullable();
            $table->text('encrypted_credentials');
            $table->timestamps();
            $table->unique(['platform', 'external_account_id']);
        });
        Schema::create('meta_deliveries', function (Blueprint $table) {
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
        Schema::create('inbound_meta_messages', function (Blueprint $table) {
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
        Schema::dropIfExists('inbound_meta_messages');
        Schema::dropIfExists('meta_deliveries');
        Schema::dropIfExists('meta_channels');
    }
};
