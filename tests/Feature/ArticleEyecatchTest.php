<?php

use App\Actions\Zenn\ParseZennArticle;
use App\Actions\Zenn\RenderZennArticle;
use App\Exceptions\ZennImportException;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

test('owners can set a cover filename and retrieve its public URL without exposing sync metadata', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('articles.store'), [
        'translations' => ['ja' => ['title' => 'Cover story', 'content' => 'Body']],
        'slug' => 'cover-story', 'eyecatch' => '/images/cover.png', 'is_published' => true,
    ])->assertRedirect();

    $article = $user->articles()->sole();
    expect($article->zenn_frontmatter['eyecatch'])->toBe('cover.png');
    $this->get(route('articles.edit', $article))->assertInertia(fn (Assert $page) => $page->where('article.eyecatch', 'cover.png'));
    $this->get(route('public.articles.index'))->assertInertia(fn (Assert $page) => $page
        ->where('articles.data.0.eyecatch_url', '/images/cover.png')->missing('articles.data.0.zenn_frontmatter'));
    $this->get(route('public.articles.show', $article->slug))->assertInertia(fn (Assert $page) => $page
        ->where('article.eyecatch_url', '/images/cover.png')->missing('article.zenn_frontmatter'));
    $this->get(route('articles.index'))->assertInertia(fn (Assert $page) => $page->where('articles.data.0.eyecatch_url', '/images/cover.png'));
    $this->get(route('articles.show', $article))->assertInertia(fn (Assert $page) => $page->where('article.eyecatch_url', '/images/cover.png'));
    $parsed = app(ParseZennArticle::class)(app(RenderZennArticle::class)($article->fresh()));
    expect($parsed['frontmatter']['eyecatch'])->toBe('cover.png');
});

test('changing a cover preserves other metadata and rejects stale edits', function () {
    $user = User::factory()->create();
    $article = $user->articles()->create(['title' => 'Title', 'eyecatch' => 'old.png', 'topics' => ['github']]);
    $version = $article->fresh()->edit_version;

    $this->actingAs($user)->patch(route('articles.update', $article), [
        'title' => 'Title', 'edit_version' => $version, 'eyecatch' => 'new.webp',
    ])->assertRedirect();

    expect($article->fresh()->eyecatch)->toBe('new.webp');
    expect($article->fresh()->topics)->toBe(['github']);
    expect($article->fresh()->edit_version)->toBeGreaterThan($version);
    $this->patch(route('articles.update', $article), [
        'title' => 'Title', 'edit_version' => $version, 'eyecatch' => 'stale.png',
    ])->assertSessionHasErrors('edit_version');
    expect($article->fresh()->eyecatch)->toBe('new.webp');
});

test('omitting a cover keeps it while clearing it restores the default cover', function () {
    $user = User::factory()->create();
    $article = $user->articles()->create(['title' => 'Title', 'eyecatch' => 'cover.jpg']);

    $this->actingAs($user)->patch(route('articles.update', $article), [
        'title' => 'Updated', 'edit_version' => $article->fresh()->edit_version,
    ])->assertRedirect();
    expect($article->fresh()->eyecatch)->toBe('cover.jpg');

    $this->patch(route('articles.update', $article), [
        'title' => 'Updated', 'edit_version' => $article->fresh()->edit_version, 'eyecatch' => '',
    ])->assertRedirect();

    expect($article->fresh()->eyecatch_url)->toBeNull();
    expect($article->fresh()->zenn_frontmatter)->not->toHaveKey('eyecatch');
});

test('rejects unsafe cover paths from both the editor and imported frontmatter', function (mixed $eyecatch) {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('articles.store'), ['title' => 'Title', 'eyecatch' => $eyecatch])
        ->assertSessionHasErrors('eyecatch');

    expect($user->articles()->count())->toBe(0);
    $markdown = "---\ntitle: Title\npublished: false\neyecatch: ".json_encode($eyecatch)."\n---\n\nBody";
    expect(fn () => app(ParseZennArticle::class)($markdown))->toThrow(ZennImportException::class);
})->with([
    'external URL' => 'https://example.com/cover.png',
    'protocol relative' => '//example.com/cover.png',
    'script URL' => 'javascript:alert(1)',
    'traversal' => '../cover.png',
    'nested traversal' => 'folder/../cover.png',
    'SVG' => 'cover.svg',
    'array' => [['cover.png']],
    'oversized filename' => str_repeat('a', 256).'.png',
]);

test('another user cannot change a cover', function () {
    $article = User::factory()->create()->articles()->create(['title' => 'Private', 'eyecatch' => 'original.png']);

    $this->actingAs(User::factory()->create())->patch(route('articles.update', $article), [
        'title' => 'Changed', 'edit_version' => $article->edit_version, 'eyecatch' => 'changed.png',
    ])->assertForbidden();

    expect($article->fresh()->eyecatch)->toBe('original.png');
});

test('unsafe legacy cover metadata is never emitted as a public image URL', function () {
    $article = User::factory()->create()->articles()->create(['title' => 'Legacy', 'is_published' => true]);
    $article->zenn_frontmatter = ['eyecatch' => 'https://example.com/tracker.png'];
    $article->save();

    $this->get(route('public.articles.show', $article->slug))->assertInertia(fn (Assert $page) => $page->where('article.eyecatch_url', null));
});
