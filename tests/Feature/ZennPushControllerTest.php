<?php

use App\Models\Activity;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

const ZENN_LISTING = 'api.github.com/repos/owner/zenn/contents/articles?*';
const ZENN_FILE = 'api.github.com/repos/owner/zenn/contents/articles/*';

beforeEach(function () {
    config([
        'articles.zenn_publishers' => ['publisher@example.com'],
        'services.github_zenn' => ['token' => 'secret-token', 'repository' => 'owner/zenn', 'branch' => 'main'],
    ]);
    Http::preventStrayRequests();
    $this->publisher = User::factory()->create(['email' => 'publisher@example.com']);
});

function createZennArticle(User $owner, bool $isPublished = true): Article
{
    $article = $owner->articles()->create(['slug' => 'inkrail-sync', 'is_published' => $isPublished]);
    $article->replaceTranslations([
        'en' => ['title' => 'English title', 'content' => 'English body'],
        'ja' => ['title' => '日本語の題名', 'content' => '日本語の本文'],
    ]);

    return $article;
}

function pushedRequests(): array
{
    return Http::recorded(fn (Request $request): bool => $request->method() === 'PUT')->map(fn (array $pair): Request => $pair[0])->values()->all();
}

test('commits the Japanese version to the Zenn repository and records the action', function () {
    $article = createZennArticle($this->publisher);
    Http::fake([ZENN_LISTING => Http::response([], 404), ZENN_FILE => Http::response([], 201)]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'success');

    $requests = pushedRequests();
    expect($requests)->toHaveCount(1);
    expect($requests[0]->url())->toMatch('#/contents/articles/inkrail-sync-[0-9a-f]{8}\.md$#');
    expect($requests[0]->hasHeader('Authorization', 'Bearer secret-token'))->toBeTrue();
    expect($requests[0]['branch'])->toBe('main');
    expect($requests[0]->data())->not->toHaveKey('sha');
    expect(base64_decode($requests[0]['content']))->toBe(
        "---\ntitle: \"日本語の題名\"\nemoji: \"📝\"\ntype: \"tech\"\ntopics: []\npublished: true\n---\n\n日本語の本文\n"
    );
    $event = Activity::inLog('security')->where('event', 'article.zenn_pushed')->sole();
    expect($event->subject_id)->toBe($article->id);
    expect($event->properties->all())->not->toHaveKey('content');
});

test('updates the existing Zenn file with its blob sha', function () {
    $article = createZennArticle($this->publisher);
    Http::fake([
        ZENN_LISTING => Http::response([['name' => 'inkrail-sync-0a1b2c3d.md', 'sha' => 'old-sha'], ['name' => 'other-article-ffffffff.md', 'sha' => 'x']]),
        ZENN_FILE => Http::response([], 200),
    ]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))->assertRedirect();

    $requests = pushedRequests();
    expect($requests)->toHaveCount(1);
    expect($requests[0]->url())->toEndWith('/contents/articles/inkrail-sync-0a1b2c3d.md');
    expect($requests[0]['sha'])->toBe('old-sha');
});

test('does not commit when the Zenn file already has the same content', function () {
    $article = createZennArticle($this->publisher);
    $markdown = "---\ntitle: \"日本語の題名\"\nemoji: \"📝\"\ntype: \"tech\"\ntopics: []\npublished: true\n---\n\n日本語の本文\n";
    Http::fake([
        ZENN_LISTING => Http::response([['name' => 'inkrail-sync-0a1b2c3d.md', 'sha' => sha1('blob '.strlen($markdown)."\0".$markdown)]]),
    ]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.message', __('Zenn is already up to date.'));

    expect(pushedRequests())->toBeEmpty();
});

test('sends an unpublished article as a Zenn draft', function () {
    $article = createZennArticle($this->publisher, isPublished: false);
    Http::fake([ZENN_LISTING => Http::response([], 404), ZENN_FILE => Http::response([], 201)]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))->assertRedirect();

    expect(base64_decode(pushedRequests()[0]['content']))->toContain("published: false\n");
});

test('forbids a user who is not a configured publisher', function () {
    $user = User::factory()->create(['email' => 'other@example.com']);
    $article = createZennArticle($user);

    $this->actingAs($user)->post(route('articles.zenn.push', $article))->assertForbidden();

    Http::assertNothingSent();
});

test("forbids a publisher from sending another user's article", function () {
    $article = createZennArticle(User::factory()->create());

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))->assertForbidden();

    Http::assertNothingSent();
});

test('redirects a guest to the login page', function () {
    $article = createZennArticle($this->publisher);

    $this->post(route('articles.zenn.push', $article))->assertRedirect(route('login'));

    Http::assertNothingSent();
});

test('reports an error and records the failure when GitHub rejects the commit', function () {
    $article = createZennArticle($this->publisher);
    Http::fake([ZENN_LISTING => Http::response([], 404), ZENN_FILE => Http::response(['message' => 'Bad credentials'], 401)]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect(Activity::inLog('security')->where('event', 'article.zenn_pushed')->exists())->toBeFalse();
    $failure = Activity::inLog('security')->where('event', 'article.zenn_push_failed')->sole();
    expect(json_encode($failure->properties))->not->toContain('secret-token');
});

test('reports an error without calling GitHub when the article has no Japanese version', function () {
    $article = $this->publisher->articles()->create(['slug' => 'english-only']);
    $article->replaceTranslations(['en' => ['title' => 'Only English', 'content' => 'Body']]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    Http::assertNothingSent();
});

test('reports an error without calling GitHub when the repository is not configured', function () {
    config(['services.github_zenn.token' => null]);
    $article = createZennArticle($this->publisher);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    Http::assertNothingSent();
});

test('shows the send action only to publishers', function () {
    config(['inertia.ssr.enabled' => false]);
    $article = createZennArticle($this->publisher);
    $stranger = User::factory()->create(['email' => 'other@example.com']);

    $this->actingAs($this->publisher)->get(route('articles.show', $article))
        ->assertInertia(fn (Assert $page) => $page->where('canPushToZenn', true));

    $strangerArticle = $stranger->articles()->create(['slug' => 'stranger-article']);
    $strangerArticle->replaceTranslations(['ja' => ['title' => 'x', 'content' => 'y']]);
    $this->actingAs($stranger)->get(route('articles.show', $strangerArticle))
        ->assertInertia(fn (Assert $page) => $page->where('canPushToZenn', false));
});
