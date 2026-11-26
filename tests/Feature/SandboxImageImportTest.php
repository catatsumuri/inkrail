<?php

use App\Actions\ImportSandboxDocuments;
use App\Models\ArticleImage;
use App\Models\User;
use App\Support\RepositoryImageStore;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['articles.zenn_publishers' => ['publisher'], 'services.github_zenn' => ['repository' => 'owner/notes', 'branch' => 'main', 'token' => 'secret'], 'articles.image_disk' => 'public']);
});

/**
 * @param  array<string, string>  $files
 * @param  array<string, array<string, mixed>>  $overrides
 */
function fakeSandboxImageRepository(array $files, array $overrides = []): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $tree = [];
    $responses = [];
    foreach ($files as $path => $bytes) {
        $entry = array_replace(['path' => $path, 'type' => 'blob', 'mode' => '100644', 'sha' => sha1('blob '.strlen($bytes)."\0".$bytes), 'size' => strlen($bytes)], $overrides[$path] ?? []);
        $tree[] = $entry;
        $responses['https://api.github.com/repos/owner/notes/git/blobs/'.$entry['sha']] = Http::response(['encoding' => 'base64', 'content' => base64_encode($bytes)]);
    }
    $responses['https://api.github.com/repos/owner/notes/git/trees/main*'] = Http::response(['truncated' => false, 'tree' => $tree]);
    Http::fake($responses);
}

test('sandbox sync imports all repository images and serves their original paths to guests', function () {
    Storage::fake('public');
    $owner = User::factory()->withUsername('publisher')->create();
    $png = UploadedFile::fake()->image('diagram.png')->get();
    $jpeg = UploadedFile::fake()->image('photo.jpg')->get();
    $markdown = "# Note\n\n![diagram](/images/図.png)";
    fakeSandboxImageRepository([
        'sandbox/note.md' => $markdown,
        'articles/not-imported.md' => '# Not imported',
        'images/図.png' => $png,
        'images/nested/unreferenced.jpeg' => $jpeg,
        'images/not-an-image.txt' => 'Text',
        'images/symlink.png' => 'private/file',
    ], ['images/symlink.png' => ['mode' => '120000']]);

    $response = $this->actingAs($owner)->post(route('sandbox.import'));

    $response->assertRedirect(route('articles.index', ['namespace' => 'sandbox']))->assertSessionHas('sandbox_import.created', 3);
    $article = $owner->articles()->sole();
    expect($article->content)->toBe($markdown);
    expect($article->isSandbox())->toBeTrue();
    Storage::disk('public')->assertExists(['repository-images/図.png', 'repository-images/nested/unreferenced.jpeg']);
    Storage::disk('public')->assertMissing(['repository-images/not-an-image.txt', 'repository-images/symlink.png']);
    auth()->logout();
    $image = $this->get('/images/'.rawurlencode('図.png'))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($image->streamedContent())->toBe($png);
    expect($image->headers->get('Cache-Control'))->toContain('public', 'must-revalidate')->not->toContain('immutable');
    expect($this->get('/images/nested/unreferenced.jpeg')->assertOk()->streamedContent())->toBe($jpeg);
    $this->get(route('public.articles.show', $article->slug))->assertNotFound();
    Http::assertSentCount(4);
    Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
});

test('sandbox sync updates images even when the Markdown has not changed', function () {
    Storage::fake('public');
    $owner = User::factory()->withUsername('publisher')->create();
    $first = UploadedFile::fake()->image('diagram.png', 2, 2)->get();
    $second = UploadedFile::fake()->image('diagram.png', 3, 2)->get();
    $files = ['sandbox/note.md' => '# Note', 'images/diagram.png' => $first];
    fakeSandboxImageRepository($files);
    expect(app(ImportSandboxDocuments::class)($owner)['created'])->toBe(2);

    fakeSandboxImageRepository($files);
    expect(app(ImportSandboxDocuments::class)($owner)['unchanged'])->toBe(2);
    fakeSandboxImageRepository([...$files, 'images/diagram.png' => $second]);

    $result = app(ImportSandboxDocuments::class)($owner);

    expect($result['updated'])->toBe(1);
    expect($result['unchanged'])->toBe(1);
    expect($this->get('/images/diagram.png')->assertOk()->streamedContent())->toBe($second);
    expect($owner->articles()->count())->toBe(1);
    Http::assertSentCount(3);
});

test('sandbox streaming reports images in progress and final results', function () {
    Storage::fake('public');
    $owner = User::factory()->withUsername('publisher')->create();
    $png = UploadedFile::fake()->image('diagram.png')->get();
    fakeSandboxImageRepository(['images/diagram.png' => $png]);

    $content = $this->actingAs($owner)->withHeader('Accept', 'text/event-stream')
        ->post(route('sandbox.import'))->assertOk()->streamedContent();

    expect($content)->toContain('Reading image :current of :total.', '"status":"imported"', '"created":1')->not->toContain('secret');
    Storage::disk('public')->assertExists('repository-images/diagram.png');
    Http::assertSentCount(2);
});

test('invalid repository images are skipped without changing a previously imported image', function (string $bytes, array $overrides) {
    Storage::fake('public');
    $owner = User::factory()->withUsername('publisher')->create();
    $original = UploadedFile::fake()->image('diagram.png')->get();
    Storage::disk('public')->put('repository-images/diagram.png', $original);
    fakeSandboxImageRepository(['images/diagram.png' => $bytes], ['images/diagram.png' => $overrides]);

    $result = app(ImportSandboxDocuments::class)($owner);

    expect($result['invalid'])->toBe(1);
    expect(Storage::disk('public')->get('repository-images/diagram.png'))->toBe($original);
    expect($owner->articles()->count())->toBe(0);
})->with([
    'HTML disguised as PNG' => ['<html>Not an image</html>', []],
    'checksum mismatch' => ['Not the advertised blob', ['sha' => str_repeat('a', 40)]],
    'oversized blob' => ['Not downloaded', ['size' => RepositoryImageStore::MAX_BYTES + 1]],
]);

test('repository images cannot escape the image directory or expose unsupported file types', function (string $path) {
    Storage::fake('public');
    $owner = User::factory()->withUsername('publisher')->create();
    fakeSandboxImageRepository([$path => 'Not an image']);

    $result = app(ImportSandboxDocuments::class)($owner);

    expect($result['items'])->toBe([]);
    Storage::disk('public')->assertEmpty();
    Http::assertSentCount(1);
    $this->get('/'.$path)->assertNotFound();
})->with(['images/../private.png', 'images/%2e%2e/private.png', 'images/folder/../../private.png', 'images/folder//note.png', 'images/script.svg']);

test('repository image imports preserve uploaded images and their immutable public URLs', function () {
    Storage::fake('public');
    $owner = User::factory()->withUsername('publisher')->create();
    $png = UploadedFile::fake()->image('upload.png', 2, 2)->get();
    $path = hash('sha256', $png).'.png';
    Storage::disk('public')->put(ArticleImage::DIRECTORY.'/'.$path, $png);
    $other = UploadedFile::fake()->image('remote.png', 3, 2)->get();
    fakeSandboxImageRepository(['images/'.$path => $other]);

    app(ImportSandboxDocuments::class)($owner);

    $response = $this->get('/images/'.$path)->assertOk();
    expect($response->streamedContent())->toBe($png);
    expect($response->headers->get('Cache-Control'))->toContain('immutable');
    Http::assertSentCount(2);
});
