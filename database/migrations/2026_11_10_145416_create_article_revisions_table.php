<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('title');
            $table->longText('content');
            $table->boolean('is_deleted')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restored_from_id')->nullable()->constrained('article_revisions')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['article_id', 'locale', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_revisions');
    }
};
