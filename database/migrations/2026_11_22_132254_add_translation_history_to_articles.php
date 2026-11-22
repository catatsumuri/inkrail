<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->unsignedInteger('edit_version')->default(0);
        });
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
        Schema::table('article_translations', function (Blueprint $table): void {
            $table->foreignId('current_revision_id')->nullable()->constrained('article_revisions')->nullOnDelete();
            $table->softDeletes();
        });
        Schema::create('article_translation_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('en_revision_id')->constrained('article_revisions')->cascadeOnDelete();
            $table->foreignId('ja_revision_id')->constrained('article_revisions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['article_id', 'id']);
        });
        DB::table('article_translations')->orderBy('id')->chunkById(100, function ($translations): void {
            foreach ($translations as $translation) {
                $revisionId = DB::table('article_revisions')->insertGetId([
                    'article_id' => $translation->article_id, 'locale' => $translation->locale,
                    'title' => $translation->title, 'content' => $translation->content,
                    'created_at' => $translation->updated_at ?? now(),
                ]);
                DB::table('article_translations')->where('id', $translation->id)->update(['current_revision_id' => $revisionId]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_translation_reviews');
        Schema::table('article_translations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_revision_id');
            $table->dropSoftDeletes();
        });
        Schema::dropIfExists('article_revisions');
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('edit_version');
        });
    }
};
