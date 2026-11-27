<?php

use App\Models\ArticleNamespace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the feed is valid XML and contains only public articles with stable identifiers', function () {
    config(['articles.public_index_enabled' => true]);
    $owner = User::factory()->create();
    $article = $owner->articles()->create(['title' => 'A & <B>', 'content' => 'Hello & world', 'is_published' => true]);
    $owner->articles()->create(['title' => 'Secret draft', 'content' => 'Secret']);
    $owner->articles()->create(['title' => 'Future', 'is_published' => true, 'published_at' => now()->addDay()]);

    $response = $this->get(route('public.articles.feed'))->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse();
    expect((string) $xml->channel->item->title)->toBe('A & <B>');
    expect($xml->channel->item)->toHaveCount(1);
    $guid = (string) $xml->channel->item->guid;
    $article->update(['slug' => 'changed-slug', 'title' => 'Changed']);
    $updated = simplexml_load_string($this->get(route('public.articles.feed'))->getContent());
    expect((string) $updated->channel->item->guid)->toBe($guid);
});

test('feed language is explicit and independent of session language', function () {
    config(['articles.public_index_enabled' => true, 'app.locale' => 'en']);
    $this->withSession(['locale' => 'ja'])->get('/rss.xml')->assertOk()->assertSee('<language>en</language>', false);
    $this->get('/rss.xml?lang=ja')->assertOk()->assertSee('<language>ja</language>', false);
    $this->get('/rss.xml?lang=invalid')->assertNotFound();
    $this->get('/rss.xml?lang[]=en')->assertNotFound();
});

test('disabling the public index disables the feed and discovery links', function () {
    config(['articles.public_index_enabled' => false]);
    $this->get('/rss.xml')->assertNotFound();
    $this->get('/')->assertDontSee('application/rss+xml', false);
});

test('the feed limits results and orders by effective publication date', function () {
    config(['articles.public_index_enabled' => true]);
    $owner = User::factory()->create();
    for ($i = 0; $i < 22; $i++) {
        $owner->articles()->create(['title' => "Article $i", 'is_published' => true, 'published_at' => now()->subDays(23 - $i)]);
    }
    $owner->articles()->create(['title' => 'No publication date', 'is_published' => true]);
    $xml = simplexml_load_string($this->get('/rss.xml')->assertOk()->getContent());
    expect($xml->channel->item)->toHaveCount(20);
    expect((string) $xml->channel->item[0]->title)->toBe('No publication date');
    expect((string) $xml->channel->item[1]->title)->toBe('Article 21');
});

test('the feed respects demo ownership and excludes sandbox articles', function () {
    config(['articles.public_index_enabled' => true, 'app.demo_mode' => true]);
    $owner = User::factory()->create(['is_showcase_owner' => true]);
    $other = User::factory()->create();
    $owner->articles()->create(['title' => 'Showcase', 'is_published' => true]);
    $other->articles()->create(['title' => 'Other owner', 'is_published' => true]);
    $sandbox = ArticleNamespace::query()->firstOrCreate(['slug' => 'sandbox'], ['name' => 'Sandbox']);
    $article = $owner->articles()->make(['title' => 'Sandbox', 'is_published' => true]);
    $article->namespace()->associate($sandbox);
    $article->save();
    $xml = simplexml_load_string($this->get('/rss.xml')->assertOk()->getContent());
    expect($xml->channel->item)->toHaveCount(1);
    expect((string) $xml->channel->item->title)->toBe('Showcase');
});

test('translated feed links match the resolved language and XML removes control characters', function () {
    config(['articles.public_index_enabled' => true, 'app.locale' => 'en']);
    $article = User::factory()->create()->articles()->create(['title' => "English & title\x01", 'content' => 'English body', 'is_published' => true]);
    $article->replaceTranslations(['ja' => ['title' => '日本語', 'content' => '本文']]);
    $xml = simplexml_load_string($this->get('/rss.xml?lang=ja')->assertOk()->getContent());
    expect((string) $xml->channel->item->title)->toBe('日本語');
    expect((string) $xml->channel->item->link)->toContain('lang=ja');
    $english = simplexml_load_string($this->get('/rss.xml?lang=en')->getContent());
    expect((string) $english->channel->item->title)->toBe('English & title');
    $this->get('/')->assertOk()->assertSee('application/rss+xml', false)->assertSee('rss.xml?lang=ja', false);
});
