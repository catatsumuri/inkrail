<?php

use App\Actions\Backup\RestoreArticleBackup;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\ArticleNamespace;
use App\Models\ArticleRevision;
use App\Models\ArticleTranslationReview;
use App\Models\User;
use Illuminate\Database\QueryException;
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

test('a backup restores articles with revision history, reviews, images and metadata after the data is gone', function () {
    $owner = User::factory()->withUsername('writer')->create();
    $article = createBackedUpArticle($owner);
    $image = ArticleImage::factory()->for($owner)->create(['path' => hash('sha256', 'image-bytes').'.png']);
    Storage::disk('public')->put($image->storagePath(), 'image-bytes');
    $expected = [
        'sync_id' => $article->sync_id,
        'edit_version' => $article->edit_version,
        'revisions' => ArticleRevision::where('article_id', $article->id)->orderBy('id')->pluck('content')->all(),
        'reviews' => ArticleTranslationReview::where('article_id', $article->id)->count(),
    ];

    $this->artisan('articles:backup', ['--output' => $this->backupPath, '--description' => 'before fresh'])->assertSuccessful();

    wipeArticles();
    User::query()->delete();
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

function archiveWithImages(string $path, array $images): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('manifest.json', json_encode(['format' => 1]));
    $zip->addFromString('data.json', json_encode(['images' => array_map(
        fn (string $name): array => ['path' => $name, 'mime' => 'image/png', 'size' => 1, 'owner' => null, 'created_at' => null, 'updated_at' => null],
        array_keys($images),
    )]));

    foreach ($images as $name => $bytes) {
        if ($bytes !== null) {
            $zip->addFromString('_files/'.$name, $bytes);
        }
    }

    $zip->close();
}

test('a missing or damaged image aborts the restore before any image is written', function () {
    User::factory()->create();
    $good = hash('sha256', 'good').'.png';
    $missing = hash('sha256', 'missing').'.png';
    $damaged = hash('sha256', 'expected').'.png';

    archiveWithImages($this->backupPath, [$good => 'good', $missing => null]);
    $this->artisan('articles:restore', ['path' => $this->backupPath])->expectsOutputToContain('missing the image file')->assertFailed();

    archiveWithImages($this->backupPath, [$good => 'good', $damaged => 'tampered']);
    $this->artisan('articles:restore', ['path' => $this->backupPath])->expectsOutputToContain('damaged')->assertFailed();

    Storage::disk('public')->assertMissing(ArticleImage::DIRECTORY.'/'.$good);
    expect(ArticleImage::count())->toBe(0);
});

test('images written by a restore that later fails are removed again', function () {
    User::factory()->create();
    $first = hash('sha256', 'first').'.png';
    $second = hash('sha256', 'second').'.png';
    archiveWithImages($this->backupPath, [$first => 'first', $second => 'second']);
    $zip = new ZipArchive;
    $zip->open($this->backupPath);
    $zip->addFromString('data.json', json_encode(['images' => [
        ['path' => $first, 'mime' => 'image/png', 'size' => 1, 'owner' => null, 'created_at' => null, 'updated_at' => null],
        ['path' => $second, 'mime' => null, 'size' => 1, 'owner' => null, 'created_at' => null, 'updated_at' => null],
    ]]));
    $zip->close();

    expect(fn () => app(RestoreArticleBackup::class)($this->backupPath))->toThrow(QueryException::class);

    Storage::disk('public')->assertMissing(ArticleImage::DIRECTORY.'/'.$first);
    Storage::disk('public')->assertMissing(ArticleImage::DIRECTORY.'/'.$second);
    expect(ArticleImage::count())->toBe(0);
});

test('an image that already exists is not overwritten', function () {
    User::factory()->create();
    $name = hash('sha256', 'same').'.png';
    Storage::disk('public')->put(ArticleImage::DIRECTORY.'/'.$name, 'already there');
    archiveWithImages($this->backupPath, [$name => 'same']);

    $this->artisan('articles:restore', ['path' => $this->backupPath])->assertSuccessful();

    expect(Storage::disk('public')->get(ArticleImage::DIRECTORY.'/'.$name))->toBe('already there');
    expect(ArticleImage::where('path', $name)->exists())->toBeTrue();
});
