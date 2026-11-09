<?php

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('articles require authentication and verified email', function () {
    $this->get(route('articles.index'))->assertRedirect(route('login'));
    $this->post(route('articles.store'), [])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('articles.index'))->assertRedirect(route('verification.notice'));
});

test('an owner can create read update and delete an article', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);
    $this->get(route('articles.create'))->assertOk();
    $this->post(route('articles.store'), [
        'title' => '原稿', 'content' => '', 'user_id' => $other->id,
    ])->assertRedirect();
    $article = Article::query()->sole();
    expect($article->user_id)->toBe($user->id);
    expect($article->content)->toBe('');
    $this->get(route('articles.show', $article))
        ->assertInertia(fn (Assert $page) => $page->component('articles/show')->where('article.title', '原稿'));
    $this->get(route('articles.edit', $article))->assertOk();
    $this->patch(route('articles.update', $article), [
        'title' => '更新', 'content' => '<script>alert(1)</script>', 'user_id' => $other->id,
    ])->assertRedirect(route('articles.show', $article));
    expect($article->fresh())->title->toBe('更新')->user_id->toBe($user->id)
        ->content->toBe('<script>alert(1)</script>');
    $this->delete(route('articles.destroy', $article))->assertRedirect(route('articles.index'));
    $this->assertModelMissing($article);
});

test('the list only contains the current users articles and is paginated', function () {
    $user = User::factory()->create();
    for ($i = 0; $i < 21; $i++) {
        $user->articles()->create(['title' => "Article $i", 'content' => '']);
    }
    User::factory()->create()->articles()->create(['title' => 'Private', 'content' => '']);
    $this->actingAs($user)->get(route('articles.index'))
        ->assertInertia(fn (Assert $page) => $page->component('articles/index')
            ->has('articles.data', 20)->where('articles.total', 21));
});

test('other users cannot read edit update or delete an article', function () {
    $article = User::factory()->create()->articles()->create(['title' => 'Private', 'content' => 'secret']);
    $this->actingAs(User::factory()->create());
    $this->get(route('articles.show', $article))->assertForbidden();
    $this->get(route('articles.edit', $article))->assertForbidden();
    $this->patch(route('articles.update', $article), ['title' => 'stolen'])->assertForbidden();
    $this->delete(route('articles.destroy', $article))->assertForbidden();
    expect($article->fresh())->title->toBe('Private')->content->toBe('secret');
});

test('title is required and content must be text', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('articles.store'), ['title' => ''])
        ->assertSessionHasErrors('title');
    $this->post(route('articles.store'), ['title' => str_repeat('a', 256), 'content' => []])
        ->assertSessionHasErrors(['title', 'content']);
    expect($user->articles()->count())->toBe(0);
});

test('new articles receive a unique slug and are drafts by default', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('articles.store'), ['title' => '同じタイトル'])->assertRedirect();
    $this->post(route('articles.store'), ['title' => '同じタイトル', 'slug' => ''])->assertRedirect();

    $articles = $user->articles()->get();
    expect($articles)->toHaveCount(2);
    expect($articles->pluck('slug')->unique())->toHaveCount(2);
    foreach ($articles as $article) {
        expect($article->slug)->toMatch('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/');
        expect($article->is_published)->toBeFalse();
    }
});

test('owners can set a slug publish and return an article to draft', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('articles.store'), [
        'title' => '公開する記事', 'slug' => 'first-article', 'is_published' => '1',
    ])->assertRedirect();

    $article = $user->articles()->sole();
    expect($article->slug)->toBe('first-article');
    expect($article->is_published)->toBeTrue();
    $this->get(route('articles.edit', $article))
        ->assertInertia(fn (Assert $page) => $page
            ->where('article.slug', 'first-article')->where('article.is_published', true));
    $this->get(route('articles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('articles.data.0.slug', 'first-article')->where('articles.data.0.is_published', true));

    $this->patch(route('articles.update', $article), ['title' => '本文だけの更新'])->assertRedirect();
    expect($article->fresh())->slug->toBe('first-article')->is_published->toBeTrue();

    $this->patch(route('articles.update', $article), [
        'title' => '編集した記事', 'slug' => 'first-article', 'is_published' => '0',
    ])->assertRedirect();
    expect($article->fresh()->is_published)->toBeFalse();

    $this->patch(route('articles.update', $article), [
        'title' => '更新', 'slug' => '',
    ])->assertRedirect();
    expect($article->fresh()->slug)->toBe('first-article');

    $this->patch(route('articles.update', $article), [
        'title' => '更新', 'slug' => 'renamed-article',
    ])->assertRedirect();
    expect($article->fresh()->slug)->toBe('renamed-article');
});

test('article slugs are unique across owners and rejected updates preserve existing data', function () {
    $other = User::factory()->create()->articles()->create([
        'title' => 'Other', 'slug' => 'taken-slug', 'content' => '',
    ]);
    $user = User::factory()->create();
    $article = $user->articles()->create(['title' => 'Mine', 'slug' => 'my-slug', 'content' => '']);
    $this->actingAs($user)->post(route('articles.store'), [
        'title' => 'Duplicate', 'slug' => $other->slug,
    ])->assertSessionHasErrors('slug');
    $this->patch(route('articles.update', $article), [
        'title' => 'Duplicate', 'slug' => $other->slug, 'is_published' => true,
    ])->assertSessionHasErrors('slug');
    expect($article->fresh())->slug->toBe('my-slug')->is_published->toBeFalse();
    expect($user->articles()->count())->toBe(1);
});

test('invalid slug formats are rejected', function (string $slug) {
    $this->actingAs(User::factory()->create())->post(route('articles.store'), [
        'title' => 'Article', 'slug' => $slug,
    ])->assertSessionHasErrors('slug');
    expect(Article::count())->toBe(0);
})->with(['UPPERCASE', 'has space', '日本語', 'has/slash', 'has_underscore', '-leading', 'trailing-', 'two--hyphens', str_repeat('a', 256)]);

test('invalid publication flags are rejected', function () {
    $this->actingAs(User::factory()->create())->post(route('articles.store'), [
        'title' => 'Article', 'is_published' => 'yes',
    ])->assertSessionHasErrors('is_published');
    expect(Article::count())->toBe(0);
});

test('publication metadata does not allow other users to read or modify articles', function () {
    $article = User::factory()->create()->articles()->create([
        'title' => 'Owner only', 'content' => '', 'slug' => 'owner-only', 'is_published' => true,
    ]);
    $this->get(route('articles.show', $article))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('articles.show', $article))->assertForbidden();
    $this->patch(route('articles.update', $article), [
        'title' => 'Changed', 'slug' => 'changed', 'is_published' => false,
    ])->assertForbidden();
    expect($article->fresh())->slug->toBe('owner-only')->is_published->toBeTrue();
});

test('article samples can be seeded repeatedly with valid publication metadata', function () {
    $this->seed();
    $this->seed();

    $articles = User::where('email', 'test@example.com')->sole()->articles()->get();
    expect($articles)->toHaveCount(2);
    expect($articles->pluck('slug')->unique())->toHaveCount(2);
    expect($articles->firstWhere('title', 'InkRailへようこそ')->is_published)->toBeTrue();
    expect($articles->firstWhere('title', '記事の編集と管理')->is_published)->toBeFalse();
    foreach ($articles as $article) {
        expect($article->slug)->not->toBeEmpty();
    }
});
