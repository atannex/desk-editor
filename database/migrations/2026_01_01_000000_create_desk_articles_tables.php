<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desk_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('desk_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('desk_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('desk_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->string('locale', 12)->default('en')->index();
            $table->uuid('translation_group')->nullable()->index();
            $table->string('subtitle')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('cover_alt')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('featured')->default(false)->index();
            $table->boolean('allow_comments')->default(true);
            $table->foreignId('category_id')->nullable()->constrained('desk_categories')->nullOnDelete();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('canonical_url')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedSmallInteger('reading_time')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->nullableMorphs('author');
            $table->nullableMorphs('editor');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'locale']);
        });

        Schema::create('desk_article_tag', function (Blueprint $table) {
            $table->foreignId('desk_article_id')->constrained('desk_articles')->cascadeOnDelete();
            $table->foreignId('desk_tag_id')->constrained('desk_tags')->cascadeOnDelete();
            $table->primary(['desk_article_id', 'desk_tag_id']);
        });

        Schema::create('desk_article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desk_article_id')->constrained('desk_articles')->cascadeOnDelete();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('word_count')->default(0);
            $table->string('note')->nullable();
            $table->nullableMorphs('author');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('desk_article_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desk_article_id')->constrained('desk_articles')->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->nullableMorphs('causer');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desk_article_activities');
        Schema::dropIfExists('desk_article_revisions');
        Schema::dropIfExists('desk_article_tag');
        Schema::dropIfExists('desk_articles');
        Schema::dropIfExists('desk_tags');
        Schema::dropIfExists('desk_categories');
    }
};
