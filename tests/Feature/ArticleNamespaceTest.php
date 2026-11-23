<?php

use App\Actions\Zenn\ParseZennArticle;
use App\Actions\Zenn\RenderZennArticle;
use App\Exceptions\ZennImportException;
use App\Models\ArticleNamespace;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('articles may be ungrouped and deleting a namespace preserves its articles', function () {
    $article = User::factory()->create()->articles()->create(['title' => 'Keep me']);
    expect($article->namespace)->toBeNull();
    $namespace = ArticleNamespace::factory()->create();
    $article->namespace()->associate($namespace);
    $article->save();
    expect($namespace->articles()->sole()->is($article))->toBeTrue();

    $namespace->delete();

    $this->assertModelExists($article);
    expect($article->fresh()->namespace)->toBeNull();
});

test('renders the current namespace slug in frontmatter instead of stale imported metadata', function () {
    $article = User::factory()->create()->articles()->create(['title' => 'Title', 'is_published' => false]);
    $article->replaceTranslations(['ja' => ['title' => '日本語', 'content' => '本文']]);
    $article->namespace()->associate(ArticleNamespace::factory()->create(['slug' => 'guides']));
    $article->zenn_frontmatter = ['namespace' => 'stale'];
    $article->save();

    $markdown = app(RenderZennArticle::class)($article);

    expect(app(ParseZennArticle::class)($markdown)['frontmatter']['namespace'])->toBe('guides');
    $article->namespace()->dissociate();
    expect(app(RenderZennArticle::class)($article))->not->toContain('namespace:');
});

test('rejects invalid namespace frontmatter', function (string $value) {
    $markdown = "---\ntitle: Article\npublished: false\nnamespace: {$value}\n---\n\nBody\n";

    expect(fn () => app(ParseZennArticle::class)($markdown))->toThrow(ZennImportException::class);
})->with(['array' => '[guides]', 'boolean' => 'true', 'path' => '../guides', 'empty' => '""']);
