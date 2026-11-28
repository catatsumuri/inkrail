<?php

use App\Actions\Backup\CreateArticleBackup;
use App\Actions\Backup\RestoreArticleBackup;
use App\Actions\Zenn\WithZennSyncLock;
use App\Models\Activity;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\ArticleNamespace;
use App\Models\User;
use App\Support\ArticleBackupStore;
use App\Support\RepositoryImageStore;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Storage::fake('sandbox-backup-images');
    config(['articles.image_disk' => 'sandbox-backup-images', 'app.url' => 'https://source.example']);
    Http::preventStrayRequests();
    $this->backupPath = tempnam(sys_get_temp_dir(), 'sandbox-backup-');
});

afterEach(function () {
    @unlink($this->backupPath);
});

function sandboxBackupDocument(): Article
{
    $owner = User::factory()->withUsername('writer')->create();
    $article = $owner->articles()->create([]);
    $article->namespace()->associate(ArticleNamespace::query()->where('slug', 'sandbox')->sole())->save();
    $article->replaceTranslations(['ja' => ['title' => 'Sandbox note', 'content' => 'Original body']]);
    DB::table('article_github_sources')->insert([
        'article_id' => $article->id, 'repository' => 'writer/notes', 'branch' => 'main', 'path' => 'sandbox/note.md',
        'remote_sha' => str_repeat('a', 40), 'local_fingerprint' => hash('sha256', 'Original body'),
        'frontmatter' => json_encode(['title' => 'Sandbox note']), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $article->fresh();
}

function sandboxBackupPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
}

function editSandboxArchive(string $path, Closure $edit): void
{
    $zip = new ZipArchive;
    $zip->open($path);
    $data = json_decode($zip->getFromName('data.json'), true);
    $edit($data, $zip);
    $zip->addFromString('data.json', json_encode($data));
    $zip->close();
}

test('a fresh Sandbox backup restores its source, body, history and repository images without old database ids', function () {
    $article = sandboxBackupDocument();
    $bytes = sandboxBackupPng();
    $hashed = hash('sha256', $bytes).'.png';
    app(RepositoryImageStore::class)->put($hashed, $bytes);
    app(RepositoryImageStore::class)->put('nested/screenshot.png', $bytes);
    $article->replaceTranslations(['ja' => ['title' => 'Sandbox note', 'content' => "![old](/images/{$hashed})"]]);
    $article->replaceTranslations(['ja' => ['title' => 'Sandbox note', 'content' => '![new](/images/nested/screenshot.png)']]);
    $syncId = $article->sync_id;
    $oldId = $article->id;
    $revisionCount = DB::table('article_revisions')->where('article_id', $article->id)->count();
    $source = (array) DB::table('article_github_sources')->where('article_id', $oldId)->first();

    $result = app(CreateArticleBackup::class)($this->backupPath);
    expect($result['missing_images'])->toBeEmpty()->and($result['images'])->toBe(2);
    Article::query()->delete();
    Storage::disk('sandbox-backup-images')->deleteDirectory(RepositoryImageStore::DIRECTORY);
    $article->user->articles()->create([]);
    $restoredCounts = app(RestoreArticleBackup::class)($this->backupPath);

    $restored = Article::where('sync_id', $syncId)->sole();
    expect($restored->id)->not->toBe($oldId);
    expect($restored->isSandbox())->toBeTrue();
    expect($restored->is_published)->toBeFalse();
    expect($restored->translations->sole()->content)->toBe('![new](/images/nested/screenshot.png)');
    expect(DB::table('article_revisions')->where('article_id', $restored->id)->count())->toBe($revisionCount);
    $linked = DB::table('article_github_sources')->where('article_id', $restored->id)->sole();
    expect($linked->path)->toBe($source['path'])->and(json_decode($linked->frontmatter, true))->toBe(['title' => 'Sandbox note']);
    expect($restoredCounts['skipped_github_sources'])->toBe(0);
    expect(Storage::disk('sandbox-backup-images')->get('repository-images/'.$hashed))->toBe($bytes);
    expect(Storage::disk('sandbox-backup-images')->get('repository-images/nested/screenshot.png'))->toBe($bytes);
    expect(ArticleImage::count())->toBe(0);
    Http::assertNothingSent();
});

test('restoring a Sandbox backup over itself replaces its single source without duplicates', function () {
    $article = sandboxBackupDocument();
    app(CreateArticleBackup::class)($this->backupPath);
    $article->replaceTranslations(['ja' => ['title' => 'Changed', 'content' => 'Changed after backup']]);

    app(RestoreArticleBackup::class)($this->backupPath);

    expect(Article::count())->toBe(1);
    expect(DB::table('article_github_sources')->count())->toBe(1);
    expect($article->fresh()->translations->sole()->content)->toBe('Original body');
});

test('a conflicting Sandbox source reports a conflict and leaves every article unchanged', function () {
    $article = sandboxBackupDocument();
    app(CreateArticleBackup::class)($this->backupPath);
    $article->forceFill(['sync_id' => (string) Str::uuid(), 'slug' => 'reimported'])->save();
    $article->replaceTranslations(['ja' => ['title' => 'Keep me', 'content' => 'Local edits']]);
    $syncId = $article->sync_id;

    expect(fn () => app(RestoreArticleBackup::class)($this->backupPath))->toThrow(RuntimeException::class, 'already linked to a different article');

    expect(Article::count())->toBe(1);
    expect($article->fresh()->sync_id)->toBe($syncId);
    expect($article->fresh()->translations->sole()->content)->toBe('Local edits');
    expect(DB::table('article_github_sources')->where('article_id', $article->id)->value('path'))->toBe('sandbox/note.md');
});

test('a backup from another environment restores Sandbox content without taking its existing GitHub link', function () {
    $article = sandboxBackupDocument();
    app(CreateArticleBackup::class)($this->backupPath);
    $article->forceFill(['sync_id' => (string) Str::uuid(), 'slug' => 'destination-note'])->save();
    config(['app.url' => 'https://destination.example']);

    $counts = app(RestoreArticleBackup::class)($this->backupPath);

    expect($counts['skipped_github_sources'])->toBe(1);
    expect(Article::count())->toBe(2);
    $imported = Article::where('id', '!=', $article->id)->sole();
    expect($imported->isSandbox())->toBeTrue();
    expect($imported->translations->sole()->content)->toBe('Original body');
    expect(DB::table('article_github_sources')->where('article_id', $imported->id)->exists())->toBeFalse();
    expect(DB::table('article_github_sources')->where('article_id', $article->id)->value('path'))->toBe('sandbox/note.md');
});

test('a foreign backup cannot overwrite a locally linked Sandbox even when the sync id matches', function () {
    $article = sandboxBackupDocument();
    app(CreateArticleBackup::class)($this->backupPath);
    $article->replaceTranslations(['ja' => ['title' => 'Keep', 'content' => 'Destination edits']]);
    config(['app.url' => 'https://destination.example']);

    expect(fn () => app(RestoreArticleBackup::class)($this->backupPath))->toThrow(RuntimeException::class, 'cannot overwrite');

    expect($article->fresh()->translations->sole()->content)->toBe('Destination edits');
    expect(DB::table('article_github_sources')->where('article_id', $article->id)->exists())->toBeTrue();
});

test('restoring an unlinked Sandbox snapshot removes a source added after the backup', function () {
    $article = sandboxBackupDocument();
    $source = (array) DB::table('article_github_sources')->where('article_id', $article->id)->sole();
    DB::table('article_github_sources')->delete();
    app(CreateArticleBackup::class)($this->backupPath);
    DB::table('article_github_sources')->insert($source);

    app(RestoreArticleBackup::class)($this->backupPath);

    expect(DB::table('article_github_sources')->count())->toBe(0);
    expect($article->fresh()->translations->sole()->content)->toBe('Original body');
});

test('a changed repository image stops the restore before article contents are replaced', function () {
    $article = sandboxBackupDocument();
    app(RepositoryImageStore::class)->put('diagram.png', sandboxBackupPng());
    app(CreateArticleBackup::class)($this->backupPath);
    Storage::disk('sandbox-backup-images')->put('repository-images/diagram.png', 'Keep these bytes');
    $article->replaceTranslations(['ja' => ['title' => 'Keep', 'content' => 'Local edits']]);

    expect(fn () => app(RestoreArticleBackup::class)($this->backupPath))->toThrow(RuntimeException::class, 'different content');

    expect($article->fresh()->translations->sole()->content)->toBe('Local edits');
    expect(Storage::disk('sandbox-backup-images')->get('repository-images/diagram.png'))->toBe('Keep these bytes');
});

test('unsafe, missing or damaged repository images reject the entire restore', function (string $defect) {
    sandboxBackupDocument();
    app(RepositoryImageStore::class)->put('diagram.png', sandboxBackupPng());
    app(CreateArticleBackup::class)($this->backupPath);
    editSandboxArchive($this->backupPath, function (array &$data, ZipArchive $zip) use ($defect) {
        if ($defect === 'unsafe') {
            $data['repository_images'][0]['path'] = '../outside.png';
        } elseif ($defect === 'missing') {
            $zip->deleteName('_repository_files/diagram.png');
        } else {
            $zip->addFromString('_repository_files/diagram.png', 'tampered');
        }
    });
    Article::query()->delete();
    Storage::disk('sandbox-backup-images')->deleteDirectory('repository-images');

    expect(fn () => app(RestoreArticleBackup::class)($this->backupPath))->toThrow(RuntimeException::class);

    expect(Article::count())->toBe(0);
    Storage::disk('sandbox-backup-images')->assertMissing('repository-images/diagram.png');
})->with(['unsafe', 'missing', 'damaged']);

test('backup and restore cannot overlap GitHub synchronization', function () {
    sandboxBackupDocument();
    app(CreateArticleBackup::class)($this->backupPath);

    app(WithZennSyncLock::class)(function () {
        expect(fn () => app(CreateArticleBackup::class)($this->backupPath))->toThrow(RuntimeException::class, 'in progress');
        expect(fn () => app(RestoreArticleBackup::class)($this->backupPath))->toThrow(RuntimeException::class, 'in progress');
    });

    expect(Article::count())->toBe(1);
});

test('old backup formats fail explicitly rather than applying an incomplete Sandbox restore', function () {
    $zip = new ZipArchive;
    $zip->open($this->backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('manifest.json', json_encode(['format' => 1]));
    $zip->addFromString('data.json', '{}');
    $zip->close();

    expect(fn () => app(RestoreArticleBackup::class)($this->backupPath))->toThrow(RuntimeException::class, 'Create a new backup');
});

test('a Sandbox source already used by a regular article is not reassigned by restore', function () {
    $article = sandboxBackupDocument();
    app(CreateArticleBackup::class)($this->backupPath);
    $regular = $article->user->articles()->create([]);
    $regular->forceFill(['zenn_repository' => 'writer/notes', 'zenn_branch' => 'main', 'zenn_path' => 'sandbox/note.md'])->save();

    expect(fn () => app(RestoreArticleBackup::class)($this->backupPath))->toThrow(RuntimeException::class, 'already linked to a different article');

    expect($regular->fresh()->zenn_path)->toBe('sandbox/note.md');
    expect(DB::table('article_github_sources')->where('article_id', $article->id)->exists())->toBeTrue();
});

test('an image write failure rolls back Sandbox data and removes only files written by this restore', function () {
    $article = sandboxBackupDocument();
    $bytes = sandboxBackupPng();
    app(RepositoryImageStore::class)->put('first.png', $bytes);
    app(RepositoryImageStore::class)->put('second.png', $bytes);
    app(CreateArticleBackup::class)($this->backupPath);
    $disk = Storage::disk('sandbox-backup-images');
    $disk->deleteDirectory('repository-images');
    $article->replaceTranslations(['ja' => ['title' => 'Keep', 'content' => 'Keep local edits']]);
    $disk->put('unrelated.txt', 'Keep this file');
    $failingDisk = Mockery::mock($disk);
    $failingDisk->shouldReceive('put')->with('repository-images/first.png', $bytes)->once()->andReturnUsing(fn (string $path, string $contents): bool => $disk->put($path, $contents));
    $failingDisk->shouldReceive('put')->with('repository-images/second.png', $bytes)->once()->andReturn(false);
    Storage::set('sandbox-backup-images', $failingDisk);

    expect(fn () => app(RestoreArticleBackup::class)($this->backupPath))->toThrow(RuntimeException::class, 'could not be restored');

    expect($article->fresh()->translations->sole()->content)->toBe('Keep local edits');
    $disk->assertMissing('repository-images/first.png');
    $disk->assertMissing('repository-images/second.png');
    expect($disk->get('unrelated.txt'))->toBe('Keep this file');
});

test('the web restore reports skipped foreign Sandbox links and audits the successful content restore', function () {
    Storage::fake('sandbox-backup-archives');
    config(['articles.backup_disk' => 'sandbox-backup-archives']);
    $article = sandboxBackupDocument();
    $owner = $article->user;
    app(CreateArticleBackup::class)($this->backupPath);
    $filename = app(ArticleBackupStore::class)->put($this->backupPath);
    $article->delete();
    config(['app.url' => 'https://destination.example']);

    $this->actingAs($owner)->post(route('backups.restore', $filename), ['confirmation' => 'restore'])
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast.type', 'warning')
        ->assertInertiaFlash('toast.message', 'バックアップから1件の記事を復元しました。 別環境のSandbox GitHub連携 1 件は引き継ぎませんでした。');

    expect(Article::sole()->translations->sole()->content)->toBe('Original body');
    expect(DB::table('article_github_sources')->count())->toBe(0);
    expect(Activity::where('event', 'articles.backup_restored')->count())->toBe(1);
});

test('a Sandbox source without frontmatter can be backed up and restored', function () {
    $article = sandboxBackupDocument();
    DB::table('article_github_sources')->where('article_id', $article->id)->update(['frontmatter' => null]);
    app(CreateArticleBackup::class)($this->backupPath);
    $article->delete();

    app(RestoreArticleBackup::class)($this->backupPath);

    expect(DB::table('article_github_sources')->sole()->frontmatter)->toBeNull();
    expect(Article::sole()->translations->sole()->content)->toBe('Original body');
});
