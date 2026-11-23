<?php

use App\Models\Activity;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

const ZENN_READ = 'api.github.com/repos/owner/zenn/contents/articles/*.md?ref=main';
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
    Http::fake([ZENN_READ => Http::response([], 404), ZENN_FILE => Http::response([], 201)]);

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
        "---\ntitle: \"日本語の題名\"\nemoji: \"📝\"\ntype: \"tech\"\ntopics: []\npublished: true\ninkrail_id: \"{$article->sync_id}\"\n---\n\n日本語の本文\n"
    );
    $this->assertDatabaseHas('articles', [
        'id' => $article->id,
        'zenn_repository' => 'owner/zenn',
        'zenn_branch' => 'main',
        'zenn_path' => str_replace('https://api.github.com/repos/owner/zenn/contents/', '', $requests[0]->url()),
        'zenn_sha' => sha1('blob '.strlen(base64_decode($requests[0]['content']))."\0".base64_decode($requests[0]['content'])),
    ]);
    $event = Activity::inLog('security')->where('event', 'article.zenn_pushed')->sole();
    expect($event->subject_id)->toBe($article->id);
    expect($event->properties->all())->not->toHaveKey('content');
});

test('updates the existing Zenn file with its blob sha', function () {
    $article = createZennArticle($this->publisher);
    $article->forceFill([
        'zenn_repository' => 'owner/zenn', 'zenn_branch' => 'main',
        'zenn_path' => 'articles/inkrail-sync-0a1b2c3d.md', 'zenn_sha' => 'old-sha',
    ])->save();
    Http::fake([
        ZENN_READ => Http::response(['sha' => 'old-sha']),
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
    $markdown = "---\ntitle: \"日本語の題名\"\nemoji: \"📝\"\ntype: \"tech\"\ntopics: []\npublished: true\ninkrail_id: \"{$article->sync_id}\"\n---\n\n日本語の本文\n";
    $sha = sha1('blob '.strlen($markdown)."\0".$markdown);
    $article->forceFill([
        'zenn_repository' => 'owner/zenn', 'zenn_branch' => 'main',
        'zenn_path' => 'articles/inkrail-sync-0a1b2c3d.md', 'zenn_sha' => $sha,
    ])->save();
    Http::fake([
        ZENN_READ => Http::response(['sha' => $sha]),
    ]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.message', __('Zenn is already up to date.'));

    expect(pushedRequests())->toBeEmpty();
    $this->assertDatabaseHas('articles', ['id' => $article->id, 'zenn_path' => 'articles/inkrail-sync-0a1b2c3d.md', 'zenn_sha' => sha1('blob '.strlen($markdown)."\0".$markdown)]);
});

test('sends an unpublished article as a Zenn draft', function () {
    $article = createZennArticle($this->publisher, isPublished: false);
    Http::fake([ZENN_READ => Http::response([], 404), ZENN_FILE => Http::response([], 201)]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))->assertRedirect();

    expect(base64_decode(pushedRequests()[0]['content']))->toContain("published: false\n");
});

test('keeps the linked file and identity after the local slug changes', function () {
    $article = createZennArticle($this->publisher);
    $identity = $article->sync_id;
    Http::fake([
        ZENN_READ => Http::sequence()->push([], 404)->pushResponse(fn () => Http::response(['sha' => $article->fresh()->zenn_sha])),
        ZENN_FILE => Http::response([], 200),
    ]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertSessionHas('inertia.flash_data.toast.type', 'success');
    $article->refresh();
    $originalPath = $article->zenn_path;
    $originalSha = $article->zenn_sha;
    $article->update(['slug' => 'renamed-local-article']);
    $article->replaceTranslations(['ja' => ['title' => '更新した題名', 'content' => '更新した本文']]);

    $this->post(route('articles.zenn.push', $article))
        ->assertSessionHas('inertia.flash_data.toast.type', 'success');

    $requests = pushedRequests();
    expect($requests)->toHaveCount(2);
    expect($requests[1]->url())->toBe($requests[0]->url());
    expect($requests[1]['sha'])->toBe($originalSha);
    expect(base64_decode($requests[1]['content']))->toContain("inkrail_id: \"{$identity}\"", '更新した本文');
    $this->assertDatabaseHas('articles', ['id' => $article->id, 'sync_id' => $identity, 'zenn_path' => $originalPath]);
});

test('refuses to overwrite a linked file changed or deleted on GitHub', function (?string $remoteSha) {
    $article = createZennArticle($this->publisher);
    $article->forceFill([
        'zenn_repository' => 'owner/zenn', 'zenn_branch' => 'main',
        'zenn_path' => 'articles/inkrail-sync-0a1b2c3d.md', 'zenn_sha' => 'last-synced-sha',
    ])->save();
    Http::fake([ZENN_READ => Http::response(['sha' => $remoteSha], $remoteSha === null ? 404 : 200)]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect(pushedRequests())->toBeEmpty();
    $this->assertDatabaseHas('articles', ['id' => $article->id, 'zenn_sha' => 'last-synced-sha']);
    expect(Activity::inLog('security')->where('event', 'article.zenn_pushed')->exists())->toBeFalse();
    expect(Activity::inLog('security')->where('event', 'article.zenn_push_failed')->count())->toBe(1);
})->with(['changed' => 'externally-edited-sha', 'deleted' => null]);

test('refuses to send a linked article to a different repository or branch', function (string $setting, string $value) {
    $article = createZennArticle($this->publisher);
    $article->forceFill([
        'zenn_repository' => 'owner/zenn', 'zenn_branch' => 'main',
        'zenn_path' => 'articles/inkrail-sync-0a1b2c3d.md', 'zenn_sha' => 'old-sha',
    ])->save();
    config(["services.github_zenn.{$setting}" => $value]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    Http::assertNothingSent();
    $this->assertDatabaseHas('articles', ['id' => $article->id, 'zenn_repository' => 'owner/zenn', 'zenn_branch' => 'main', 'zenn_sha' => 'old-sha']);
})->with(['repository' => ['repository', 'owner/another-repo'], 'branch' => ['branch', 'develop']]);

test('detects remote changes even when the latest remote content matches the local content', function () {
    $article = createZennArticle($this->publisher);
    $article->forceFill([
        'zenn_repository' => 'owner/zenn', 'zenn_branch' => 'main',
        'zenn_path' => 'articles/inkrail-sync-0a1b2c3d.md', 'zenn_sha' => 'previous-baseline-sha',
    ])->save();
    $markdown = "---\ntitle: \"日本語の題名\"\nemoji: \"📝\"\ntype: \"tech\"\ntopics: []\npublished: true\ninkrail_id: \"{$article->sync_id}\"\n---\n\n日本語の本文\n";
    Http::fake([ZENN_READ => Http::response(['sha' => sha1('blob '.strlen($markdown)."\0".$markdown)])]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect(pushedRequests())->toBeEmpty();
    $this->assertDatabaseHas('articles', ['id' => $article->id, 'zenn_sha' => 'previous-baseline-sha']);
    expect(Activity::inLog('security')->where('event', 'article.zenn_pushed')->exists())->toBeFalse();
});

test('keeps the baseline when GitHub rejects an update after the file was checked', function () {
    $article = createZennArticle($this->publisher);
    $article->forceFill([
        'zenn_repository' => 'owner/zenn', 'zenn_branch' => 'main',
        'zenn_path' => 'articles/inkrail-sync-0a1b2c3d.md', 'zenn_sha' => 'last-synced-sha',
    ])->save();
    Http::fake([
        ZENN_READ => Http::response(['sha' => 'last-synced-sha']),
        ZENN_FILE => Http::response(['message' => 'SHA mismatch'], 409),
    ]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    $this->assertDatabaseHas('articles', ['id' => $article->id, 'zenn_sha' => 'last-synced-sha']);
    expect(Activity::inLog('security')->where('event', 'article.zenn_pushed')->exists())->toBeFalse();
});

test('does not adopt a colliding legacy export even when its content matches', function () {
    $article = createZennArticle($this->publisher);
    $legacyMarkdown = "---\ntitle: \"日本語の題名\"\nemoji: \"📝\"\ntype: \"tech\"\ntopics: []\npublished: true\n---\n\n日本語の本文\n";
    $legacySha = sha1('blob '.strlen($legacyMarkdown)."\0".$legacyMarkdown);
    Http::fake([
        ZENN_READ => Http::sequence()->push(['sha' => $legacySha])->push([], 404),
        ZENN_FILE => Http::response([], 200),
    ]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertSessionHas('inertia.flash_data.toast.type', 'success');

    $requests = pushedRequests();
    expect($requests)->toHaveCount(1);
    expect($requests[0]->data())->not->toHaveKey('sha');
    expect(base64_decode($requests[0]['content']))->toContain("inkrail_id: \"{$article->sync_id}\"");
    $reads = Http::recorded(fn (Request $request): bool => $request->method() === 'GET')->values();
    expect($reads)->toHaveCount(2);
    expect($requests[0]->url())->not->toBe(strtok($reads[0][0]->url(), '?'));
    expect($requests[0]->url())->toBe(strtok($reads[1][0]->url(), '?'));
});

test('stops without writing after repeated filename collisions', function () {
    $article = createZennArticle($this->publisher);
    Http::fake([ZENN_READ => Http::response(['sha' => 'unknown-content-sha'])]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect(pushedRequests())->toBeEmpty();
    Http::assertSentCount(10);
    $this->assertDatabaseHas('articles', ['id' => $article->id, 'zenn_path' => null, 'zenn_sha' => null]);
});

test('a new article gets its own destination when another article has the same old slug', function () {
    $existingArticle = $this->publisher->articles()->create(['slug' => 'inkrail-sync']);
    $existingArticle->forceFill([
        'zenn_repository' => 'owner/zenn', 'zenn_branch' => 'main',
        'zenn_path' => 'articles/inkrail-sync-0a1b2c3d.md', 'zenn_sha' => 'old-sha',
    ])->save();
    $existingArticle->update(['slug' => 'renamed-original']);
    $article = createZennArticle($this->publisher);
    Http::fake([ZENN_READ => Http::response([], 404), ZENN_FILE => Http::response([], 201)]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertSessionHas('inertia.flash_data.toast.type', 'success');

    expect(pushedRequests())->toHaveCount(1);
    expect($article->fresh()->zenn_path)->not->toBe($existingArticle->zenn_path);
    expect($article->sync_id)->not->toBe($existingArticle->sync_id);
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
    Http::fake([ZENN_READ => Http::response([], 404), ZENN_FILE => Http::response(['message' => 'Bad credentials'], 401)]);

    $this->actingAs($this->publisher)->post(route('articles.zenn.push', $article))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect(Activity::inLog('security')->where('event', 'article.zenn_pushed')->exists())->toBeFalse();
    $failure = Activity::inLog('security')->where('event', 'article.zenn_push_failed')->sole();
    expect(json_encode($failure->properties))->not->toContain('secret-token');
    $this->assertDatabaseHas('articles', ['id' => $article->id, 'zenn_path' => null, 'zenn_sha' => null]);
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
