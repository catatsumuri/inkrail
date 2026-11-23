<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

/** @return array{meta: array<string, list<string>>, canonical: string, title: string} */
function articleSocialHead(TestResponse $response): array
{
    $response->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $meta = [];
    foreach ($xpath->query('//head/meta[@property or @name]') as $node) {
        $key = $node->getAttribute('property') ?: $node->getAttribute('name');
        $meta[$key][] = $node->getAttribute('content');
    }

    return [
        'meta' => $meta,
        'canonical' => $xpath->evaluate('string(//head/link[@rel="canonical"]/@href)'),
        'title' => $xpath->evaluate('string(//head/title)'),
    ];
}

test('serves article cards in initial HTML without JavaScript or SSR', function () {
    $article = User::factory()->create()->articles()->create([
        'title' => 'Public story', 'content' => '**Intro** & [guide](https://example.com).',
        'slug' => 'public-story', 'is_published' => true, 'eyecatch' => 'cover.png',
    ]);
    $canonical = route('public.articles.show', $article->slug);

    $response = $this->get($canonical.'?utm_source=social');
    $head = articleSocialHead($response);

    expect($head['meta']['og:title'])->toBe(['Public story']);
    expect($head['meta']['og:type'])->toBe(['article']);
    expect($head['meta']['og:url'])->toBe([$canonical]);
    expect($head['canonical'])->toBe($canonical);
    expect($head['title'])->toBe('Public story - '.config('app.name'));
    expect($head['meta']['description'])->toBe(['Intro & guide.']);
    expect($head['meta']['og:description'])->toBe(['Intro & guide.']);
    expect($head['meta']['twitter:description'])->toBe(['Intro & guide.']);
    expect($head['meta']['og:image'])->toBe([url('/images/cover.png')]);
    expect($head['meta']['twitter:image'])->toBe([url('/images/cover.png')]);
    expect($head['meta']['og:image:alt'])->toBe(['Public story']);
    expect($head['meta']['twitter:card'])->toBe(['summary_large_image']);
    $response->assertInertia(fn (Assert $page) => $page->where('social.url', $canonical)->where('social.meta.og:image', url('/images/cover.png')));
});

test('uses a summary card without an image when the article has no cover', function () {
    $article = User::factory()->create()->articles()->create(['title' => 'Plain story', 'content' => '', 'is_published' => true]);

    $head = articleSocialHead($this->get(route('public.articles.show', $article->slug)));

    expect($head['meta']['twitter:card'])->toBe(['summary']);
    expect($head['meta'])->not->toHaveKeys(['og:image', 'twitter:image']);
    expect($head['meta']['description'])->toBe(['']);
});

test('uses the public HTTPS origin for canonical and image URLs', function () {
    User::factory()->create()->articles()->create([
        'title' => 'HTTPS story', 'slug' => 'https-story', 'is_published' => true, 'eyecatch' => 'cover.webp',
    ]);

    $head = articleSocialHead($this->get('https://inkrail.example/p/https-story?utm_source=social'));

    expect($head['canonical'])->toBe('https://inkrail.example/p/https-story');
    expect($head['meta']['og:image'])->toBe(['https://inkrail.example/images/cover.webp']);
});

test('escapes user supplied metadata instead of adding head elements', function () {
    $title = 'Title "><script>alert(1)</script>';
    $article = User::factory()->create()->articles()->create(['title' => $title, 'content' => 'Text "quoted" & safe.', 'is_published' => true]);

    $response = $this->get(route('public.articles.show', $article->slug));
    $head = articleSocialHead($response);

    expect($head['meta']['og:title'])->toBe([$title]);
    expect($head['meta']['description'])->toBe(['Text "quoted" & safe.']);
    $response->assertDontSee('<script>alert(1)</script>', false);
});

test('describes the resolved article language and bounds the summary', function () {
    app()->setLocale('ja');
    $article = User::factory()->create()->articles()->create(['is_published' => true]);
    $article->replaceTranslations(['en' => ['title' => 'English fallback', 'content' => str_repeat('A long sentence. ', 30)]]);

    $head = articleSocialHead($this->get(route('public.articles.show', $article->slug)));

    expect($head['meta']['og:title'])->toBe(['English fallback']);
    expect(mb_strlen($head['meta']['description'][0]))->toBeLessThanOrEqual(163);
    expect($head['meta']['description'][0])->toEndWith('...');
});

test('provides website cards on the public index with canonical pagination', function () {
    $head = articleSocialHead($this->get(route('public.articles.index', ['page' => 2, 'utm_source' => 'social'])));

    expect($head['meta']['og:type'])->toBe(['website']);
    expect($head['meta']['twitter:card'])->toBe(['summary']);
    expect($head['canonical'])->toBe(route('public.articles.index', ['page' => 2]));
});

test('does not expose draft metadata or add article cards to private pages', function () {
    $owner = User::factory()->create();
    $article = $owner->articles()->create(['title' => 'Private title', 'content' => 'Private body']);

    $this->get(route('public.articles.show', $article->slug))->assertNotFound()->assertDontSee('Private title');
    $head = articleSocialHead($this->actingAs($owner)->get(route('articles.show', $article)));

    expect($head['meta'])->not->toHaveKeys(['og:title', 'twitter:card']);
    expect($head['canonical'])->toBe('');
});
