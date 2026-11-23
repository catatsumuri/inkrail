<?php

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->zennRepository = sys_get_temp_dir().'/zenn-export-'.uniqid();
    File::ensureDirectoryExists($this->zennRepository.'/articles');
});

afterEach(function () {
    File::deleteDirectory($this->zennRepository);
});

function createBilingualArticle(string $slug, bool $isPublished): Article
{
    $article = User::factory()->create()->articles()->create(['slug' => $slug, 'is_published' => $isPublished]);
    $article->replaceTranslations([
        'en' => ['title' => 'English title', 'content' => 'English body'],
        'ja' => ['title' => '日本語の "題名"', 'content' => "日本語の本文\n\n:::message\n補足\n:::"],
    ]);

    return $article;
}

test('exports only the Japanese version with Zenn frontmatter', function () {
    $article = createBilingualArticle('inkrail-sync', true);

    $this->artisan('articles:export-zenn', ['slug' => 'inkrail-sync', '--path' => $this->zennRepository])->assertSuccessful();

    $files = File::files($this->zennRepository.'/articles');
    expect($files)->toHaveCount(1);
    expect($files[0]->getFilename())->toBe('inkrail-sync.md');
    expect(File::get($files[0]->getPathname()))->toBe(
        "---\ntitle: \"日本語の \\\"題名\\\"\"\nemoji: \"📝\"\ntype: \"tech\"\ntopics: []\npublished: true\ninkrail_id: \"{$article->sync_id}\"\n---\n\n日本語の本文\n\n:::message\n補足\n:::\n"
    );
});

test('keeps an unpublished article as a Zenn draft', function () {
    createBilingualArticle('draft-article', false);

    $this->artisan('articles:export-zenn', ['slug' => 'draft-article', '--path' => $this->zennRepository])->assertSuccessful();

    expect(File::get(File::files($this->zennRepository.'/articles')[0]->getPathname()))->toContain("published: false\n");
});

test('re-exporting updates the same Zenn file instead of creating a new one', function () {
    $article = createBilingualArticle('inkrail-sync', true);
    $this->artisan('articles:export-zenn', ['slug' => 'inkrail-sync', '--path' => $this->zennRepository])->assertSuccessful();
    $firstFile = File::files($this->zennRepository.'/articles')[0]->getFilename();
    $article->replaceTranslations(['ja' => ['title' => '更新後', 'content' => '新しい本文']]);
    $article->update(['slug' => 'renamed-article']);

    $this->artisan('articles:export-zenn', ['slug' => 'renamed-article', '--path' => $this->zennRepository])->assertSuccessful();

    $files = File::files($this->zennRepository.'/articles');
    expect($files)->toHaveCount(1);
    expect($files[0]->getFilename())->toBe($firstFile);
    expect(File::get($files[0]->getPathname()))->toContain('title: "更新後"')->toContain('新しい本文');
});

test('does not overwrite an unlinked local export with the same filename prefix', function () {
    createBilingualArticle('inkrail-sync', true);
    $existingPath = $this->zennRepository.'/articles/inkrail-sync-0a1b2c3d.md';
    File::put($existingPath, "---\ntitle: Old article\n---\n\nKeep this article\n");

    $this->artisan('articles:export-zenn', ['slug' => 'inkrail-sync', '--path' => $this->zennRepository])->assertSuccessful();

    expect(File::files($this->zennRepository.'/articles'))->toHaveCount(2);
    expect(File::get($existingPath))->toBe("---\ntitle: Old article\n---\n\nKeep this article\n");
});

test('refuses to export when multiple local files carry the article identity', function () {
    $article = createBilingualArticle('inkrail-sync', true);
    $markdown = "---\ninkrail_id: \"{$article->sync_id}\"\n---\n\nKeep this content\n";
    File::put($this->zennRepository.'/articles/first-article-12345678.md', $markdown);
    File::put($this->zennRepository.'/articles/second-article-12345678.md', $markdown);

    $this->artisan('articles:export-zenn', ['slug' => 'inkrail-sync', '--path' => $this->zennRepository])->assertFailed();

    expect(File::get($this->zennRepository.'/articles/first-article-12345678.md'))->toBe($markdown);
    expect(File::get($this->zennRepository.'/articles/second-article-12345678.md'))->toBe($markdown);
});

test('builds a Zenn slug that satisfies the length and character rules', function (string $slug) {
    createBilingualArticle($slug, true);

    $this->artisan('articles:export-zenn', ['slug' => $slug, '--path' => $this->zennRepository])->assertSuccessful();

    $name = File::files($this->zennRepository.'/articles')[0]->getFilenameWithoutExtension();
    expect($name)->toMatch('/^[a-z0-9_-]{12,50}$/');
})->with([
    'too short' => 'ab',
    'too long' => str_repeat('long-slug-', 8),
    'invalid characters' => 'Mixed.Case slug',
]);

test('fails when the article has no Japanese version', function () {
    $article = User::factory()->create()->articles()->create(['slug' => 'english-only']);
    $article->replaceTranslations(['en' => ['title' => 'Only English', 'content' => 'Body']]);

    $this->artisan('articles:export-zenn', ['slug' => 'english-only', '--path' => $this->zennRepository])->assertFailed();

    expect(File::files($this->zennRepository.'/articles'))->toBeEmpty();
});

test('preserves valid readable slugs without reserving space for a suffix', function (string $slug) {
    createBilingualArticle($slug, true);

    $this->artisan('articles:export-zenn', ['slug' => $slug, '--path' => $this->zennRepository])->assertSuccessful();

    expect(File::files($this->zennRepository.'/articles')[0]->getFilenameWithoutExtension())->toBe($slug);
})->with(['readable' => 'catatsumurii-github-sync', 'maximum length' => str_repeat('a', 50)]);

test('adds a suffix only when the readable filename belongs to another article', function () {
    createBilingualArticle('inkrail-sync', true);
    $existingPath = $this->zennRepository.'/articles/inkrail-sync.md';
    File::put($existingPath, "---\ntitle: Existing\n---\n\nKeep me\n");

    $this->artisan('articles:export-zenn', ['slug' => 'inkrail-sync', '--path' => $this->zennRepository])->assertSuccessful();

    expect(File::files($this->zennRepository.'/articles'))->toHaveCount(2);
    expect(File::get($existingPath))->toBe("---\ntitle: Existing\n---\n\nKeep me\n");
    expect(collect(File::files($this->zennRepository.'/articles'))->filter(fn (SplFileInfo $file): bool => preg_match('/^inkrail-sync-[0-9a-f]{8}\.md$/', $file->getFilename()) === 1))->toHaveCount(1);
});

test('fails when the article does not exist', function () {
    $this->artisan('articles:export-zenn', ['slug' => 'missing', '--path' => $this->zennRepository])->assertFailed();

    expect(File::files($this->zennRepository.'/articles'))->toBeEmpty();
});

test('fails when the Zenn repository path is not usable', function () {
    createBilingualArticle('inkrail-sync', true);
    config(['articles.zenn_repository_path' => null]);

    $this->artisan('articles:export-zenn', ['slug' => 'inkrail-sync'])->assertFailed();
});
