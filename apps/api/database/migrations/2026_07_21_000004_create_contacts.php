<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('identifier')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->boolean('blocked')->default(false);
            $table->json('additional_attributes')->default('{}');
            $table->json('custom_attributes')->default('{}');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'identifier']);
            $table->index(['account_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
