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
        Schema::create('article_github_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('repository');
            $table->string('branch');
            $table->string('path');
            $table->string('remote_sha', 40);
            $table->string('local_fingerprint', 64);
            $table->json('frontmatter')->nullable();
            $table->unique(['repository', 'branch', 'path']);
            $table->timestamps();
        });
        $database = DB::table('article_namespaces');
        $namespaceId = (clone $database)->where('slug', 'sandbox')->value('id');
        if ($namespaceId === null) {
            $namespaceId = $database->insertGetId(['slug' => 'sandbox', 'name' => 'Sandbox', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('articles')->where('namespace_id', $namespaceId)->update(['is_published' => false, 'published_at' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_github_sources');
    }
};
