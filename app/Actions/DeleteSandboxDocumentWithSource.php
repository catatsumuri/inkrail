<?php

namespace App\Actions;

use App\Actions\Zenn\WithZennSyncLock;
use App\Models\Article;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use stdClass;
use Throwable;

class DeleteSandboxDocumentWithSource
{
    public function __construct(private WithZennSyncLock $lock, private LogSecurityEvent $audit) {}

    public function confirmation(Article $article, stdClass $source): string
    {
        return hash_hmac('sha256', serialize([
            $article->id, $article->user_id, $article->edit_version,
            $source->id, $source->repository, $source->branch, $source->path, $source->remote_sha,
        ]), config('app.key'));
    }

    public function __invoke(User $owner, Article $article, string $confirmation): void
    {
        Gate::forUser($owner)->authorize('delete', $article);
        Gate::forUser($owner)->authorize('import-from-zenn');
        $remoteRemoved = false;
        $localDeleted = false;
        try {
            ($this->lock)(function (Closure $check) use ($owner, $article, $confirmation, &$remoteRemoved): void {
                DB::transaction(function () use ($owner, $article, $confirmation, $check, &$remoteRemoved): void {
                    User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
                    $source = DB::table('article_github_sources')->where('article_id', $article->id)->lockForUpdate()->first();
                    $locked = Article::query()->whereKey($article->id)->lockForUpdate()->firstOrFail();
                    Gate::forUser($owner)->authorize('delete', $locked);
                    Gate::forUser($owner)->authorize('import-from-zenn');
                    if (! $locked->isSandbox() || $source === null || ! hash_equals($this->confirmation($locked, $source), $confirmation)) {
                        $this->fail('The document or GitHub link has changed. Reload before deleting.');
                    }
                    if ($source->repository !== config('services.github_zenn.repository') || $source->branch !== config('services.github_zenn.branch') || $source->branch === ''
                        || ! is_string(config('services.github_zenn.token')) || config('services.github_zenn.token') === ''
                        || preg_match('/\A[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+\z/', $source->repository) !== 1
                        || ! str_starts_with($source->path, 'sandbox/') || ! str_ends_with($source->path, '.md')
                        || preg_match('/[\\\\\x00-\x1f\x7f]/', $source->path) === 1
                        || array_intersect(explode('/', $source->path), ['', '.', '..']) !== []
                        || preg_match('/\A[0-9a-f]{40}\z/', $source->remote_sha) !== 1) {
                        $this->fail('The linked GitHub source cannot be safely deleted with the current configuration.');
                    }
                    if (Article::query()->where('zenn_repository', $source->repository)->where('zenn_branch', $source->branch)->where('zenn_path', $source->path)->exists()) {
                        $this->fail('This GitHub file is also linked to a regular article. Deletion was stopped.');
                    }
                    $client = Http::baseUrl('https://api.github.com')->withToken(config('services.github_zenn.token'))->acceptJson()
                        ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])->connectTimeout(5)->timeout(15)
                        ->beforeSending($check)->afterResponse(function () use ($check): ?Response {
                            $check();

                            return null;
                        });
                    $tree = $client->get('/repos/'.$source->repository.'/git/trees/'.rawurlencode($source->branch), ['recursive' => 1])->throw()->json();
                    if (! is_array($tree) || ($tree['truncated'] ?? true) !== false || ! is_array($tree['tree'] ?? null)) {
                        $this->fail('GitHub did not return a complete article tree.');
                    }
                    $entries = array_values(array_filter($tree['tree'], fn (mixed $entry): bool => is_array($entry) && ($entry['path'] ?? null) === $source->path));
                    if ($entries !== []) {
                        if (count($entries) !== 1 || ($entries[0]['type'] ?? null) !== 'blob'
                            || ! in_array($entries[0]['mode'] ?? null, ['100644', '100755'], true)
                            || ($entries[0]['sha'] ?? null) !== $source->remote_sha) {
                            $this->fail('The GitHub file has changed since import. Import again before deleting.');
                        }
                        $path = implode('/', array_map(rawurlencode(...), explode('/', $source->path)));
                        $response = $client->delete('/repos/'.$source->repository.'/contents/'.$path, [
                            'message' => 'docs: delete sandbox document from InkRail', 'branch' => $source->branch, 'sha' => $source->remote_sha,
                        ])->throw();
                        if ($response->status() !== 200) {
                            $this->fail('GitHub did not confirm the deletion. The local document was kept. Reload and retry.');
                        }
                    }
                    $remoteRemoved = true;
                    $check();
                    if (! $locked->delete()) {
                        throw new RuntimeException('The local document could not be deleted.');
                    }
                });
            });
            $localDeleted = true;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (LockTimeoutException $exception) {
            $this->fail($remoteRemoved ? 'The GitHub file is absent, but local deletion could not be completed. Retry to finish deleting the local document.' : 'The repository is busy or the sync lock was lost. Try again shortly.');
        } catch (ConnectionException|RequestException $exception) {
            $this->fail('GitHub could not confirm the operation. The local document was kept. Reload and retry; the remote file may already be deleted.');
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($remoteRemoved ? 'The GitHub file is absent, but local deletion could not be completed. Retry to finish deleting the local document.' : 'Deletion could not be completed. The local document was kept.');
        } finally {
            if ($remoteRemoved) {
                ($this->audit)('articles.sandbox_source_deleted', $article, ['article_id' => $article->id, 'local_deleted' => $localDeleted], causer: $owner);
            }
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['delete_github_source' => __($message)]);
    }
}
