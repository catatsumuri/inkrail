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
        Schema::table('articles', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
            $table->boolean('is_published')->default(false);
        });

        DB::table('articles')->select('id')->orderBy('id')->chunkById(100, function ($articles): void {
            foreach ($articles as $article) {
                DB::table('articles')->where('id', $article->id)->update(['slug' => 'article-'.$article->id]);
            }
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'is_published']);
        });
    }
};
