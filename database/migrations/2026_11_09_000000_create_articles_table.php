<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('namespace_id')->nullable()->constrained('article_namespaces')->nullOnDelete();
            $table->string('slug')->unique();
            $table->boolean('is_published')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->index(['is_published', 'published_at']);
            $table->unsignedInteger('edit_version')->default(0);
            $table->uuid('sync_id')->nullable()->unique();
            $table->string('zenn_repository')->nullable();
            $table->string('zenn_branch')->nullable();
            $table->string('zenn_path')->nullable();
            $table->string('zenn_sha', 40)->nullable();
            $table->string('zenn_local_sha', 40)->nullable();
            $table->json('zenn_frontmatter')->nullable();
            $table->timestamps();
            $table->unique(['zenn_repository', 'zenn_branch', 'zenn_path'], 'articles_zenn_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
