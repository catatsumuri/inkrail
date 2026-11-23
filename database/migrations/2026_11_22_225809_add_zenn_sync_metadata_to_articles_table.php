<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->uuid('sync_id')->nullable()->unique();
            $table->string('zenn_repository')->nullable();
            $table->string('zenn_branch')->nullable();
            $table->string('zenn_path')->nullable();
            $table->string('zenn_sha', 40)->nullable();
            $table->unique(['zenn_repository', 'zenn_branch', 'zenn_path'], 'articles_zenn_target_unique');
        });

        DB::table('articles')->orderBy('id')->chunkById(100, function ($articles): void {
            foreach ($articles as $article) {
                DB::table('articles')->where('id', $article->id)->update(['sync_id' => (string) Str::uuid()]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropUnique('articles_zenn_target_unique');
            $table->dropUnique(['sync_id']);
            $table->dropColumn(['sync_id', 'zenn_repository', 'zenn_branch', 'zenn_path', 'zenn_sha']);
        });
    }
};
