<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', fn (Blueprint $table) => $table->index(['account_id', 'last_activity_at'], 'contacts_search_order'));
        Schema::table('conversations', fn (Blueprint $table) => $table->index(['account_id', 'last_activity_at'], 'conversations_search_order'));
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['account_id', 'created_at'], 'messages_search_order');
            $table->index(['account_id', 'inbox_id', 'created_at'], 'messages_search_inbox');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', fn (Blueprint $table) => $table->dropIndex('contacts_search_order'));
        Schema::table('conversations', fn (Blueprint $table) => $table->dropIndex('conversations_search_order'));
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_search_order');
            $table->dropIndex('messages_search_inbox');
        });
    }
};
