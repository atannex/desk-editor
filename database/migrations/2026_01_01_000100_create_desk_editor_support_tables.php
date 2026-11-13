<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Server-side autosave drafts, keyed per user and document.
        Schema::create('desk_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191);
            $table->nullableMorphs('user');
            $table->longText('content');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['key', 'user_type', 'user_id']);
        });

        // Editorial comment threads anchored to text inside a document.
        Schema::create('desk_threads', function (Blueprint $table) {
            $table->id();
            $table->string('document', 191)->index();
            $table->string('anchor', 64)->unique();
            $table->text('quote')->nullable();
            $table->nullableMorphs('user');
            $table->timestamp('resolved_at')->nullable();
            $table->nullableMorphs('resolver');
            $table->timestamps();
        });

        Schema::create('desk_thread_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desk_thread_id')->constrained('desk_threads')->cascadeOnDelete();
            $table->text('body');
            $table->nullableMorphs('user');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desk_thread_messages');
        Schema::dropIfExists('desk_threads');
        Schema::dropIfExists('desk_drafts');
    }
};
