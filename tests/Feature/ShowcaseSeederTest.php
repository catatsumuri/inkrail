<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('legacy welcome articles retain their URL and draft state when translated', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $article = $user->articles()->create([
        'title' => 'InkRailへようこそ',
        'content' => "InkRailは、考えやアイデアを書き留めるための場所です。\n\nまずは新しい記事を作成して、今日の気づきを残してみましょう。",
        'slug' => 'original-welcome', 'is_published' => false,
    ]);

    $this->seed();
    $this->seed();

    expect($article->fresh())->title->toBe('Welcome to InkRail')->slug->toBe('original-welcome')->is_published->toBeFalse();
    expect($user->articles()->count())->toBe(8);
});

test('syntax showcase articles are published at stable URLs', function (string $syntax, string $example) {
    $this->seed();

    $this->get(route('public.articles.show', 'showcase-'.$syntax))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('articles/public-show')
            ->where('article.slug', 'showcase-'.$syntax)
            ->where('article.content', fn (string $content): bool => str_contains($content, $example)));
})->with([
    ['markdown', '**bold**'],
    ['zenn', ':::message alert'],
    ['mintlify', '<CardGroup cols="2">'],
    ['gfm', '> [!IMPORTANT]'],
    ['extensions', '```chart:radar'],
    ['inertia', "Inertia::render('articles/public-show'"],
]);

test('reseeding preserves edited showcase articles and unrelated user articles', function () {
    $this->seed();
    $user = User::where('email', 'test@example.com')->sole();
    $showcase = $user->articles()->where('slug', 'showcase-markdown')->sole();
    $showcase->update(['title' => 'My edited guide', 'content' => 'My notes', 'is_published' => false]);
    $personal = $user->articles()->create(['title' => 'Personal draft', 'content' => 'Keep this']);

    $this->seed();

    expect($showcase->fresh())->title->toBe('My edited guide')->content->toBe('My notes')->is_published->toBeFalse();
    expect($personal->fresh())->content->toBe('Keep this')->is_published->toBeFalse();
    expect($user->articles()->count())->toBe(9);
    $this->get(route('public.articles.show', 'showcase-markdown'))->assertNotFound();
});

test('unchanged Japanese guides become English without changing identity or publication', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $titles = json_decode(file_get_contents(database_path('seeders/showcase/ja/titles.json')), true);
    $article = $user->articles()->create([
        'title' => $titles['zenn'],
        'content' => file_get_contents(database_path('seeders/showcase/ja/zenn.md')),
        'slug' => 'showcase-zenn', 'is_published' => false,
    ]);

    $this->seed();
    $this->seed();

    expect($article->fresh())->title->toBe('Zenn flavor — Messages and disclosures')
        ->slug->toBe('showcase-zenn')->is_published->toBeFalse();
    expect($article->fresh()->content)->toContain('Drafts are visible only to their owner.');
    expect($user->articles()->count())->toBe(8);
});
