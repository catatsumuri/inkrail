<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('normal seeding creates a login user without sample articles', function () {
    config(['app.demo_mode' => false]);

    $this->seed();
    $this->seed();

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'is_showcase_owner' => false]);
    $this->assertDatabaseCount('articles', 0);
    $this->assertDatabaseCount('article_translations', 0);
});

test('reseeding refreshes unchanged welcome translations while preserving their URL and draft state', function () {
    config(['app.demo_mode' => true]);
    $user = User::factory()->create(['email' => 'test@example.com']);
    $article = $user->articles()->create(['slug' => 'original-welcome', 'is_published' => false]);
    $article->replaceTranslations([
        'en' => ['title' => 'Welcome to InkRail', 'content' => "InkRail is a place to capture your thoughts and ideas.\n\nCreate an article and write down something you learned today."],
        'ja' => ['title' => 'InkRailへようこそ', 'content' => "InkRailは、考えやアイデアを書き留めるための場所です。\n\nまずは新しい記事を作成して、今日の気づきを残してみましょう。"],
    ]);

    $this->seed();
    $this->seed();

    expect($article->fresh())->slug->toBe('original-welcome')->is_published->toBeFalse();
    expect($article->fresh()->translations->firstWhere('locale', 'en')->content)->toBe("## What is this?\n\n`InkRail` is a place to capture your thoughts and ideas.\n\nCreate an article and write down something you learned today.");
    expect($article->fresh()->translations->firstWhere('locale', 'ja')->content)->toBe("## これは何ですか\n\n`InkRail`は、考えやアイデアを書き留めるための場所です。\n\nまずは新しい記事を作成して、今日の気づきを残してみましょう。");
    $this->assertDatabaseCount('articles', 8);
});

test('reseeding preserves edited welcome bodies even when their titles are unchanged', function () {
    config(['app.demo_mode' => true]);
    $this->seed();
    $article = User::where('email', 'test@example.com')->sole()->articles()->where('slug', 'welcome-to-inkrail')->sole();
    $article->translations()->where('locale', 'en')->update(['content' => 'My English introduction']);
    $article->translations()->where('locale', 'ja')->update(['content' => '自分で編集した紹介文']);

    $this->seed();

    expect($article->fresh()->translations->firstWhere('locale', 'en')->content)->toBe('My English introduction');
    expect($article->fresh()->translations->firstWhere('locale', 'ja')->content)->toBe('自分で編集した紹介文');
    $this->assertDatabaseCount('articles', 8);
});

test('legacy welcome articles retain their URL and draft state when translated', function () {
    config(['app.demo_mode' => true]);
    app()->setLocale('en');
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
    config(['app.demo_mode' => true]);
    app()->setLocale('en');
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
    config(['app.demo_mode' => true]);
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
    config(['app.demo_mode' => true]);
    app()->setLocale('en');
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

test('sample articles receive bundled covers without replacing custom covers', function () {
    config(['app.demo_mode' => true]);
    $this->seed();
    $articles = User::where('email', 'test@example.com')->sole()->articles()->get();
    expect($articles)->toHaveCount(8);
    foreach ($articles as $article) {
        expect($article->eyecatch)->not->toBeNull();
        expect(is_file(public_path('images/'.$article->eyecatch)))->toBeTrue();
    }
    $article = $articles->first();
    $article->update(['eyecatch' => 'custom.png']);
    $this->seed();
    expect($article->fresh()->eyecatch)->toBe('custom.png');
});

test('enabling demo mode after normal seeding establishes the showcase owner', function () {
    config(['app.demo_mode' => false]);
    $this->seed();
    $user = User::where('email', 'test@example.com')->sole();
    expect($user->is_showcase_owner)->toBeFalse();
    config(['app.demo_mode' => true]);
    $this->seed();
    expect($user->fresh()->is_showcase_owner)->toBeTrue();
    expect($user->articles()->count())->toBe(8);
    $this->post(route('sandbox.start'))->assertRedirect(route('articles.index'));
    expect(auth()->user()->is_sandbox)->toBeTrue();
});
