<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the public index lists only published articles from all owners in ascending id order with pagination', function () {
    config(['articles.public_index_enabled' => true]);
    $owner = User::factory()->create();
    $other = User::factory()->create();
    for ($i = 1; $i <= 21; $i++) {
        ($i % 2 === 0 ? $owner : $other)->articles()->create([
            'title' => "Article $i", 'slug' => "article-$i", 'content' => 'Body', 'is_published' => true,
        ]);
    }
    $owner->articles()->create(['title' => 'Draft', 'content' => 'Secret']);

    $this->get(route('public.articles.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('articles/public-index')
            ->has('articles.data', 20)->where('articles.total', 21)
            ->where('articles.data.0', ['title' => 'Article 1', 'slug' => 'article-1'])
            ->where('articles.data.19.slug', 'article-20')
            ->where('articles.current_page', 1));
    $this->get(route('public.articles.index', ['page' => 2]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 1)
            ->where('articles.data.0.slug', 'article-21')->where('articles.current_page', 2));
});

test('the public index supports an empty list', function () {
    config(['articles.public_index_enabled' => true]);
    $this->get(route('public.articles.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('articles/public-index')->has('articles.data', 0));
});

test('disabling the public index returns 404 but keeps published article URLs available', function () {
    config(['articles.public_index_enabled' => false]);
    $owner = User::factory()->create();
    $article = $owner->articles()->create(['title' => 'Public', 'content' => '', 'is_published' => true]);

    $this->get(route('public.articles.index'))->assertNotFound();
    $this->actingAs($owner)->get(route('public.articles.index'))->assertNotFound();
    $this->get(route('public.articles.show', $article->slug))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('publicIndexEnabled', false));

    config(['articles.public_index_enabled' => true]);
    $this->get(route('public.articles.show', $article->slug))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('publicIndexEnabled', true));
});

test('visitors can read a published article by slug with only public article fields', function () {
    $article = User::factory()->create()->articles()->create([
        'title' => 'Public article', 'content' => '<script>alert(1)</script>',
        'slug' => 'public-article', 'is_published' => true,
    ]);
    $assertPublicArticle = fn (Assert $page) => $page->component('articles/public-show')
        ->where('article', [
            'title' => 'Public article', 'content' => '<script>alert(1)</script>', 'slug' => 'public-article',
        ]);

    $this->get(route('public.articles.show', $article->slug))->assertOk()->assertInertia($assertPublicArticle);
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('public.articles.show', $article->slug))->assertOk()->assertInertia($assertPublicArticle);
});

test('draft articles return 404 to guests other users and their owner', function () {
    $owner = User::factory()->create();
    $article = $owner->articles()->create([
        'title' => 'Private draft', 'content' => 'Secret draft body', 'slug' => 'private-draft',
    ]);
    $url = route('public.articles.show', $article->slug);

    $this->get($url)->assertNotFound()->assertDontSee('Secret draft body');
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    $this->actingAs($owner)->get($url)->assertNotFound();
});

test('unknown article slugs return 404', function () {
    $this->get(route('public.articles.show', 'missing-article'))->assertNotFound();
});

test('only the owner receives the edit destination on a public article page', function () {
    $owner = User::factory()->create();
    $article = $owner->articles()->create([
        'title' => 'Public article', 'content' => '', 'is_published' => true,
    ]);
    $url = route('public.articles.show', $article->slug);

    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('auth.user', null)->where('editableArticleId', null));
    $this->actingAs(User::factory()->create())->get($url)
        ->assertInertia(fn (Assert $page) => $page->where('editableArticleId', null));
    $this->actingAs($owner)->get($url)
        ->assertInertia(fn (Assert $page) => $page->where('editableArticleId', $article->id));
});

test('publishing renaming unpublishing and deletion immediately change public availability', function () {
    $owner = User::factory()->create();
    $article = $owner->articles()->create([
        'title' => 'Article', 'content' => 'Body', 'slug' => 'original-slug',
    ]);
    $originalUrl = route('public.articles.show', 'original-slug');
    $renamedUrl = route('public.articles.show', 'renamed-slug');

    $this->actingAs($owner)->patch(route('articles.update', $article), [
        'title' => 'Article', 'content' => 'Body', 'is_published' => true,
    ])->assertRedirect();
    $this->get($originalUrl)->assertOk();

    $this->patch(route('articles.update', $article), [
        'title' => 'Article', 'content' => 'Updated body', 'slug' => 'renamed-slug',
    ])->assertRedirect();
    $this->get($originalUrl)->assertNotFound();
    $this->get($renamedUrl)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('article.content', 'Updated body'));

    $this->patch(route('articles.update', $article), [
        'title' => 'Article', 'is_published' => false,
    ])->assertRedirect();
    $this->get($renamedUrl)->assertNotFound();
    expect($article->fresh()->is_published)->toBeFalse();

    $this->patch(route('articles.update', $article), [
        'title' => 'Article', 'is_published' => true,
    ])->assertRedirect();
    $this->get($renamedUrl)->assertOk();
    $this->delete(route('articles.destroy', $article))->assertRedirect();
    $this->assertModelMissing($article);
    $this->get($renamedUrl)->assertNotFound();
});

test('public readers cannot edit update or delete the article', function () {
    $article = User::factory()->create()->articles()->create([
        'title' => 'Public', 'content' => 'Original', 'slug' => 'public', 'is_published' => true,
    ]);
    $this->get(route('articles.edit', $article))->assertRedirect(route('login'));
    $this->patch(route('articles.update', $article), ['title' => 'Changed'])->assertRedirect(route('login'));
    $this->delete(route('articles.destroy', $article))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create());
    $this->get(route('articles.edit', $article))->assertForbidden();
    $this->patch(route('articles.update', $article), ['title' => 'Changed'])->assertForbidden();
    $this->delete(route('articles.destroy', $article))->assertForbidden();
    expect($article->fresh())->title->toBe('Public')->content->toBe('Original');
});
