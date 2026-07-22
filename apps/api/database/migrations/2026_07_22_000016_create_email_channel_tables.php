<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('email')->unique();
            $table->string('forward_to_email')->unique();
            $table->string('provider')->default('smtp');
            $table->text('encrypted_credentials')->nullable();
            $table->boolean('verified_for_sending')->default(false);
            $table->timestamps();
        });
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('message_id_header')->unique();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
        Schema::create('inbound_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('external_message_id');
            $table->string('in_reply_to')->nullable();
            $table->json('payload');
            $table->timestamps();
            $table->unique(['channel_id', 'external_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_emails');
        Schema::dropIfExists('email_deliveries');
        Schema::dropIfExists('email_channels');
    }
};
