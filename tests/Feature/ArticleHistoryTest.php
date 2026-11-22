<?php

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\ArticleTranslationReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('saving the leading language marks only its counterpart stale until it catches up without manual review', function (string $leading, string $following) {
    $article = bilingualHistoryArticle();
    expect($article->translationStatus()[$leading]['state'])->toBe('reviewed');
    expect($article->translationStatus()[$following]['state'])->toBe('reviewed');
    $article->replaceTranslations([$leading => ['title' => 'Updated source', 'content' => 'First change']]);
    $article->replaceTranslations([$leading => ['title' => 'Updated source', 'content' => 'Second change']]);
    expect($article->translationStatus()[$leading]['state'])->toBe('reviewed');
    expect($article->translationStatus()[$following]['state'])->toBe('other_changed');
    $article->replaceTranslations([$following => ['title' => 'Updated translation', 'content' => 'Caught up']]);
    expect($article->translationStatus()[$leading]['state'])->toBe('reviewed');
    expect($article->translationStatus()[$following]['state'])->toBe('reviewed');
})->with([['ja', 'en'], ['en', 'ja']]);

function bilingualHistoryArticle(): Article
{
    $article = User::factory()->create()->articles()->create(['slug' => 'history', 'is_published' => true]);
    $article->replaceTranslations(['en' => ['title' => 'English', 'content' => 'Before'], 'ja' => ['title' => '日本語', 'content' => '変更前']]);

    return $article->fresh();
}

test('translation saves append snapshots only on changes and preserve omitted languages', function () {
    $article = bilingualHistoryArticle();
    $this->assertDatabaseCount('article_revisions', 2);
    $article->replaceTranslations(['en' => ['title' => 'English', 'content' => 'Before']]);
    $this->assertDatabaseCount('article_revisions', 2);
    $article->replaceTranslations(['en' => ['title' => 'English', 'content' => 'After']]);
    $this->assertDatabaseCount('article_revisions', 3);
    expect($article->fresh()->translations->pluck('locale')->all())->toEqualCanonicalizing(['en', 'ja']);
    expect(ArticleRevision::orderBy('id')->first()->content)->toBe('Before');
});

test('review records a pair and detects updates in either direction', function (string $language, string $other) {
    $article = bilingualHistoryArticle();
    $this->actingAs($article->user)->post(route('articles.review', $article), ['edit_version' => $article->edit_version])->assertSessionHasNoErrors()->assertRedirect();
    expect($article->translationStatus()['en']['state'])->toBe('reviewed');
    expect($article->translationStatus()['ja']['state'])->toBe('reviewed');
    $translation = $article->translations->firstWhere('locale', $language);
    $translation->update(['content' => 'New content']);
    expect($article->translationStatus()[$other]['state'])->toBe('other_changed');
    expect($article->translationStatus()[$language]['state'])->toBe('reviewed');
    $article->translations->firstWhere('locale', $other)->update(['content' => 'Other new content']);
    expect($article->translationStatus()[$language]['state'])->toBe('reviewed');
    expect($article->translationStatus()[$other]['state'])->toBe('reviewed');
    $this->assertDatabaseCount('article_translation_reviews', 3);
})->with([['en', 'ja'], ['ja', 'en']]);

test('missing and deleted translations are distinct and restoration appends a new version', function () {
    $article = bilingualHistoryArticle();
    $original = ArticleRevision::where('article_id', $article->id)->where('locale', 'ja')->sole();
    $this->actingAs($article->user)->delete(route('articles.translations.destroy', [$article, 'ja']), ['edit_version' => $article->edit_version])->assertRedirect()->assertSessionHasNoErrors();
    expect($article->translationStatus()['ja']['state'])->toBe('deleted');
    $this->post(route('articles.review', $article), ['edit_version' => $article->fresh()->edit_version])->assertSessionHasErrors('history');
    $this->post(route('articles.restore', [$article, $original]), ['edit_version' => $article->fresh()->edit_version])->assertRedirect()->assertSessionHasNoErrors();
    expect($article->fresh()->translations->firstWhere('locale', 'ja')->content)->toBe('変更前');
    expect(ArticleRevision::latest('id')->first()->restored_from_id)->toBe($original->id);
    $this->assertDatabaseCount('article_revisions', 4);
    $this->post(route('articles.restore', [$article, $original]), ['edit_version' => $article->fresh()->edit_version])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('article_revisions', 4);
});

test('stale saves reviews deletions and restores preserve the latest data', function () {
    $article = bilingualHistoryArticle();
    $oldVersion = $article->edit_version;
    $revision = ArticleRevision::where('article_id', $article->id)->first();
    $article->translations->first()->update(['content' => 'Latest']);
    $this->actingAs($article->user)->patch(route('articles.update', $article), ['edit_version' => $oldVersion, 'title' => 'Overwrite'])->assertSessionHasErrors('edit_version');
    $this->post(route('articles.review', $article), ['edit_version' => $oldVersion])->assertSessionHasErrors('history');
    $this->delete(route('articles.translations.destroy', [$article, 'ja']), ['edit_version' => $oldVersion])->assertSessionHasErrors('history');
    $this->post(route('articles.restore', [$article, $revision]), ['edit_version' => $oldVersion])->assertSessionHasErrors('history');
    $this->assertDatabaseCount('article_revisions', 3);
    $this->assertDatabaseCount('article_translation_reviews', 1);
});

test('empty bodies are valid and the last language cannot be deleted', function () {
    $article = User::factory()->create()->articles()->create();
    expect($article->translationStatus()['ja']['state'])->toBe('missing');
    $article->replaceTranslations(['en' => ['title' => 'English', 'content' => ''], 'ja' => ['title' => '日本語', 'content' => '']]);
    $this->actingAs($article->user)->post(route('articles.review', $article), ['edit_version' => $article->fresh()->edit_version])->assertSessionHasNoErrors();
    $this->delete(route('articles.translations.destroy', [$article, 'ja']), ['edit_version' => $article->fresh()->edit_version])->assertSessionHasNoErrors();
    $this->delete(route('articles.translations.destroy', [$article, 'en']), ['edit_version' => $article->fresh()->edit_version])->assertSessionHasErrors('history');
    expect($article->fresh()->translations)->toHaveCount(1);
});

test('history is private and revisions from another article cannot be compared or restored', function () {
    $article = bilingualHistoryArticle();
    $foreign = $article->user->articles()->create(['title' => 'Other', 'content' => 'Secret']);
    $revision = ArticleRevision::where('article_id', $foreign->id)->sole();
    $this->actingAs(User::factory()->create())->get(route('articles.history', $article))->assertForbidden();
    $this->post(route('articles.review', $article), ['edit_version' => $article->edit_version])->assertForbidden();
    $this->actingAs($article->user)->get(route('articles.history', [$article, 'locale' => $revision->locale, 'from' => $revision->id]))->assertNotFound();
    $this->post(route('articles.restore', [$article, $revision]), ['edit_version' => $article->fresh()->edit_version])->assertNotFound();
    $this->get(route('public.articles.show', $article->slug))->assertOk()->assertInertia(fn (Assert $page) => $page->missing('article.translationStatus')->missing('article.edit_version'));
});

test('revision and review models reject mutation', function () {
    $article = bilingualHistoryArticle();
    $revision = ArticleRevision::where('article_id', $article->id)->first();
    expect(fn () => $revision->update(['content' => 'Changed']))->toThrow(LogicException::class);
    $this->actingAs($article->user)->post(route('articles.review', $article), ['edit_version' => $article->edit_version]);
    expect(fn () => ArticleTranslationReview::latest('id')->first()->delete())->toThrow(LogicException::class);
});

test('history migration preserves article identity public state and both translation bodies', function () {
    $article = bilingualHistoryArticle();
    $migration = require database_path('migrations/2026_11_22_132254_add_translation_history_to_articles.php');
    $migration->down();

    $migration->up();

    $this->assertDatabaseHas('articles', ['id' => $article->id, 'slug' => 'history', 'is_published' => true]);
    $this->assertDatabaseHas('article_revisions', ['article_id' => $article->id, 'locale' => 'en', 'title' => 'English', 'content' => 'Before']);
    $this->assertDatabaseHas('article_revisions', ['article_id' => $article->id, 'locale' => 'ja', 'title' => '日本語', 'content' => '変更前']);
    expect($article->fresh()->translations->every(fn ($translation): bool => $translation->current_revision_id !== null))->toBeTrue();
    expect($article->fresh()->translationStatus()['en']['state'])->toBe('other_changed');
    $this->assertDatabaseCount('article_translation_reviews', 0);
});

test('saving without an edit version and clearing an existing language are rejected', function () {
    $article = bilingualHistoryArticle();
    $this->actingAs($article->user)->patch(route('articles.update', $article), ['title' => 'Unsafe'])->assertSessionHasErrors('edit_version');
    $this->patch(route('articles.update', $article), ['edit_version' => $article->edit_version, 'translations' => ['en' => ['title' => '', 'content' => '']]])->assertSessionHasErrors('translations.en.title');
    $this->assertDatabaseCount('article_revisions', 2);
    expect($article->fresh()->translations)->toHaveCount(2);
});
