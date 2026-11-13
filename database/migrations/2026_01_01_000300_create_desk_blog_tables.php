<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reader comments on published articles (threaded, moderated).
        Schema::create('desk_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('desk_articles')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('desk_comments')->cascadeOnDelete();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->nullableMorphs('user');
            $table->string('guest_name', 80)->nullable();
            $table->string('guest_email', 191)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('is_staff')->default(false);
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['article_id', 'status']);
        });

        // Daily view/visitor counters per article.
        Schema::create('desk_article_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('desk_articles')->cascadeOnDelete();
            $table->date('date')->index();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('visitors')->default(0);

            $table->unique(['article_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desk_article_views');
        Schema::dropIfExists('desk_comments');
    }
};
