<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('selected_email_flags')->default('[]');
            $table->json('selected_push_flags')->default('[]');
            $table->timestamps();
            $table->unique(['account_id', 'user_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type');
            $table->string('primary_actor_type');
            $table->unsignedBigInteger('primary_actor_id');
            $table->string('secondary_actor_type')->nullable();
            $table->unsignedBigInteger('secondary_actor_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('last_activity_at');
            $table->json('meta')->default('{}');
            $table->timestamps();
            $table->unique(['account_id', 'user_id', 'primary_actor_type', 'primary_actor_id'], 'notifications_actor_unique');
            $table->index(['user_id', 'account_id', 'snoozed_until', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_settings');
    }
};
