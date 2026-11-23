<?php

use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config(['articles.image_disk' => 'public']);
});

function storedArticleImage(DateTimeInterface $touchedAt): ArticleImage
{
    $image = ArticleImage::factory()->create(['updated_at' => $touchedAt]);
    Storage::disk('public')->put($image->storagePath(), 'bytes');

    return $image;
}

test('deletes old images that no article references', function () {
    $image = storedArticleImage(now()->subDays(8));

    $this->artisan('article-images:prune')->assertSuccessful();

    $this->assertModelMissing($image);
    Storage::disk('public')->assertMissing($image->storagePath());
});

test('keeps images that were touched recently', function () {
    $image = storedArticleImage(now()->subDays(2));

    $this->artisan('article-images:prune')->assertSuccessful();

    $this->assertModelExists($image);
    Storage::disk('public')->assertExists($image->storagePath());
});

test('keeps images that an article translation references', function () {
    $image = storedArticleImage(now()->subDays(8));
    $article = User::factory()->create()->articles()->create(['slug' => 'with-image']);
    $article->replaceTranslations(['ja' => ['title' => '画像付き', 'content' => "![図]({$image->url()})"]]);

    $this->artisan('article-images:prune')->assertSuccessful();

    $this->assertModelExists($image);
    Storage::disk('public')->assertExists($image->storagePath());
});

test('keeps images that only an earlier revision references', function () {
    $image = storedArticleImage(now()->subDays(8));
    $article = User::factory()->create()->articles()->create(['slug' => 'history']);
    $article->replaceTranslations(['ja' => ['title' => '履歴', 'content' => "![図]({$image->url()})"]]);
    $article->replaceTranslations(['ja' => ['title' => '履歴', 'content' => '画像を消した本文']]);

    $this->artisan('article-images:prune')->assertSuccessful();

    expect(Article::query()->find($article->id)->content)->toBe('画像を消した本文');
    $this->assertModelExists($image);
});

test('keeps images used only as article covers', function (bool $withPrefix) {
    $image = storedArticleImage(now()->subDays(8));
    $article = User::factory()->create()->articles()->create(['title' => 'Cover']);
    $article->zenn_frontmatter = ['eyecatch' => $withPrefix ? $image->url() : $image->path];
    $article->save();

    $this->artisan('article-images:prune')->assertSuccessful();

    $this->assertModelExists($image);
    Storage::disk('public')->assertExists($image->storagePath());
})->with([false, true]);
