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
