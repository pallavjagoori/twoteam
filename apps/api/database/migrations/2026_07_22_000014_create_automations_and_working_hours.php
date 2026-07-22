<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inbox_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('closed_all_day')->default(false);
            $table->boolean('open_all_day')->default(false);
            $table->unsignedTinyInteger('open_hour')->nullable();
            $table->unsignedTinyInteger('open_minutes')->nullable();
            $table->unsignedTinyInteger('close_hour')->nullable();
            $table->unsignedTinyInteger('close_minutes')->nullable();
            $table->timestamps();
            $table->unique(['inbox_id', 'day_of_week']);
        });
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('event_name');
            $table->boolean('active')->default(true);
            $table->json('conditions');
            $table->json('actions');
            $table->timestamps();
        });
        Schema::create('automation_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('event_key');
            $table->timestamps();
            $table->unique(['automation_rule_id', 'conversation_id', 'event_key'], 'automation_execution_unique');
        });

        DB::table('inboxes')->orderBy('id')->each(function ($inbox) {
            foreach (range(0, 6) as $day) {
                $weekend = in_array($day, [0, 6], true);
                DB::table('working_hours')->insert(['account_id' => $inbox->account_id, 'inbox_id' => $inbox->id, 'day_of_week' => $day, 'closed_all_day' => $weekend, 'open_all_day' => false, 'open_hour' => $weekend ? null : 9, 'open_minutes' => $weekend ? null : 0, 'close_hour' => $weekend ? null : 17, 'close_minutes' => $weekend ? null : 0, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_executions');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('working_hours');
    }
};
