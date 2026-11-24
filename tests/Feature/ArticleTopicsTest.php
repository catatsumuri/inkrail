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

test('visitors can filter published articles by exact topic and keep the filter across pages', function () {
    config(['articles.public_index_enabled' => true]);
    $this->freezeTime();
    $owner = User::factory()->create();
    for ($i = 1; $i <= 21; $i++) {
        $owner->articles()->create(['title' => "Match $i", 'is_published' => true, 'topics' => ['php', '日本語']]);
    }
    $owner->articles()->create(['title' => 'Other', 'is_published' => true, 'topics' => ['phpstan']]);
    $owner->articles()->create(['title' => 'Draft', 'topics' => ['private', 'php']]);
    $owner->articles()->create(['title' => 'Scheduled', 'is_published' => true, 'published_at' => now()->addDay(), 'topics' => ['scheduled', 'php']]);

    $this->get(route('public.articles.index', ['topic' => 'php']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('selectedTopic', 'php')
            ->where('topics', ['php', 'phpstan', '日本語'])
            ->where('articles.total', 21)->has('articles.data', 20)
            ->where('articles.data.0.topics', ['php', '日本語'])
            ->where('articles.next_page_url', route('public.articles.index', ['topic' => 'php', 'page' => 2])));
    $this->get(route('public.articles.index', ['topic' => 'php', 'page' => 2]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 1)->where('articles.data.0.title', 'Match 21')
            ->where('selectedTopic', 'php')->where('topics', ['php', 'phpstan', '日本語']));
    $this->get(route('public.articles.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('selectedTopic', null)->where('articles.total', 22));
});

test('owners can filter their articles including drafts without exposing other owners topics', function () {
    $owner = User::factory()->create();
    for ($i = 1; $i <= 21; $i++) {
        $owner->articles()->create(['title' => "Draft $i", 'topics' => ['日本語']]);
    }
    $owner->articles()->create(['title' => 'Other topic', 'topics' => ['other']]);
    User::factory()->create()->articles()->create(['title' => 'Private', 'topics' => ['secret', '日本語']]);

    $this->actingAs($owner)->get(route('articles.index', ['topic' => '日本語']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('selectedTopic', '日本語')->where('topics', ['other', '日本語'])
            ->where('articles.total', 21)->has('articles.data', 20)
            ->where('articles.data.0.topics', ['日本語'])
            ->where('articles.next_page_url', route('articles.index', ['topic' => '日本語', 'page' => 2])));
    $this->get(route('articles.index', ['topic' => '日本語', 'page' => 2]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 1)->where('articles.data.0.title', 'Draft 1'));
    $this->get(route('articles.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('selectedTopic', null)->where('articles.total', 22)
            ->where('articles.data.0.topics', ['other']));
});

test('public topic choices respect showcase visibility in demo mode', function () {
    config(['articles.public_index_enabled' => true, 'app.demo_mode' => true]);
    User::factory()->create(['is_showcase_owner' => true])->articles()->create(['title' => 'Showcase', 'is_published' => true, 'topics' => ['showcase']]);
    User::factory()->create()->articles()->create(['title' => 'Visitor', 'is_published' => true, 'topics' => ['visitor', 'showcase']]);

    $this->get(route('public.articles.index', ['topic' => 'showcase']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('topics', ['showcase'])
            ->has('articles.data', 1)->where('articles.data.0.title', 'Showcase'));
});

test('unknown topics return an empty list without changing the available choices', function (string $routeName) {
    config(['articles.public_index_enabled' => true]);
    $owner = User::factory()->create();
    $owner->articles()->create(['title' => 'Article', 'is_published' => true, 'topics' => ['php']]);

    $this->actingAs($owner)->get(route($routeName, ['topic' => "' OR 1=1 --"]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 0)
            ->where('selectedTopic', "' OR 1=1 --")->where('topics', ['php']));
})->with(['public.articles.index', 'articles.index']);

test('article lists reject invalid topic filters', function (string $routeName, mixed $topic) {
    config(['articles.public_index_enabled' => true]);

    $this->actingAs(User::factory()->create())->getJson(route($routeName, ['topic' => $topic]))
        ->assertUnprocessable()->assertJsonValidationErrors('topic');
})->with(['public.articles.index', 'articles.index'])->with([
    'array' => [['php']],
    'too long' => [str_repeat('a', 256)],
]);
