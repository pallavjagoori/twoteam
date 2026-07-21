<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content')->nullable();
            $table->string('echo_id')->nullable();
            $table->unsignedTinyInteger('message_type')->default(1);
            $table->string('content_type')->default('text');
            $table->string('status')->default('sent');
            $table->json('content_attributes')->default('{}');
            $table->boolean('private')->default(false);
            $table->string('source_id')->nullable();
            $table->text('external_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
