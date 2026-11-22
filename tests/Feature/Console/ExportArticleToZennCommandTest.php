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
    createBilingualArticle('inkrail-sync', true);

    $this->artisan('articles:export-zenn', ['slug' => 'inkrail-sync', '--path' => $this->zennRepository])->assertSuccessful();

    $files = File::files($this->zennRepository.'/articles');
    expect($files)->toHaveCount(1);
    expect($files[0]->getFilename())->toMatch('/^inkrail-sync-[0-9a-f]{8}\.md$/');
    expect(File::get($files[0]->getPathname()))->toBe(
        "---\ntitle: \"日本語の \\\"題名\\\"\"\nemoji: \"📝\"\ntype: \"tech\"\ntopics: []\npublished: true\n---\n\n日本語の本文\n\n:::message\n補足\n:::\n"
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

    $this->artisan('articles:export-zenn', ['slug' => 'inkrail-sync', '--path' => $this->zennRepository])->assertSuccessful();

    $files = File::files($this->zennRepository.'/articles');
    expect($files)->toHaveCount(1);
    expect($files[0]->getFilename())->toBe($firstFile);
    expect(File::get($files[0]->getPathname()))->toContain('title: "更新後"')->toContain('新しい本文');
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

test('fails when the article does not exist', function () {
    $this->artisan('articles:export-zenn', ['slug' => 'missing', '--path' => $this->zennRepository])->assertFailed();

    expect(File::files($this->zennRepository.'/articles'))->toBeEmpty();
});

test('fails when the Zenn repository path is not usable', function () {
    createBilingualArticle('inkrail-sync', true);
    config(['articles.zenn_repository_path' => null]);

    $this->artisan('articles:export-zenn', ['slug' => 'inkrail-sync'])->assertFailed();
});
