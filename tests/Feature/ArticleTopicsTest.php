<?php

use App\Actions\Zenn\RenderZennArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Yaml\Yaml;

uses(LazilyRefreshDatabase::class);

test('owners can configure topics when creating an article and retrieve them for editing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('articles.store'), [
        'translations' => ['ja' => ['title' => '記事', 'content' => '本文']],
        'topics' => ['zenn', 'github'],
    ])->assertRedirect();

    $article = $user->articles()->sole();
    expect($article->topics)->toBe(['zenn', 'github']);
    $this->get(route('articles.edit', $article))->assertInertia(fn (Assert $page) => $page->where('article.topics', ['zenn', 'github']));
    $markdown = app(RenderZennArticle::class)($article);
    preg_match('/\A---\n(.*?)\n---/s', $markdown, $matches);
    expect(Yaml::parse($matches[1])['topics'])->toBe(['zenn', 'github']);
});

test('editing topics preserves other frontmatter and rejects stale edits', function () {
    $user = User::factory()->create();
    $article = $user->articles()->create(['title' => 'Title']);
    $article->zenn_frontmatter = ['emoji' => '🐱', 'topics' => ['old'], 'custom_field' => 'keep'];
    $article->save();
    $version = $article->fresh()->edit_version;

    $this->actingAs($user)->patch(route('articles.update', $article), [
        'title' => 'Title', 'edit_version' => $version, 'topics' => ['github'],
    ])->assertRedirect();

    expect($article->fresh()->zenn_frontmatter)->toBe(['emoji' => '🐱', 'topics' => ['github'], 'custom_field' => 'keep']);
    expect($article->fresh()->edit_version)->toBeGreaterThan($version);
    $this->patch(route('articles.update', $article), [
        'title' => 'Stale', 'edit_version' => $version, 'topics' => ['stale'],
    ])->assertSessionHasErrors('edit_version');
    expect($article->fresh()->topics)->toBe(['github']);
});

test('omitting topics preserves them while an empty list clears them', function () {
    $user = User::factory()->create();
    $article = $user->articles()->create(['title' => 'Title', 'topics' => ['github']]);
    $this->actingAs($user)->patch(route('articles.update', $article), [
        'title' => 'Updated', 'edit_version' => $article->fresh()->edit_version,
    ])->assertRedirect();
    expect($article->fresh()->topics)->toBe(['github']);

    $this->patch(route('articles.update', $article), [
        'title' => 'Updated', 'edit_version' => $article->fresh()->edit_version, 'topics' => [],
    ])->assertRedirect();

    expect($article->fresh()->topics)->toBe([]);
});

test('rejects invalid topics without creating an article', function (mixed $topics, string $error) {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('articles.store'), ['title' => 'Title', 'topics' => $topics])
        ->assertSessionHasErrors($error);

    expect($user->articles()->count())->toBe(0);
})->with([
    'too many' => [['a', 'b', 'c', 'd', 'e', 'f'], 'topics'],
    'not an array' => ['github', 'topics'],
    'duplicate' => [['github', 'GitHub'], 'topics.0'],
    'empty topic' => [[''], 'topics.0'],
    'nested topic' => [[['github']], 'topics.0'],
    'too long' => [[str_repeat('a', 256)], 'topics.0'],
]);

test('another user cannot modify article topics', function () {
    $article = User::factory()->create()->articles()->create(['title' => 'Private', 'topics' => ['original']]);

    $this->actingAs(User::factory()->create())->patch(route('articles.update', $article), [
        'title' => 'Changed', 'topics' => ['changed'], 'edit_version' => $article->edit_version,
    ])->assertForbidden();

    expect($article->fresh()->topics)->toBe(['original']);
});
