<?php

use App\Models\ArticleImage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config(['articles.image_disk' => 'public', 'articles.image_max_dimension' => 100]);
    $this->user = User::factory()->create();
});

function uploadArticleImage($test, UploadedFile $file, ?User $user = null)
{
    return $test->actingAs($user ?? $test->user)->postJson(route('article-images.store'), ['image' => $file]);
}

test('stores an uploaded image under its content hash and returns its URL', function () {
    $response = uploadArticleImage($this, UploadedFile::fake()->image('photo.png', 40, 30));

    $response->assertCreated()->assertJsonPath('url', fn (string $url) => preg_match('#^/images/[0-9a-f]{64}\.png$#', $url) === 1);
    $image = ArticleImage::query()->sole();
    expect($image->user_id)->toBe($this->user->id);
    expect($image->mime)->toBe('image/png');
    Storage::disk('public')->assertExists($image->storagePath());
    expect($image->path)->toBe(hash('sha256', Storage::disk('public')->get($image->storagePath())).'.png');
});

test('scales an oversized image down to the configured longest side', function () {
    uploadArticleImage($this, UploadedFile::fake()->image('wide.jpg', 400, 200))->assertCreated();

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get(ArticleImage::query()->sole()->storagePath()));
    expect([$width, $height])->toBe([100, 50]);
});

test('reuses the stored file when the same image is uploaded again', function () {
    $file = fn () => UploadedFile::fake()->createWithContent('same.png', (function () {
        ob_start();
        imagepng(imagecreatetruecolor(20, 20));

        return ob_get_clean();
    })());
    $first = uploadArticleImage($this, $file())->json('url');
    $other = User::factory()->create();

    $second = uploadArticleImage($this, $file(), $other)->json('url');

    expect($second)->toBe($first);
    expect(ArticleImage::query()->count())->toBe(1);
    expect(ArticleImage::query()->sole()->user_id)->toBe($this->user->id);
});

test('rejects files that are not supported images', function (UploadedFile $file) {
    uploadArticleImage($this, $file)->assertUnprocessable()->assertJsonValidationErrors('image');

    expect(ArticleImage::query()->count())->toBe(0);
})->with([
    'svg' => fn () => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'text renamed as png' => fn () => UploadedFile::fake()->createWithContent('x.png', 'not an image'),
    'larger than 5 MB' => fn () => UploadedFile::fake()->image('big.png')->size(5121),
    'wider than 8000 px' => fn () => UploadedFile::fake()->image('wide.png', 8001, 1),
]);

test('requires a signed-in user to upload', function () {
    $this->postJson(route('article-images.store'), ['image' => UploadedFile::fake()->image('a.png')])->assertUnauthorized();

    expect(ArticleImage::query()->count())->toBe(0);
});

test('serves a stored image to anyone with long-lived caching', function () {
    uploadArticleImage($this, UploadedFile::fake()->image('photo.png', 40, 30));
    $image = ArticleImage::query()->sole();
    auth()->logout();

    $response = $this->get($image->url());

    $response->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('immutable')->toContain('max-age=31536000');
});

test('returns 404 for a well-formed path that is not stored', function () {
    $this->get('/images/'.str_repeat('a', 64).'.png')->assertNotFound();
});

test('does not route paths that are not content-hash image names', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    'traversal' => '/images/..%2F..%2F.env',
    'short name' => '/images/abc.png',
    'unsupported extension' => '/images/'.str_repeat('a', 64).'.svg',
]);
