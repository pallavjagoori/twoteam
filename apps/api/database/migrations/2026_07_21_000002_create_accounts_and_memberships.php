<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('locale')->default('en');
            $table->string('domain')->nullable();
            $table->string('support_email')->nullable();
            $table->string('status')->default('active');
            $table->json('settings')->default('{}');
            $table->json('custom_attributes')->default('{}');
            $table->json('features')->default('{}');
            $table->timestamps();
        });

        Schema::create('account_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('agent');
            $table->string('availability')->default('online');
            $table->boolean('auto_offline')->default(true);
            $table->json('permissions')->default('[]');
            $table->timestamp('active_at')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_users');
        Schema::dropIfExists('accounts');
    }
};
