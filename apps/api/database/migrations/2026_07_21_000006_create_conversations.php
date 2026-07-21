<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('display_id');
            $table->uuid('uuid')->unique();
            $table->string('status')->default('open');
            $table->string('priority')->nullable();
            $table->boolean('muted')->default(false);
            $table->json('additional_attributes')->default('{}');
            $table->json('custom_attributes')->default('{}');
            $table->timestamp('agent_last_seen_at')->nullable();
            $table->timestamp('assignee_last_seen_at')->nullable();
            $table->timestamp('contact_last_seen_at')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'display_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
