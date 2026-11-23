<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_translation_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('en_revision_id')->constrained('article_revisions')->cascadeOnDelete();
            $table->foreignId('ja_revision_id')->constrained('article_revisions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['article_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_translation_reviews');
    }
};
