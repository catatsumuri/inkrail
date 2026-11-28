<?php

use App\Actions\DeleteSandboxDocumentWithSource;
use App\Actions\ImportSandboxDocuments;
use App\Actions\Zenn\WithZennSyncLock;
use App\Models\Article;
use App\Models\ArticleNamespace;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['articles.zenn_publishers' => ['publisher'], 'services.github_zenn' => ['repository' => 'owner/notes', 'branch' => 'main', 'token' => 'secret'], 'inertia.ssr.enabled' => false]);
    Http::preventStrayRequests();
    $this->owner = User::factory()->withUsername('publisher')->create();
    $this->article = $this->owner->articles()->create(['title' => 'Private note']);
    $this->article->namespace()->associate(ArticleNamespace::where('slug', 'sandbox')->sole());
    $this->article->save();
    DB::table('article_github_sources')->insert([
        'article_id' => $this->article->id, 'repository' => 'owner/notes', 'branch' => 'main', 'path' => 'sandbox/note.md',
        'remote_sha' => str_repeat('a', 40), 'local_fingerprint' => str_repeat('b', 64), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $source = DB::table('article_github_sources')->where('article_id', $this->article->id)->sole();
    $this->confirmation = app(DeleteSandboxDocumentWithSource::class)->confirmation($this->article->fresh(), $source);
    $this->payload = ['delete_github_source' => true, 'source_confirmation' => $this->confirmation];
});

function fakeSandboxDeletionTree(?string $sha = null): array
{
    return ['truncated' => false, 'tree' => $sha === null ? [] : [['path' => 'sandbox/note.md', 'sha' => $sha, 'type' => 'blob', 'mode' => '100644']]];
}

test('local only deletion remains the default even for linked documents', function () {
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article))->assertRedirect(route('articles.index', ['namespace' => 'sandbox']));
    $this->assertModelMissing($this->article);
    $this->assertDatabaseMissing('article_github_sources', ['article_id' => $this->article->id]);
    Http::assertNothingSent();
});

test('the confirmation displays the exact source only to an authorized publisher', function () {
    $this->actingAs($this->owner)->get(route('articles.show', $this->article))->assertInertia(fn (Assert $page) => $page
        ->where('githubSourceDeletion.repository', 'owner/notes')->where('githubSourceDeletion.branch', 'main')
        ->where('githubSourceDeletion.path', 'sandbox/note.md')->where('githubSourceDeletion.confirmation', $this->confirmation));
    config(['articles.zenn_publishers' => []]);
    $this->get(route('articles.show', $this->article))->assertInertia(fn (Assert $page) => $page->where('githubSourceDeletion', null));
});

test('combined deletion removes only the linked markdown and prevents reimport', function () {
    config(['articles.image_disk' => 'sandbox-source-deletion']);
    Storage::fake('sandbox-source-deletion');
    Storage::disk('sandbox-source-deletion')->put('repository-images/shared.png', 'shared image');
    $regular = $this->owner->articles()->create(['title' => 'Independent draft']);
    Http::fake([
        '*git/trees/*' => Http::sequence()->push(fakeSandboxDeletionTree(str_repeat('a', 40)))->push(fakeSandboxDeletionTree()),
        '*contents/sandbox/note.md' => Http::response(['commit' => ['sha' => str_repeat('c', 40)]], 200),
    ]);
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasNoErrors()->assertRedirect(route('articles.index', ['namespace' => 'sandbox']));
    $this->assertModelMissing($this->article);
    $this->assertModelExists($regular);
    $this->assertDatabaseMissing('article_github_sources', ['article_id' => $this->article->id]);
    Storage::disk('sandbox-source-deletion')->assertExists('repository-images/shared.png');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->url() === 'https://api.github.com/repos/owner/notes/contents/sandbox/note.md' && $request['sha'] === str_repeat('a', 40) && $request['branch'] === 'main');
    expect(app(ImportSandboxDocuments::class)($this->owner)['created'])->toBe(0);
    Http::assertSentCount(3);
    $this->assertDatabaseHas('activity_log', ['event' => 'articles.sandbox_source_deleted', 'subject_id' => $this->article->id, 'causer_id' => $this->owner->id]);
});

test('a changed remote blob preserves the local document', function () {
    Http::fake(['*git/trees/*' => Http::response(fakeSandboxDeletionTree(str_repeat('c', 40)))]);
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    $this->assertModelExists($this->article);
    Http::assertSentCount(1);
});

test('a missing file in a readable complete branch tree permits local deletion without another remote write', function () {
    Http::fake(['*git/trees/*' => Http::response(fakeSandboxDeletionTree())]);
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertModelMissing($this->article);
    Http::assertSentCount(1);
});

test('remote read errors and incomplete trees preserve the local document', function (array $tree, int $status) {
    Http::fake(['*git/trees/*' => Http::response($tree, $status)]);
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    $this->assertModelExists($this->article);
    Http::assertSentCount(1);
})->with([[[], 404], [[], 403], [[], 500], [['truncated' => true, 'tree' => []], 200]]);

test('failed remote deletes including a race preserve the local source and document', function (int $status) {
    Http::fake(['*git/trees/*' => Http::response(fakeSandboxDeletionTree(str_repeat('a', 40))), '*contents/*' => Http::response([], $status)]);
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    $this->assertModelExists($this->article);
    $this->assertDatabaseHas('article_github_sources', ['article_id' => $this->article->id]);
    Http::assertSentCount(2);
})->with([403, 404, 409, 422, 500]);

test('connection failures preserve the local document', function () {
    Http::fake(['*' => Http::failedConnection()]);
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    $this->assertModelExists($this->article);
});

test('other owners and publishers without permission cannot delete remote files', function (bool $otherOwner) {
    $user = $otherOwner ? User::factory()->create() : $this->owner;
    if (! $otherOwner) {
        config(['articles.zenn_publishers' => []]);
    }
    $this->actingAs($user)->delete(route('articles.destroy', $this->article), $this->payload)->assertForbidden();
    $this->assertModelExists($this->article);
    Http::assertNothingSent();
})->with([true, false]);

test('stale document confirmation and forged confirmation cannot trigger remote requests', function (bool $edit) {
    if ($edit) {
        $this->article->replaceTranslations(['ja' => ['title' => 'Changed', 'content' => 'Changed']]);
    } else {
        $this->payload['source_confirmation'] = str_repeat('0', 64);
    }
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    $this->assertModelExists($this->article);
    Http::assertNothingSent();
})->with([true, false]);

test('configuration changes and unsafe linked paths stop deletion', function (string $field, string $value) {
    DB::table('article_github_sources')->where('article_id', $this->article->id)->update([$field => $value]);
    $source = DB::table('article_github_sources')->where('article_id', $this->article->id)->sole();
    $this->payload['source_confirmation'] = app(DeleteSandboxDocumentWithSource::class)->confirmation($this->article->fresh(), $source);
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    $this->assertModelExists($this->article);
    Http::assertNothingSent();
})->with([['repository', 'other/notes'], ['branch', 'other'], ['path', 'images/shared.md'], ['path', 'sandbox/../articles/file.md']]);

test('a cross table reference to the source stops deletion', function () {
    $regular = $this->owner->articles()->create();
    $regular->forceFill(['zenn_repository' => 'owner/notes', 'zenn_branch' => 'main', 'zenn_path' => 'sandbox/note.md'])->save();
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    $this->assertModelExists($this->article);
    Http::assertNothingSent();
});

test('regular articles cannot use combined deletion', function () {
    $this->article->namespace()->dissociate();
    $this->article->saveQuietly();
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    $this->assertModelExists($this->article);
    Http::assertNothingSent();
});

test('the sync lock prevents overlapping deletion and import', function () {
    app(WithZennSyncLock::class)(function (): void {
        $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    });
    $this->assertModelExists($this->article);
    Http::assertNothingSent();
});

test('a local failure after remote deletion preserves the source and permits a safe retry', function () {
    Http::fake([
        '*git/trees/*' => Http::sequence()->push(fakeSandboxDeletionTree(str_repeat('a', 40)))->push(fakeSandboxDeletionTree()),
        '*contents/*' => Http::response(['commit' => ['sha' => str_repeat('c', 40)]], 200),
    ]);
    $failOnce = true;
    Article::deleting(function (Article $article) use (&$failOnce): void {
        if ($article->id === $this->article->id && $failOnce) {
            $failOnce = false;
            throw new RuntimeException('Simulated local failure');
        }
    });
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    $this->assertModelExists($this->article);
    $this->assertDatabaseHas('article_github_sources', ['article_id' => $this->article->id]);
    $this->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertModelMissing($this->article);
    Http::assertSentCount(3);
});

test('combined deletion requires an authenticated session', function () {
    $this->delete(route('articles.destroy', $this->article), $this->payload)->assertRedirect(route('login'));
    $this->assertModelExists($this->article);
    Http::assertNothingSent();
});

test('combined deletion requires the confirmation from the displayed source', function () {
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), ['delete_github_source' => true])->assertSessionHasErrors('source_confirmation');
    $this->assertModelExists($this->article);
    Http::assertNothingSent();
});

test('a link changed after confirmation cannot select a different target', function () {
    DB::table('article_github_sources')->where('article_id', $this->article->id)->update(['path' => 'sandbox/other.md']);
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    $this->assertModelExists($this->article);
    Http::assertNothingSent();
});

test('a removed link cannot trigger remote deletion', function () {
    DB::table('article_github_sources')->where('article_id', $this->article->id)->delete();
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    $this->assertModelExists($this->article);
    Http::assertNothingSent();
});

test('a symlink replacement cannot be deleted as the imported markdown', function () {
    $tree = fakeSandboxDeletionTree(str_repeat('a', 40));
    $tree['tree'][0]['mode'] = '120000';
    Http::fake(['*git/trees/*' => Http::response($tree)]);
    $this->actingAs($this->owner)->delete(route('articles.destroy', $this->article), $this->payload)->assertSessionHasErrors('delete_github_source');
    $this->assertModelExists($this->article);
    Http::assertSentCount(1);
});
