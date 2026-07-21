<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realtime_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('data');
            $table->timestamps();
            $table->index(['account_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realtime_events');
    }
};
