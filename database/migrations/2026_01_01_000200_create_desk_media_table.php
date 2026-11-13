<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every upload made through the editor, with its generated derivatives.
        Schema::create('desk_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('desk_media')->nullOnDelete();
            $table->string('disk', 64);
            $table->string('path', 500);
            $table->string('url', 500)->index();
            $table->string('name');
            $table->string('mime', 191)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('kind', 16)->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->double('duration')->nullable();
            $table->string('alt', 500)->nullable();
            $table->string('status', 20)->default('ready')->index();
            $table->json('meta')->nullable();
            $table->nullableMorphs('owner');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desk_media');
    }
};
