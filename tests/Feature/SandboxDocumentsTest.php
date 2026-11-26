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
