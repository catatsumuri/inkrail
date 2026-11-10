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
        Schema::create('article_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('title');
            $table->longText('content');
            $table->timestamps();
            $table->unique(['article_id', 'locale']);
        });

        DB::table('articles')->orderBy('id')->chunkById(100, function ($articles): void {
            foreach ($articles as $article) {
                DB::table('article_translations')->insert([
                    'article_id' => $article->id,
                    'locale' => 'en',
                    'title' => $article->title,
                    'content' => $article->content,
                    'created_at' => $article->created_at,
                    'updated_at' => $article->updated_at,
                ]);
            }
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['title', 'content']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('title')->default('');
            $table->longText('content')->default('');
        });

        DB::table('articles')->orderBy('id')->chunkById(100, function ($articles): void {
            foreach ($articles as $article) {
                $translation = DB::table('article_translations')->where('article_id', $article->id)
                    ->orderByRaw("CASE WHEN locale = 'en' THEN 0 ELSE 1 END")->orderBy('locale')->first();
                if ($translation !== null) {
                    DB::table('articles')->where('id', $article->id)->update([
                        'title' => $translation->title, 'content' => $translation->content,
                    ]);
                }
            }
        });

        Schema::dropIfExists('article_translations');
    }
};
