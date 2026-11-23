<?php

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use LaravelLang\Routes\Helpers\Name;

uses(RefreshDatabase::class);

test('the initial schema stores translated content separately from article identity', function () {
    $user = User::factory()->create();
    $article = $user->articles()->create(['slug' => 'keep-this-url', 'is_published' => true]);

    $article->replaceTranslations(['en' => ['title' => '既存の原稿', 'content' => 'Keep **original** content']]);

    $this->assertDatabaseHas('articles', ['id' => $article->id, 'user_id' => $user->id, 'slug' => 'keep-this-url', 'is_published' => true]);
    $this->assertDatabaseHas('article_translations', ['article_id' => $article->id, 'locale' => 'en', 'title' => '既存の原稿', 'content' => 'Keep **original** content']);
    expect(Schema::hasColumn('articles', 'title'))->toBeFalse();
    expect(Schema::hasColumn('articles', 'content'))->toBeFalse();
});

test('an article saves both languages and changes display with the session locale', function () {
    $user = User::factory()->create();
    $translations = ['en' => ['title' => 'Hello', 'content' => 'English body'], 'ja' => ['title' => 'こんにちは', 'content' => '日本語本文']];

    $this->actingAs($user)->post(route('articles.store'), ['translations' => $translations])->assertSessionHasNoErrors();

    $article = $user->articles()->sole();
    $this->assertDatabaseCount('article_translations', 2);
    $this->withSession([Name::session() => 'ja'])->get(route('articles.show', $article))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('article.title', 'こんにちは')->where('article.content', '日本語本文')->where('article.resolvedLocale', 'ja'));
    $this->withSession([Name::session() => 'en'])->get(route('articles.show', $article))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('article.title', 'Hello')->where('article.content', 'English body'));
});

test('untranslated articles use the fallback language and retain empty editing fields', function () {
    $user = User::factory()->create();
    $article = $user->articles()->create(['is_published' => true]);
    $article->replaceTranslations(['en' => ['title' => 'English', 'content' => 'Body']]);

    $this->withSession([Name::session() => 'ja'])->get(route('public.articles.show', $article->slug))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('article.title', 'English')->where('article.resolvedLocale', 'en'));
    $this->actingAs($user)->get(route('articles.edit', $article))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('article.translations.en.title', 'English')->missing('article.translations.ja'));
});

test('a Japanese only article can be published and deleting one translation preserves the other', function () {
    $user = User::factory()->create();
    $article = $user->articles()->create();
    $article->replaceTranslations(['en' => ['title' => 'English', 'content' => 'Body'], 'ja' => ['title' => '日本語', 'content' => '本文']]);

    $this->actingAs($user)->delete(route('articles.translations.destroy', [$article, 'en']), ['edit_version' => $article->fresh()->edit_version])->assertSessionHasNoErrors();
    $this->patch(route('articles.update', $article), ['edit_version' => $article->fresh()->edit_version, 'translations' => ['ja' => ['title' => '日本語', 'content' => '本文']], 'is_published' => true])->assertSessionHasNoErrors();

    $this->assertSoftDeleted('article_translations', ['article_id' => $article->id, 'locale' => 'en']);
    $this->withSession([Name::session() => 'en'])->get(route('public.articles.show', $article->slug))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('article.title', '日本語')->where('article.resolvedLocale', 'ja'));
});

test('empty translations content without title and unsupported languages are rejected without modifying the article', function (array $translations, string $error) {
    $user = User::factory()->create();
    $article = $user->articles()->create();
    $article->replaceTranslations(['en' => ['title' => 'Keep', 'content' => 'Keep body']]);

    $this->actingAs($user)->patch(route('articles.update', $article), ['edit_version' => $article->fresh()->edit_version, 'translations' => $translations])->assertSessionHasErrors($error);

    $this->assertDatabaseHas('article_translations', ['article_id' => $article->id, 'title' => 'Keep', 'content' => 'Keep body']);
})->with([
    [['en' => ['title' => '', 'content' => '']], 'translations.en.title'],
    [['ja' => ['title' => '', 'content' => '本文']], 'translations.ja.title'],
    [['fr' => ['title' => 'Bonjour', 'content' => 'Texte']], 'translations'],
]);

test('changing locale uses the existing session key and rejects unsupported values', function () {
    $this->from(route('home'))->post(route('locale.update'), ['locale' => 'ja'])->assertRedirect(route('home'))->assertSessionHas(Name::session(), 'ja');
    $this->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale')->assertSessionHas(Name::session(), 'ja');
});

test('sandbox copies every translation without publishing or changing originals', function () {
    config(['app.demo_mode' => true]);
    $this->seed();
    $original = Article::where('slug', 'showcase-markdown')->sole();

    $this->post(route('sandbox.start'))->assertRedirect(route('articles.index'));

    $copy = auth()->user()->articles()->whereRelation('translations', 'title', $original->translations->firstWhere('locale', 'en')->title)->sole();
    expect($copy->translations->pluck('locale')->sort()->values()->all())->toBe(['en', 'ja']);
    expect($copy->is_published)->toBeFalse();
    expect($copy->user_id)->not->toBe($original->user_id);
});
