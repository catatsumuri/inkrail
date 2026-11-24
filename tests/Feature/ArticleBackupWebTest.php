<?php

use App\Models\Activity;
use App\Models\Article;
use App\Models\User;
use App\Support\ArticleBackupStore;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Storage::fake('backups');
    config([
        'articles.backup_disk' => 'backups',
        'articles.backup_directory' => 'backups',
        'articles.zenn_publishers' => [],
        'inertia.ssr.enabled' => false,
    ]);
    $this->owner = User::factory()->withUsername('owner')->create();
});

function storedBackup(): string
{
    return app(ArticleBackupStore::class)->create()['filename'];
}

test('guests cannot see or use backups', function () {
    $filename = storedBackup();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->post(route('backups.store'))->assertRedirect(route('login'));
    $this->get(route('backups.download', $filename))->assertRedirect(route('login'));
    $this->post(route('backups.restore', $filename), ['confirmation' => 'restore'])->assertRedirect(route('login'));
    $this->delete(route('backups.destroy', $filename))->assertRedirect(route('login'));
    $this->post(route('backups.upload'), ['backup' => UploadedFile::fake()->create('b.zip')])->assertRedirect(route('login'));
});

test('the dashboard lists backups on the backup disk', function () {
    $filename = storedBackup();

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('backups.disk', 'backups')
            ->where('backups.items.0.filename', $filename));
});

test('backup access is independent of github publishing', function () {
    config(['articles.zenn_publishers' => ['publisher']]);
    $publisher = User::factory()->withUsername('publisher')->create();

    $this->actingAs($publisher)->post(route('backups.store'))->assertRedirect(route('dashboard'));
    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('zenn', null)
            ->where('backups.disk', 'backups'));
});

test('all authenticated accounts can manage backups', function (string $account) {
    $user = User::factory()->withUsername('visitor')->create();

    if ($account === 'unverified') {
        $user->email_verified_at = null;
    }

    if ($account === 'sandbox') {
        $user->is_sandbox = true;
    }

    if ($account === 'no username') {
        $user->username = null;
    }

    $user->save();

    expect(Gate::forUser($user)->allows('manage-article-backups'))->toBeTrue();
    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('backups.disk', 'backups'));
    $this->actingAs($user)->post(route('backups.store'))->assertRedirect(route('dashboard'));
    expect(Storage::disk('backups')->files('backups'))->toHaveCount(1);
})->with([
    'ordinary user' => ['ordinary'],
    'unverified' => ['unverified'],
    'sandbox' => ['sandbox'],
    'no username' => ['no username'],
]);

test('creating a backup stores it on the disk and is audited', function () {
    $this->owner->articles()->create(['slug' => 'one']);

    $this->actingAs($this->owner)->post(route('backups.store'), ['description' => 'before fresh'])->assertRedirect(route('dashboard'));

    expect(Storage::disk('backups')->files('backups'))->toHaveCount(2);
    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('backups.items.0.description', 'before fresh'));
    expect(Activity::query()->where('event', 'articles.backup_created')->count())->toBe(1);
});

test('a stored backup can be downloaded', function () {
    $filename = storedBackup();

    $this->actingAs($this->owner)->get(route('backups.download', $filename))
        ->assertOk()
        ->assertDownload($filename);
    expect(Activity::query()->where('event', 'articles.backup_downloaded')->count())->toBe(1);
});

test('restoring needs the confirmation word and brings articles back', function () {
    $this->owner->articles()->create(['slug' => 'precious'])->replaceTranslations(['en' => ['title' => 'T', 'content' => 'C']]);
    $filename = storedBackup();
    Article::query()->delete();

    $this->actingAs($this->owner)->post(route('backups.restore', $filename), ['confirmation' => 'nope'])
        ->assertSessionHasErrors('confirmation');
    expect(Article::count())->toBe(0);

    $this->actingAs($this->owner)->post(route('backups.restore', $filename), ['confirmation' => 'restore'])
        ->assertRedirect(route('dashboard'));

    expect(Article::where('slug', 'precious')->exists())->toBeTrue();
    expect(Activity::query()->where('event', 'articles.backup_restored')->count())->toBe(1);
});

test('a conflicting restore reports the problem and changes nothing', function () {
    $this->owner->articles()->create(['slug' => 'clash']);
    $filename = storedBackup();
    Article::query()->delete();
    $this->owner->articles()->create(['slug' => 'clash']);

    $this->actingAs($this->owner)->post(route('backups.restore', $filename), ['confirmation' => 'restore'])
        ->assertSessionHasErrors('backup');

    expect(Activity::query()->where('event', 'articles.backup_restore_failed')->count())->toBe(1);
});

test('uploading accepts only real backups', function () {
    $filename = storedBackup();
    $realContents = Storage::disk('backups')->get('backups/'.$filename);
    $upload = UploadedFile::fake()->createWithContent('mine.zip', $realContents);

    $this->actingAs($this->owner)->post(route('backups.upload'), ['backup' => $upload])->assertRedirect(route('dashboard'));
    expect(Storage::disk('backups')->files('backups'))->toHaveCount(2);

    $this->actingAs($this->owner)->post(route('backups.upload'), ['backup' => UploadedFile::fake()->createWithContent('bad.zip', 'not a zip')])
        ->assertSessionHasErrors('backup');
    expect(Storage::disk('backups')->files('backups'))->toHaveCount(2);
});

test('deleting removes the stored file and unknown names are not found', function () {
    $filename = storedBackup();

    $this->actingAs($this->owner)->delete(route('backups.destroy', $filename))->assertRedirect(route('dashboard'));

    expect(Storage::disk('backups')->files('backups'))->toBeEmpty();
    $this->actingAs($this->owner)->delete(route('backups.destroy', $filename))->assertNotFound();
    $this->actingAs($this->owner)->get('/backups/..%2F.env')->assertNotFound();
});

test('the commands use the backup disk too', function () {
    $this->owner->articles()->create(['slug' => 'cli']);

    $this->artisan('articles:backup')->assertSuccessful();
    $filename = basename(Storage::disk('backups')->files('backups')[0]);
    Article::query()->delete();

    $this->artisan('articles:restore', ['path' => $filename])->assertSuccessful();

    expect(Article::where('slug', 'cli')->exists())->toBeTrue();
});

test('a description survives upload and is removed with the backup', function () {
    $filename = app(ArticleBackupStore::class)->create('keep me')['filename'];
    $contents = Storage::disk('backups')->get('backups/'.$filename);
    app(ArticleBackupStore::class)->delete($filename);
    expect(Storage::disk('backups')->files('backups'))->toBeEmpty();

    $this->actingAs($this->owner)->post(route('backups.upload'), ['backup' => UploadedFile::fake()->createWithContent('x.zip', $contents)]);

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('backups.items.0.description', 'keep me'));
});

test('creating a backup warns when referenced image files are missing', function () {
    config(['articles.image_disk' => 'public']);
    Storage::fake('public');
    $article = $this->owner->articles()->create(['slug' => 'with-image']);
    $article->replaceTranslations(['en' => ['title' => 'T', 'content' => '![x](/images/'.hash('sha256', 'gone').'.png)']]);

    $this->actingAs($this->owner)->post(route('backups.store'))
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast.type', 'warning');
});

test('backups are disabled in demo mode', function () {
    config(['app.demo_mode' => true]);
    $filename = storedBackup();

    $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('backups', null));
    $this->actingAs($this->owner)->post(route('backups.store'))->assertForbidden();
    $this->actingAs($this->owner)->get(route('backups.download', $filename))->assertForbidden();
    $this->actingAs($this->owner)->post(route('backups.restore', $filename))->assertForbidden();
    $this->actingAs($this->owner)->delete(route('backups.destroy', $filename))->assertForbidden();
});
