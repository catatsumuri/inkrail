<?php

use App\Actions\ImportSandboxDocuments;
use App\Actions\Zenn\ImportZennArticles;
use App\Actions\Zenn\PushArticleToZenn;
use App\Actions\Zenn\RenderZennArticle;
use App\Actions\Zenn\WithZennSyncLock;
use App\Exceptions\ZennImportException;
use App\Exceptions\ZennPushException;
use App\Models\Activity;
use App\Models\ArticleNamespace;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Yaml\Yaml;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config([
        'articles.zenn_publishers' => ['publisher'],
        'services.github_zenn' => ['repository' => 'owner/zenn', 'branch' => 'main', 'token' => 'secret-token'],
        'inertia.ssr.enabled' => false,
    ]);
    Http::preventStrayRequests();
});

function zennImportMarkdown(string $title = '日本語の記事', string $body = '本文', ?string $identity = null): string
{
    $id = $identity === null ? '' : "inkrail_id: \"{$identity}\"\n";

    return "---\ntitle: \"{$title}\"\nemoji: \"🐱\"\ntype: idea\ntopics:\n  - laravel\npublished: false\n{$id}custom_field: keep-me\n---\n\n{$body}\n";
}

/** @param array<string, string> $documents */
function fakeZennImport(array $documents): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $entries = [];
    $responses = [];
    foreach ($documents as $path => $content) {
        $sha = sha1('blob '.strlen($content)."\0".$content);
        $entries[] = ['path' => $path, 'type' => 'blob', 'mode' => '100644', 'sha' => $sha, 'size' => strlen($content)];
        $responses["api.github.com/repos/owner/zenn/git/blobs/{$sha}"] = Http::response(['encoding' => 'base64', 'content' => base64_encode($content)]);
    }
    $responses['api.github.com/repos/owner/zenn/git/trees/main?recursive=1'] = Http::response(['sha' => str_repeat('a', 40), 'truncated' => false, 'tree' => $entries]);
    Http::fake($responses);
}

test('imports a legacy article through the dashboard without writing to GitHub and repeated imports do not duplicate it', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown()]);

    $this->actingAs($owner)->post(route('zenn.import'))->assertRedirect(route('dashboard'))
        ->assertSessionHas('zenn_import.created', 1);
    $article = $owner->articles()->sole();
    $identity = $article->sync_id;
    $this->post(route('zenn.import'))->assertSessionHas('zenn_import.unchanged', 1);

    $this->assertDatabaseCount('articles', 1);
    $this->assertDatabaseCount('article_revisions', 1);
    $this->assertDatabaseHas('article_translations', ['article_id' => $article->id, 'locale' => 'ja', 'title' => '日本語の記事', 'content' => "本文\n"]);
    expect($article->fresh())->sync_id->toBe($identity)->zenn_repository->toBe('owner/zenn')->zenn_branch->toBe('main')->zenn_path->toBe('articles/original-article.md');
    expect($article->zenn_frontmatter)->toMatchArray(['emoji' => '🐱', 'type' => 'idea', 'topics' => ['laravel'], 'custom_field' => 'keep-me']);
    expect($article->zenn_local_sha)->toBe(app(RenderZennArticle::class)->localSha($article));
    Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
    $event = Activity::inLog('security')->where('event', 'articles.zenn_imported')->firstOrFail();
    expect($event->causer_id)->toBe($owner->id);
    expect($event->properties->all())->not->toHaveKeys(['content', 'token', 'frontmatter']);
});

test('restores a remote UUID and uses a distinct local slug when that slug is already taken', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    $unrelated = User::factory()->create()->articles()->create(['slug' => 'original-article', 'title' => 'Keep me']);
    $identity = 'e306984e-a4ed-471a-a60e-41e7f20b637c';
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown(identity: $identity)]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['created'])->toBe(1);
    expect($owner->articles()->sole())->sync_id->toBe($identity)->slug->not->toBe('original-article');
    expect($unrelated->fresh())->title->toBe('Keep me');
});

test('updates a clean imported article while keeping its local slug and English translation', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown()]);
    app(ImportZennArticles::class)($owner);
    $article = $owner->articles()->sole();
    $article->update(['slug' => 'local-friendly-slug']);
    $article->replaceTranslations(['en' => ['title' => 'English title', 'content' => 'Keep English']]);
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown(title: '更新された題名', body: 'リモートの更新')]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['updated'])->toBe(1);
    expect($article->fresh())->slug->toBe('local-friendly-slug');
    $this->assertDatabaseHas('article_translations', ['article_id' => $article->id, 'locale' => 'en', 'content' => 'Keep English']);
    $this->assertDatabaseHas('article_translations', ['article_id' => $article->id, 'locale' => 'ja', 'title' => '更新された題名', 'content' => "リモートの更新\n"]);
});

test('keeps local edits and their sync baseline when the remote article also changes', function (bool $matchingContent) {
    $owner = User::factory()->withUsername('publisher')->create();
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown()]);
    app(ImportZennArticles::class)($owner);
    $article = $owner->articles()->sole();
    $baseline = $article->zenn_sha;
    $localBaseline = $article->zenn_local_sha;
    $article->replaceTranslations(['ja' => ['title' => 'Local title', 'content' => "Local body\n"]]);
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown(title: $matchingContent ? 'Local title' : 'Remote title', body: $matchingContent ? 'Local body' : 'Remote body')]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['conflict'])->toBe(1);
    $this->assertDatabaseHas('articles', ['id' => $article->id, 'zenn_sha' => $baseline, 'zenn_local_sha' => $localBaseline]);
    $this->assertDatabaseHas('article_translations', ['article_id' => $article->id, 'locale' => 'ja', 'title' => 'Local title', 'content' => "Local body\n"]);
})->with(['different content' => false, 'coincidentally matching content' => true]);

test('an unchanged remote article does not reset local edits or mark them as synced', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown()]);
    app(ImportZennArticles::class)($owner);
    $article = $owner->articles()->sole();
    $baseline = $article->zenn_local_sha;
    $article->replaceTranslations(['ja' => ['title' => 'Local', 'content' => 'Changed locally']]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['unchanged'])->toBe(1);
    expect($result['items'][0]['status'])->toBe('unchanged');
    expect($article->fresh()->zenn_local_sha)->toBe($baseline);
    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('zenn.articles.data.0.locallyChanged', true)->missing('zenn.token'));
});

test('the same repository cannot be pushed or imported while a sync is running', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    $article = $owner->articles()->create(['slug' => 'local-article']);
    $article->replaceTranslations(['ja' => ['title' => 'Title', 'content' => 'Body']]);

    app(WithZennSyncLock::class)(function () use ($owner, $article): void {
        expect(fn () => app(ImportZennArticles::class)($owner))->toThrow(ZennImportException::class);
        expect(fn () => app(PushArticleToZenn::class)($article))->toThrow(ZennPushException::class);
    });

    Http::assertNothingSent();
    $this->assertDatabaseHas('articles', ['id' => $article->id, 'zenn_path' => null, 'zenn_sha' => null]);
});

test('long syncs renew their lease and continue excluding another sync', function () {
    $this->freezeTime();

    $result = app(WithZennSyncLock::class)(function (Closure $checkLock): string {
        for ($step = 0; $step < 4; $step++) {
            $this->travel(50)->seconds();
            $checkLock();
            expect(fn () => app(WithZennSyncLock::class)(fn (): bool => true))->toThrow(LockTimeoutException::class);
        }

        return 'completed';
    });

    expect($result)->toBe('completed');
    expect(app(WithZennSyncLock::class)(fn (): bool => true))->toBeTrue();
});

test('a failed sync releases its repository lock', function () {
    expect(fn () => app(WithZennSyncLock::class)(function (): void {
        throw new RuntimeException('Operation failed');
    }))->toThrow(RuntimeException::class, 'Operation failed');

    expect(app(WithZennSyncLock::class)(fn (): bool => true))->toBeTrue();
});

test('an import whose lease expires during a GitHub request stops before saving', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    $this->freezeTime();
    Http::fake(['api.github.com/repos/owner/zenn/git/trees/main?recursive=1' => function () {
        $this->travel(121)->seconds();

        return Http::response(['truncated' => false, 'tree' => []]);
    }]);

    $this->actingAs($owner)->post(route('zenn.import'))->assertSessionHas('inertia.flash_data.toast.message', __('The repository is busy or the sync lock was lost. Try again shortly.'));

    $this->assertDatabaseCount('articles', 0);
    expect(Activity::inLog('security')->where('event', 'articles.zenn_imported')->exists())->toBeFalse();
});

test('does not claim an identity belonging to another user', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    $otherArticle = User::factory()->create()->articles()->create(['title' => 'Private article']);
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown(identity: $otherArticle->sync_id)]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['conflict'])->toBe(1);
    expect($owner->articles()->count())->toBe(0);
    expect($otherArticle->fresh())->title->toBe('Private article')->zenn_path->toBeNull();
});

test('rejects a changed UUID at an already linked remote path', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown()]);
    app(ImportZennArticles::class)($owner);
    $article = $owner->articles()->sole();
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown(identity: 'e306984e-a4ed-471a-a60e-41e7f20b637c')]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['conflict'])->toBe(1);
    $this->assertDatabaseHas('articles', ['id' => $article->id, 'sync_id' => $article->sync_id, 'zenn_sha' => $article->zenn_sha]);
});

test('tracks a renamed remote file by UUID while preserving its local identity', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    $markdown = zennImportMarkdown(identity: 'e306984e-a4ed-471a-a60e-41e7f20b637c');
    fakeZennImport(['articles/original-article.md' => $markdown]);
    app(ImportZennArticles::class)($owner);
    $article = $owner->articles()->sole();
    fakeZennImport(['articles/renamed-article.md' => $markdown]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['updated'])->toBe(1);
    $this->assertDatabaseCount('articles', 1);
    $this->assertDatabaseHas('articles', ['id' => $article->id, 'slug' => $article->slug, 'sync_id' => $article->sync_id, 'zenn_path' => 'articles/renamed-article.md']);
});

test('rejects duplicate remote UUIDs instead of letting import order choose a file', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    $markdown = zennImportMarkdown(identity: 'e306984e-a4ed-471a-a60e-41e7f20b637c');
    fakeZennImport(['articles/original-article.md' => $markdown, 'articles/duplicate-article.md' => $markdown]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['invalid'])->toBe(2);
    $this->assertDatabaseCount('articles', 0);
});

test('skips invalid frontmatter without importing a partially parsed article', function (string $markdown) {
    $owner = User::factory()->withUsername('publisher')->create();
    fakeZennImport(['articles/original-article.md' => $markdown]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['invalid'])->toBe(1);
    $this->assertDatabaseCount('articles', 0);
})->with([
    'missing header' => 'Plain markdown',
    'malformed YAML' => "---\ntitle: [unclosed\npublished: true\n---\nBody",
    'missing title' => "---\npublished: false\n---\nBody",
    'string publication state' => "---\ntitle: Test\npublished: 'false'\n---\nBody",
    'invalid UUID' => "---\ntitle: Test\npublished: false\ninkrail_id: wrong\n---\nBody",
    'non-JSON numeric value' => "---\ntitle: Test\npublished: false\ncustom_field: .inf\n---\nBody",
]);

test('rejects a truncated GitHub tree before changing local articles', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    Http::fake(['api.github.com/repos/owner/zenn/git/trees/main?recursive=1' => Http::response(['truncated' => true, 'tree' => []])]);

    $this->actingAs($owner)->post(route('zenn.import'))->assertSessionHas('inertia.flash_data.toast.type', 'error');

    $this->assertDatabaseCount('articles', 0);
    expect(Activity::inLog('security')->where('event', 'articles.zenn_imported')->exists())->toBeFalse();
});

test('the import command requires an explicit existing owner', function () {
    $this->artisan('articles:import-zenn')->assertFailed();

    Http::assertNothingSent();
});

test('does not import partial data if fetching a later blob fails', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    $content = zennImportMarkdown();
    $sha = sha1('blob '.strlen($content)."\0".$content);
    $missingSha = str_repeat('b', 40);
    Http::fake([
        'api.github.com/repos/owner/zenn/git/trees/main?recursive=1' => Http::response(['truncated' => false, 'tree' => [
            ['path' => 'articles/first-article.md', 'sha' => $sha, 'type' => 'blob', 'mode' => '100644', 'size' => strlen($content)],
            ['path' => 'articles/second-article.md', 'sha' => $missingSha, 'type' => 'blob', 'mode' => '100644', 'size' => 100],
        ]]),
        "api.github.com/repos/owner/zenn/git/blobs/{$sha}" => Http::response(['encoding' => 'base64', 'content' => base64_encode($content)]),
        "api.github.com/repos/owner/zenn/git/blobs/{$missingSha}" => Http::response(['message' => 'Unavailable'], 503),
    ]);

    $this->actingAs($owner)->post(route('zenn.import'))->assertSessionHas('inertia.flash_data.toast.type', 'error');

    $this->assertDatabaseCount('articles', 0);
    expect(Activity::inLog('security')->where('event', 'articles.zenn_imported')->exists())->toBeFalse();
});

test('does not contact GitHub when its token is missing', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    config(['services.github_zenn.token' => null]);

    $this->actingAs($owner)->post(route('zenn.import'))->assertSessionHas('inertia.flash_data.toast.type', 'error');

    Http::assertNothingSent();
    $this->assertDatabaseCount('articles', 0);
});

test('does not move a known identity into a different repository', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    $article = $owner->articles()->create(['title' => 'Keep me']);
    $article->forceFill(['zenn_repository' => 'other/repository', 'zenn_branch' => 'main', 'zenn_path' => 'articles/original-article.md', 'zenn_sha' => 'old-sha'])->save();
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown(identity: $article->sync_id)]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['conflict'])->toBe(1);
    $this->assertDatabaseHas('articles', ['id' => $article->id, 'zenn_repository' => 'other/repository', 'zenn_sha' => 'old-sha']);
});

test('the import command restores articles for a configured publisher', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown()]);

    $this->artisan('articles:import-zenn', ['--user' => $owner->username])->assertSuccessful();

    $this->assertDatabaseCount('articles', 1);
    expect($owner->articles()->sole()->zenn_path)->toBe('articles/original-article.md');
});

test('only verified configured publishers can import', function (string $kind) {
    $user = match ($kind) {
        'unverified' => User::factory()->withUsername('publisher')->unverified()->create(),
        'sandbox' => User::factory()->withUsername('publisher')->create(['is_sandbox' => true]),
        default => User::factory()->create(),
    };

    $this->artisan('articles:import-zenn', ['--user' => $user->username ?? 'nobody'])->assertFailed();

    Http::assertNothingSent();
})->with(['unverified', 'sandbox', 'unlisted']);

test('forbids HTTP imports from a user outside the publisher list', function () {
    $this->actingAs(User::factory()->create())->post(route('zenn.import'))->assertForbidden();

    Http::assertNothingSent();
});

test('guests cannot import articles', function () {
    $this->post(route('zenn.import'))->assertRedirect(route('login'));

    Http::assertNothingSent();
});

test('the dashboard does not disclose repository settings to other users', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('zenn', null));

    Http::assertNothingSent();
});

test('sending an imported article uses its original remote path and preserves frontmatter', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown()]);
    app(ImportZennArticles::class)($owner);
    $article = $owner->articles()->sole();
    $baseline = $article->zenn_sha;
    Http::fake([
        'api.github.com/repos/owner/zenn/contents/articles/original-article.md?ref=main' => Http::response(['sha' => $baseline]),
        'api.github.com/repos/owner/zenn/contents/articles/original-article.md' => Http::response([], 200),
    ]);

    $this->actingAs($owner)->post(route('articles.zenn.push', $article))->assertSessionHas('inertia.flash_data.toast.type', 'success');

    $put = Http::recorded(fn (Request $request): bool => $request->method() === 'PUT')->sole()[0];
    expect($put['sha'])->toBe($baseline);
    preg_match('/\A---\n(.*?)\n---/s', base64_decode($put['content']), $matches);
    expect(Yaml::parse($matches[1]))->toMatchArray(['emoji' => '🐱', 'type' => 'idea', 'topics' => ['laravel'], 'custom_field' => 'keep-me', 'inkrail_id' => $article->sync_id]);
    expect($article->fresh()->zenn_local_sha)->toBe($article->fresh()->zenn_sha);
});

test('restores namespaces from frontmatter and preserves them when the remote field disappears', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    $document = str_replace('custom_field:', "namespace: guides\ncustom_field:", zennImportMarkdown());
    fakeZennImport(['articles/namespaced-article.md' => $document]);

    app(ImportZennArticles::class)($owner);

    $article = $owner->articles()->sole();
    expect($article->namespace->slug)->toBe('guides');
    expect(app(RenderZennArticle::class)($article))->toContain('namespace: "guides"');
    fakeZennImport(['articles/namespaced-article.md' => zennImportMarkdown(body: 'Updated remotely')]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['updated'])->toBe(1);
    expect($article->fresh()->namespace->slug)->toBe('guides');
    expect($article->fresh()->zenn_local_sha)->toBe(app(RenderZennArticle::class)->localSha($article->fresh()));
});

test('uses explicit null frontmatter to remove namespace membership without deleting the namespace', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    fakeZennImport(['articles/namespaced-article.md' => str_replace('custom_field:', "namespace: guides\ncustom_field:", zennImportMarkdown())]);
    app(ImportZennArticles::class)($owner);
    $article = $owner->articles()->sole();
    $namespace = $article->namespace;
    fakeZennImport(['articles/namespaced-article.md' => str_replace('custom_field:', "namespace: null\ncustom_field:", zennImportMarkdown())]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['updated'])->toBe(1);
    expect($article->fresh()->namespace)->toBeNull();
    $this->assertModelExists($namespace);
});

test('does not overwrite a local namespace change during import', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    fakeZennImport(['articles/namespaced-article.md' => zennImportMarkdown()]);
    app(ImportZennArticles::class)($owner);
    $article = $owner->articles()->sole();
    $namespace = ArticleNamespace::factory()->create();
    $article->namespace()->associate($namespace);
    $article->save();
    fakeZennImport(['articles/namespaced-article.md' => zennImportMarkdown(body: 'Remote change')]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['conflict'])->toBe(1);
    expect($article->fresh()->namespace->is($namespace))->toBeTrue();
});

test('streams import progress and the final result over server-sent events', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown()]);

    $content = $this->actingAs($owner)->withHeader('Accept', 'text/event-stream')
        ->post(route('zenn.import'))->assertOk()->streamedContent();

    expect($content)->toContain('event: progress', 'Reading article :current of :total.', 'event: result', '"status":"imported"', '"created":1')
        ->not->toContain('secret-token', 'Authorization');
    expect($owner->articles()->count())->toBe(1);
});

test('streams a terminal failure when the import cannot read GitHub', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response([], 403)]);

    $content = $this->actingAs($owner)->withHeader('Accept', 'text/event-stream')
        ->post(route('zenn.import'))->assertOk()->streamedContent();

    expect($content)->toContain('"status":"failed"')->not->toContain('secret-token');
    expect(Activity::inLog('security')->where('event', 'articles.zenn_import_failed')->count())->toBe(1);
});

test('regular article imports also import public repository images without importing sandbox documents', function () {
    Storage::fake('public');
    config(['articles.image_disk' => 'public']);
    $owner = User::factory()->withUsername('publisher')->create();
    $image = UploadedFile::fake()->image('diagram.png')->get();
    $article = zennImportMarkdown(body: '![Diagram](/images/nested/diagram.png)');
    fakeZennImport([
        'articles/original-article.md' => $article,
        'sandbox/note.md' => '# Private note',
        'images/nested/diagram.png' => $image,
        'images/unreferenced.png' => $image,
    ]);

    $this->actingAs($owner)->post(route('zenn.import'))->assertRedirect(route('dashboard'))
        ->assertSessionHas('zenn_import.created', 3);

    expect($owner->articles()->count())->toBe(1);
    expect($owner->articles()->sole()->isSandbox())->toBeFalse();
    Storage::disk('public')->assertExists(['repository-images/nested/diagram.png', 'repository-images/unreferenced.png']);
    auth()->logout();
    expect($this->get('/images/nested/diagram.png')->assertOk()->streamedContent())->toBe($image);
    Http::assertSentCount(4);
    Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
});

test('regular imports update shared images when the article is unchanged and sandbox imports see the update', function () {
    Storage::fake('public');
    config(['articles.image_disk' => 'public']);
    $owner = User::factory()->withUsername('publisher')->create();
    $first = UploadedFile::fake()->image('diagram.png', 2, 2)->get();
    $second = UploadedFile::fake()->image('diagram.png', 3, 2)->get();
    $files = ['articles/original-article.md' => zennImportMarkdown(), 'images/diagram.png' => $first];
    fakeZennImport($files);
    expect(app(ImportZennArticles::class)($owner)['created'])->toBe(2);
    fakeZennImport($files);
    expect(app(ImportZennArticles::class)($owner)['unchanged'])->toBe(2);
    fakeZennImport([...$files, 'images/diagram.png' => $second]);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['updated'])->toBe(1);
    expect($result['unchanged'])->toBe(1);
    expect($this->get('/images/diagram.png')->assertOk()->streamedContent())->toBe($second);
    expect(app(ImportSandboxDocuments::class)($owner)['unchanged'])->toBe(1);
    expect($owner->articles()->count())->toBe(1);
    Http::assertSentCount(5);
});

test('regular imports skip invalid images while importing valid articles', function () {
    Storage::fake('public');
    config(['articles.image_disk' => 'public']);
    $owner = User::factory()->withUsername('publisher')->create();
    $original = UploadedFile::fake()->image('diagram.png')->get();
    Storage::disk('public')->put('repository-images/diagram.png', $original);
    fakeZennImport(['articles/original-article.md' => zennImportMarkdown(), 'images/diagram.png' => '<html>Not an image</html>']);

    $result = app(ImportZennArticles::class)($owner);

    expect($result['created'])->toBe(1);
    expect($result['invalid'])->toBe(1);
    expect($owner->articles()->count())->toBe(1);
    expect(Storage::disk('public')->get('repository-images/diagram.png'))->toBe($original);
    Http::assertSentCount(3);
});

test('regular streaming imports report image progress and results', function () {
    Storage::fake('public');
    config(['articles.image_disk' => 'public']);
    $owner = User::factory()->withUsername('publisher')->create();
    fakeZennImport(['images/diagram.png' => UploadedFile::fake()->image('diagram.png')->get()]);

    $content = $this->actingAs($owner)->withHeader('Accept', 'text/event-stream')
        ->post(route('zenn.import'))->assertOk()->streamedContent();

    expect($content)->toContain('Reading image :current of :total.', '"status":"imported"', '"created":1')->not->toContain('secret-token');
    Storage::disk('public')->assertExists('repository-images/diagram.png');
    Http::assertSentCount(2);
});
