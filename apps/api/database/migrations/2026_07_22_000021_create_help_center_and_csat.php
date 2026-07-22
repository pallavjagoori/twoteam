<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->string('default_locale')->default('en');
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });
        Schema::create('help_center_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('locale')->default('en');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['portal_id', 'slug', 'locale']);
        });
        Schema::create('help_center_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('help_center_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('content');
            $table->string('locale')->default('en');
            $table->string('status')->default('draft');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['portal_id', 'slug', 'locale']);
        });
        Schema::create('csat_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('feedback_message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csat_survey_responses');
        Schema::dropIfExists('help_center_articles');
        Schema::dropIfExists('help_center_categories');
        Schema::dropIfExists('portals');
    }
};
