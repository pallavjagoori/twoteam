<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('display_name')->nullable();
            $table->string('provider')->default('email');
            $table->string('uid')->nullable()->unique();
            $table->string('pubsub_token')->nullable()->unique();
            $table->json('ui_settings')->nullable();
            $table->string('type')->nullable();
        });

        Schema::create('auth_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id');
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['user_id', 'client_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'provider',
                'uid',
                'pubsub_token',
                'ui_settings',
                'type',
            ]);
        });
    }
};
