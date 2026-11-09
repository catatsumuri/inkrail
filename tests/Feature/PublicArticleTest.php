<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

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
