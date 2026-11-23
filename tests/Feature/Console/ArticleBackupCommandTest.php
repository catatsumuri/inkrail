<?php

use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\ArticleNamespace;
use App\Models\ArticleRevision;
use App\Models\ArticleTranslationReview;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config(['articles.image_disk' => 'public']);
    $this->backupPath = sys_get_temp_dir().'/article-backup-'.uniqid().'.zip';
});

afterEach(function () {
    File::delete($this->backupPath);
});

function createBackedUpArticle(User $owner): Article
{
    $article = $owner->articles()->create([
        'slug' => 'backed-up',
        'is_published' => true,
        'topics' => ['laravel'],
    ]);
    $article->namespace()->associate(ArticleNamespace::factory()->create(['name' => 'Notes', 'slug' => 'notes']))->save();
    $article->replaceTranslations([
        'en' => ['title' => 'English', 'content' => 'First body'],
        'ja' => ['title' => '日本語', 'content' => '本文'],
    ]);
    $article->translations()->where('locale', 'en')->first()->update(['content' => 'Second body']);

    return $article->fresh();
}

function wipeArticles(): void
{
    Article::query()->delete();
    ArticleNamespace::query()->delete();
    ArticleImage::query()->delete();
}

test('a backup restores articles with revision history, reviews, images and metadata', function () {
    $owner = User::factory()->withUsername('writer')->create();
    $article = createBackedUpArticle($owner);
    $image = ArticleImage::factory()->for($owner)->create();
    Storage::disk('public')->put($image->storagePath(), 'image-bytes');
    $expected = [
        'sync_id' => $article->sync_id,
        'edit_version' => $article->edit_version,
        'revisions' => ArticleRevision::where('article_id', $article->id)->orderBy('id')->pluck('content')->all(),
        'reviews' => ArticleTranslationReview::where('article_id', $article->id)->count(),
    ];

    $this->artisan('articles:backup', ['--output' => $this->backupPath, '--description' => 'before fresh'])->assertSuccessful();

    $this->artisan('migrate:fresh')->assertSuccessful();
    Storage::disk('public')->deleteDirectory(ArticleImage::DIRECTORY);
    $newOwner = User::factory()->withUsername('writer')->create();

    $this->artisan('articles:restore', ['path' => $this->backupPath])->assertSuccessful();

    $restored = Article::query()->where('slug', 'backed-up')->firstOrFail();
    expect($restored->sync_id)->toBe($expected['sync_id'])
        ->and($restored->user_id)->toBe($newOwner->id)
        ->and($restored->is_published)->toBeTrue()
        ->and($restored->topics)->toBe(['laravel'])
        ->and($restored->edit_version)->toBe($expected['edit_version'])
        ->and($restored->namespace->slug)->toBe('notes')
        ->and($restored->translations->pluck('content', 'locale')->all())->toBe(['en' => 'Second body', 'ja' => '本文'])
        ->and(ArticleRevision::where('article_id', $restored->id)->orderBy('id')->pluck('content')->all())->toBe($expected['revisions'])
        ->and(ArticleTranslationReview::where('article_id', $restored->id)->count())->toBe($expected['reviews']);

    $english = $restored->translations->firstWhere('locale', 'en');
    expect(ArticleRevision::find($english->current_revision_id)->content)->toBe('Second body');
    Storage::disk('public')->assertExists($image->storagePath());
    expect(ArticleImage::where('path', $image->path)->value('user_id'))->toBe($newOwner->id);
});

test('restoring over an existing article replaces its history without duplicating it', function () {
    $article = createBackedUpArticle(User::factory()->withUsername('writer')->create());
    $revisionCount = ArticleRevision::where('article_id', $article->id)->count();
    $this->artisan('articles:backup', ['--output' => $this->backupPath])->assertSuccessful();
    $article->translations()->where('locale', 'en')->first()->update(['content' => 'Changed after backup']);

    $this->artisan('articles:restore', ['path' => $this->backupPath])->assertSuccessful();

    expect(Article::count())->toBe(1)
        ->and(Article::first()->translations->firstWhere('locale', 'en')->content)->toBe('Second body')
        ->and(ArticleRevision::count())->toBe($revisionCount);
});

test('articles whose owner is gone go to the --user option', function () {
    createBackedUpArticle(User::factory()->withUsername('gone')->create());
    $this->artisan('articles:backup', ['--output' => $this->backupPath])->assertSuccessful();
    wipeArticles();
    User::query()->delete();
    User::factory()->create();
    $heir = User::factory()->withUsername('heir')->create();

    $this->artisan('articles:restore', ['path' => $this->backupPath, '--user' => 'heir'])->assertSuccessful();

    expect(Article::first()->user_id)->toBe($heir->id);
});

test('a slug used by a different article aborts the restore without changes', function () {
    $owner = User::factory()->withUsername('writer')->create();
    createBackedUpArticle($owner);
    $this->artisan('articles:backup', ['--output' => $this->backupPath])->assertSuccessful();
    wipeArticles();
    $owner->articles()->create(['slug' => 'backed-up']);

    $this->artisan('articles:restore', ['path' => $this->backupPath])
        ->expectsOutputToContain('already used by a different article')
        ->assertFailed();

    expect(Article::count())->toBe(1)->and(ArticleNamespace::count())->toBe(0);
});

test('restore fails cleanly for a missing file, a non-backup zip and an unknown --user', function () {
    User::factory()->withUsername('writer')->create();

    $this->artisan('articles:restore', ['path' => '/nonexistent/backup.zip'])->assertFailed();

    $zip = new ZipArchive;
    $zip->open($this->backupPath, ZipArchive::CREATE);
    $zip->addFromString('readme.txt', 'not a backup');
    $zip->close();
    $this->artisan('articles:restore', ['path' => $this->backupPath])->assertFailed();

    $this->artisan('articles:restore', ['path' => $this->backupPath, '--user' => 'nobody'])->assertFailed();
});

test('restore rejects image paths that escape the image directory', function () {
    User::factory()->create();
    $zip = new ZipArchive;
    $zip->open($this->backupPath, ZipArchive::CREATE);
    $zip->addFromString('manifest.json', json_encode(['format' => 1]));
    $zip->addFromString('data.json', json_encode(['images' => [['path' => '../evil.php', 'mime' => 'image/png', 'size' => 1, 'owner' => null, 'created_at' => null, 'updated_at' => null]]]));
    $zip->close();

    $this->artisan('articles:restore', ['path' => $this->backupPath])->assertFailed();

    Storage::disk('public')->assertMissing('evil.php');
});
