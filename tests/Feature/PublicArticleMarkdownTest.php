<?php

use App\Models\ArticleNamespace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('markdown download preserves the exact source and remains independent of the public index', function () {
    config(['articles.markdown_download_enabled' => true, 'articles.public_index_enabled' => false]);
    $content = "# Title\r\n\n:::message\n日本語 & <b>raw</b>\n:::\n";
    $article = User::factory()->create()->articles()->create(['title' => 'Title', 'slug' => 'source', 'content' => $content, 'is_published' => true]);
    $this->get('/p/source.md')->assertOk()->assertHeader('Content-Disposition', 'inline; filename=source.md')
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')->assertStreamedContent($content);
    $this->get('/p/source')->assertOk()->assertInertia(fn (Assert $page) => $page->where('markdownDownloadEnabled', true));
    config(['articles.markdown_download_enabled' => false]);
    $this->get('/p/source.md')->assertNotFound();
    $this->get('/p/source')->assertOk()->assertInertia(fn (Assert $page) => $page->where('markdownDownloadEnabled', false));
});

test('markdown downloads enforce public visibility and reject missing articles', function () {
    config(['articles.markdown_download_enabled' => true]);
    $owner = User::factory()->create();
    $draft = $owner->articles()->create(['title' => 'Draft', 'content' => 'Secret']);
    $future = $owner->articles()->create(['title' => 'Future', 'is_published' => true, 'published_at' => now()->addDay()]);
    foreach ([$draft->slug, $future->slug, 'missing'] as $slug) {
        $this->actingAs($owner)->get("/p/$slug.md")->assertNotFound();
    }
});

test('markdown downloads resolve translations and validate language', function () {
    config(['articles.markdown_download_enabled' => true, 'app.locale' => 'en']);
    $article = User::factory()->create()->articles()->create(['title' => 'English', 'content' => 'English source', 'is_published' => true]);
    $article->replaceTranslations(['ja' => ['title' => '日本語', 'content' => '日本語の本文']]);
    $this->get("/p/{$article->slug}.md?lang=ja")->assertStreamedContent('日本語の本文');
    $this->get("/p/{$article->slug}.md?lang=en")->assertStreamedContent('English source');
    $this->get("/p/{$article->slug}.md?lang=fr")->assertSessionHasErrors('lang');
    $article->translations()->where('locale', 'ja')->delete();
    $this->get("/p/{$article->slug}.md?lang=ja")->assertStreamedContent('English source');
});

test('markdown downloads exclude sandbox and respect demo ownership', function () {
    config(['articles.markdown_download_enabled' => true, 'app.demo_mode' => true]);
    $owner = User::factory()->create(['is_showcase_owner' => true]);
    $other = User::factory()->create();
    $otherArticle = $other->articles()->create(['title' => 'Other', 'is_published' => true]);
    $this->get("/p/{$otherArticle->slug}.md")->assertNotFound();
    $sandbox = ArticleNamespace::query()->firstOrCreate(['slug' => 'sandbox'], ['name' => 'Sandbox']);
    $article = $owner->articles()->make(['title' => 'Sandbox', 'is_published' => true]);
    $article->namespace()->associate($sandbox);
    $article->save();
    $this->get("/p/{$article->slug}.md")->assertNotFound();
    $public = $owner->articles()->create(['title' => 'Public', 'content' => '', 'is_published' => true]);
    $this->get("/p/{$public->slug}.md")->assertOk()->assertStreamedContent('');
});
