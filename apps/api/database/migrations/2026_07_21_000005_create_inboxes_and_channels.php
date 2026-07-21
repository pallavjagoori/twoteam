<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('settings')->default('{}');
            $table->string('identifier')->unique();
            $table->string('secret');
            $table->string('hmac_token');
            $table->timestamps();
        });
        Schema::create('inboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('greeting_enabled')->default(false);
            $table->text('greeting_message')->nullable();
            $table->boolean('enable_email_collect')->default(true);
            $table->boolean('csat_survey_enabled')->default(false);
            $table->boolean('enable_auto_assignment')->default(true);
            $table->boolean('working_hours_enabled')->default(false);
            $table->text('out_of_office_message')->nullable();
            $table->string('timezone')->default('UTC');
            $table->boolean('allow_messages_after_resolved')->default(true);
            $table->boolean('lock_to_single_conversation')->default(false);
            $table->json('csat_config')->default('{}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inboxes');
        Schema::dropIfExists('channels');
    }
};
