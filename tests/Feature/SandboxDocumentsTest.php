<?php

use App\Actions\ImportSandboxDocuments;
use App\Actions\Zenn\RenderZennArticle;
use App\Models\ArticleNamespace;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

uses(LazilyRefreshDatabase::class);

test('article lists and topic filters keep sandbox documents separate', function () {
    $owner = User::factory()->create();
    $regular = $owner->articles()->create(['title' => 'Regular', 'topics' => ['regular', 'shared']]);
    $namespaced = $owner->articles()->create(['title' => 'Other namespace', 'topics' => ['named']]);
    $namespaced->namespace()->associate(ArticleNamespace::factory()->create());
    $namespaced->save();
    $sandbox = $owner->articles()->create(['title' => 'Sandbox', 'topics' => ['sandbox-only', 'shared']]);
    $sandbox->namespace()->associate(ArticleNamespace::where('slug', 'sandbox')->sole());
    $sandbox->save();
    User::factory()->create()->articles()->create(['title' => 'Other owner', 'topics' => ['other-owner']]);

    $this->actingAs($owner)->get(route('articles.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('selectedNamespace', null)->where('articles.total', 2)
        ->where('articles.data.0.id', $namespaced->id)->where('articles.data.1.id', $regular->id)
        ->where('topics', ['named', 'regular', 'shared']));

    $this->get(route('articles.index', ['topic' => 'shared']))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('articles.total', 1)->where('articles.data.0.id', $regular->id));
    $this->get(route('articles.index', ['topic' => 'sandbox-only']))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('articles.total', 0));

    $this->get(route('articles.index', ['namespace' => 'sandbox']))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('selectedNamespace', 'sandbox')->where('articles.total', 1)
        ->where('articles.data.0.id', $sandbox->id)->where('articles.data.0.is_sandbox', true)
        ->where('topics', ['sandbox-only', 'shared']));
});

test('sandbox selection is preserved through creation and stores one private body', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->get(route('articles.create', ['namespace' => 'sandbox']))->assertInertia(fn (AssertableInertia $page) => $page->where('namespace', 'sandbox'));
    $this->post(route('articles.store'), ['namespace' => 'sandbox', 'title' => 'Scratch', 'content' => 'Body'])->assertSessionHasNoErrors()->assertRedirect();
    $article = $owner->articles()->sole();
    expect($article->isSandbox())->toBeTrue()->and($article->is_published)->toBeFalse()->and($article->translations()->count())->toBe(1)->and($article->title)->toBe('Scratch');
    $this->post(route('articles.store'), ['namespace' => 'sandbox', 'title' => 'Unsafe', 'is_published' => true])->assertSessionHasErrors('is_published');
    expect($owner->articles()->count())->toBe(1);
});

function sandboxRemoteDocument(string $markdown): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $sha = sha1('blob '.strlen($markdown)."\0".$markdown);
    Http::fake([
        '*git/trees/*' => Http::response(['truncated' => false, 'tree' => [['path' => 'sandbox/note.md', 'type' => 'blob', 'mode' => '100644', 'sha' => $sha, 'size' => strlen($markdown)]]]),
        '*git/blobs/*' => Http::response(['encoding' => 'base64', 'content' => base64_encode($markdown)]),
    ]);
}

beforeEach(function () {
    config(['articles.zenn_publishers' => ['publisher'], 'services.github_zenn' => ['repository' => 'owner/notes', 'branch' => 'main', 'token' => 'secret'], 'inertia.ssr.enabled' => false]);
    Http::preventStrayRequests();
});

test('imports single body Markdown and preserves local edits on repeated imports', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    $markdown = "# Mixed 日本語\n\nBody";
    $sha = sha1('blob '.strlen($markdown)."\0".$markdown);
    Http::fake([
        '*git/trees/*' => Http::response(['truncated' => false, 'tree' => [['path' => 'sandbox/nested/note.md', 'type' => 'blob', 'mode' => '100644', 'sha' => $sha, 'size' => strlen($markdown)]]]),
        '*git/blobs/*' => Http::response(['encoding' => 'base64', 'content' => base64_encode($markdown)]),
    ]);
    expect(app(ImportSandboxDocuments::class)($owner)['created'])->toBe(1);
    $article = $owner->articles()->sole();
    expect($article->isSandbox())->toBeTrue();
    expect($article->translations()->count())->toBe(1);
    $this->actingAs($owner)->patch(route('articles.update', $article), ['edit_version' => $article->edit_version, 'title' => 'Edited', 'content' => 'Local'])->assertSessionHasNoErrors();
    expect(app(ImportSandboxDocuments::class)($owner)['unchanged'])->toBe(1);
    expect($article->fresh()->title)->toBe('Edited');
    config(['articles.zenn_publishers' => ['publisher', 'second']]);
    $this->actingAs(User::factory()->withUsername('second')->create())->post(route('sandbox.import'))->assertSessionHas('sandbox_import.conflict', 1);
});

test('sandbox documents cannot be published exported or detached from the reserved namespace', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    $article = $owner->articles()->create();
    $article->namespace()->associate(ArticleNamespace::where('slug', 'sandbox')->sole());
    $article->save();
    $article->replaceTranslations(['ja' => ['title' => 'Private', 'content' => 'Secret']]);
    $this->actingAs($owner)->patch(route('articles.update', $article), ['edit_version' => $article->fresh()->edit_version, 'title' => 'Private', 'is_published' => true])->assertSessionHasErrors('is_published');
    $this->post(route('articles.zenn.push', $article))->assertForbidden();
    $this->post(route('article-images.store'), ['article_id' => $article->id])->assertForbidden();
    expect(app(RenderZennArticle::class)($article))->toBeNull();
    DB::table('articles')->where('id', $article->id)->update(['is_published' => true]);
    $this->get(route('public.articles.show', $article->slug))->assertNotFound();
    $article->namespace()->dissociate();
    expect(fn () => $article->save())->toThrow(LogicException::class);
});

test('sandbox namespace is protected and documents reject bilingual updates', function () {
    $namespace = ArticleNamespace::where('slug', 'sandbox')->sole();
    expect(fn () => $namespace->delete())->toThrow(LogicException::class);
    $owner = User::factory()->create();
    $article = $owner->articles()->create();
    $article->namespace()->associate($namespace);
    $article->save();
    $this->actingAs($owner)->patch(route('articles.update', $article), ['edit_version' => $article->edit_version, 'title' => 'Private', 'translations' => ['en' => ['title' => 'Other']]])->assertSessionHasErrors('translations');
    $this->actingAs(User::factory()->create())->get(route('articles.show', $article))->assertForbidden();
});

test('remote changes update untouched documents but never overwrite local changes', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    sandboxRemoteDocument("---\ntitle: Note\npublished: true\nnamespace: public\n---\nBody");
    expect(app(ImportSandboxDocuments::class)($owner)['created'])->toBe(1);
    $article = $owner->articles()->sole();
    expect($article->is_published)->toBeFalse()->and($article->isSandbox())->toBeTrue();
    sandboxRemoteDocument('# Updated');
    expect(app(ImportSandboxDocuments::class)($owner)['updated'])->toBe(1);
    $article->replaceTranslations(['ja' => ['title' => 'Local', 'content' => 'Private']]);
    sandboxRemoteDocument('# Remote edit');
    expect(app(ImportSandboxDocuments::class)($owner)['conflict'])->toBe(1);
    expect($article->fresh()->title)->toBe('Local');
    sandboxRemoteDocument("---\ntitle: [bad\n---\nBody");
    expect(app(ImportSandboxDocuments::class)($owner)['invalid'])->toBe(1);
});

test('sandbox sync state survives backup and restore', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    sandboxRemoteDocument('# Backup note');
    app(ImportSandboxDocuments::class)($owner);
    $path = sys_get_temp_dir().'/sandbox-backup-'.uniqid().'.zip';
    try {
        $this->artisan('articles:backup', ['--output' => $path])->assertSuccessful();
        $owner->articles()->delete();
        $this->artisan('articles:restore', ['path' => $path])->assertSuccessful();
        expect($owner->articles()->sole()->isSandbox())->toBeTrue();
        expect(app(ImportSandboxDocuments::class)($owner)['unchanged'])->toBe(1);
    } finally {
        File::delete($path);
    }
});

test('streams sandbox import progress and results while keeping imported documents private', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    sandboxRemoteDocument("# Streamed note\n\nPrivate body");

    $content = $this->actingAs($owner)->withHeader('Accept', 'text/event-stream')
        ->post(route('sandbox.import'))->assertOk()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8')->streamedContent();

    expect($content)->toContain('event: progress', 'Reading article :current of :total.', 'Saving articles.', 'event: result', '"status":"imported"', '"created":1')
        ->not->toContain('secret', 'Private body');
    $article = $owner->articles()->sole();
    expect($article->isSandbox())->toBeTrue();
    expect($article->is_published)->toBeFalse();
    expect($article->title)->toBe('Streamed note');
    $this->assertDatabaseHas('article_github_sources', ['article_id' => $article->id, 'path' => 'sandbox/note.md']);
    $this->assertDatabaseHas('activity_log', ['event' => 'articles.sandbox_imported', 'causer_id' => $owner->id]);
    Http::assertSentCount(2);
});

test('streams a terminal failure without leaking github response details for sandbox imports', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response(['message' => 'secret upstream error'], 403)]);

    $content = $this->actingAs($owner)->withHeader('Accept', 'text/event-stream')
        ->post(route('sandbox.import'))->assertOk()->streamedContent();

    expect($content)->toContain('event: result', '"status":"failed"', 'The articles could not be imported from GitHub.')
        ->not->toContain('secret');
    expect($owner->articles()->count())->toBe(0);
    $this->assertDatabaseMissing('activity_log', ['event' => 'articles.sandbox_imported']);
    Http::assertSentCount(1);
});

test('rejects unauthorized sandbox streaming requests before accessing github', function () {
    $owner = User::factory()->create();
    Http::fake();

    $this->actingAs($owner)->withHeader('Accept', 'text/event-stream')
        ->post(route('sandbox.import'))->assertForbidden();

    expect($owner->articles()->count())->toBe(0);
    Http::assertNothingSent();
});

test('dashboard separates linked sandbox documents and reports their local changes', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    sandboxRemoteDocument('# Linked note');
    app(ImportSandboxDocuments::class)($owner);
    $sandbox = $owner->articles()->sole();
    $regular = $owner->articles()->create(['title' => 'Regular article']);
    $regular->forceFill(['zenn_repository' => 'owner/notes', 'zenn_branch' => 'main', 'zenn_path' => 'articles/regular-article.md'])->save();
    $unlinked = $owner->articles()->create(['title' => 'Local sandbox note']);
    $unlinked->namespace()->associate($sandbox->namespace);
    $unlinked->save();

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('zenn.articles.total', 1)->where('zenn.articles.data.0.id', $regular->id)
        ->where('zenn.sandboxArticles.total', 1)->where('zenn.sandboxArticles.data.0.id', $sandbox->id)
        ->where('zenn.sandboxArticles.data.0.path', 'sandbox/note.md')
        ->where('zenn.sandboxArticles.data.0.locallyChanged', false));

    $sandbox->replaceTranslations(['ja' => ['title' => 'Edited locally', 'content' => 'Local body']]);

    $this->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('zenn.sandboxArticles.data.0.title', 'Edited locally')
        ->where('zenn.sandboxArticles.data.0.locallyChanged', true));
    Http::assertSentCount(2);
});

test('dashboard sandbox links are scoped to the current owner repository and branch', function (string $change) {
    $owner = User::factory()->withUsername('publisher')->create();
    sandboxRemoteDocument('# Linked note');
    app(ImportSandboxDocuments::class)($owner);
    if ($change === 'owner') {
        $owner = User::factory()->withUsername('second')->create();
        config(['articles.zenn_publishers' => ['publisher', 'second']]);
    } else {
        DB::table('article_github_sources')->update([$change => 'other']);
    }

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('zenn.sandboxArticles.total', 0)->has('zenn.sandboxArticles.data', 0));

    Http::assertSentCount(2);
})->with(['owner', 'repository', 'branch']);

test('dashboard paginates linked articles and sandbox documents independently', function () {
    $owner = User::factory()->withUsername('publisher')->create();
    $markdown = '# Linked note';
    $sha = sha1('blob '.strlen($markdown)."\0".$markdown);
    $tree = [];
    for ($index = 0; $index < 21; $index++) {
        $tree[] = ['path' => "sandbox/note-{$index}.md", 'type' => 'blob', 'mode' => '100644', 'sha' => $sha, 'size' => strlen($markdown)];
        $owner->articles()->create()->forceFill(['zenn_repository' => 'owner/notes', 'zenn_branch' => 'main', 'zenn_path' => "articles/regular-{$index}.md"])->save();
    }
    Http::fake([
        '*git/trees/*' => Http::response(['truncated' => false, 'tree' => $tree]),
        '*git/blobs/*' => Http::response(['encoding' => 'base64', 'content' => base64_encode($markdown)]),
    ]);
    app(ImportSandboxDocuments::class)($owner);

    $this->actingAs($owner)->get(route('dashboard', ['page' => 2, 'sandbox_page' => 2]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('zenn.articles.total', 21)->has('zenn.articles.data', 1)
            ->where('zenn.articles.current_page', 2)
            ->where('zenn.sandboxArticles.total', 21)->has('zenn.sandboxArticles.data', 1)
            ->where('zenn.sandboxArticles.current_page', 2)
            ->where('zenn.articles.prev_page_url', fn (string $url): bool => str_contains($url, 'sandbox_page=2'))
            ->where('zenn.sandboxArticles.prev_page_url', fn (string $url): bool => str_contains($url, 'page=2')));

    Http::assertSentCount(22);
});
