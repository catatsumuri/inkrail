<?php

namespace App\Actions\Zenn;

use App\Exceptions\ZennPushException;
use App\Models\Article;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Commit the Japanese version of an article to the Zenn GitHub repository through the GitHub contents API.
 */
class PushArticleToZenn
{
    public function __construct(
        private RenderZennArticle $render,
        private ResolveZennSlug $resolveSlug,
        private WithZennSyncLock $syncLock,
        private SyncZennImages $syncImages,
    ) {}

    /**
     * @return array{zennSlug: string, changed: bool}
     *
     * @throws ZennPushException
     */
    public function __invoke(Article $article, ?Closure $progress = null): array
    {
        if ($article->fresh()?->isSandbox()) {
            throw new ZennPushException('Sandbox documents cannot be sent to Zenn.');
        }

        $progress?->__invoke('progress', ['message' => 'Acquiring repository lock.']);
        try {
            return ($this->syncLock)(function (Closure $checkLock) use ($article, $progress): array {
                $progress?->__invoke('progress', ['message' => 'Preparing article.']);

                return $this->push($article->refresh(), $checkLock, $progress);
            });
        } catch (LockTimeoutException $exception) {
            throw new ZennPushException('The repository is busy or the sync lock was lost. Try again shortly.', previous: $exception);
        }
    }

    /** @return array{zennSlug: string, changed: bool} */
    private function push(Article $article, Closure $checkLock, ?Closure $progress): array
    {
        $repository = config('services.github_zenn.repository');
        $token = config('services.github_zenn.token');
        $branch = config('services.github_zenn.branch');

        if (! is_string($repository) || ! is_string($token) || ! is_string($branch) || $repository === '' || $token === '' || $branch === '') {
            throw new ZennPushException('The Zenn repository is not configured.');
        }

        if ($article->zenn_path !== null && ($article->zenn_repository !== $repository || $article->zenn_branch !== $branch)) {
            throw new ZennPushException('The configured Zenn repository or branch differs from the linked article.');
        }

        $markdown = ($this->render)($article);

        if ($markdown === null) {
            throw new ZennPushException('The article has no Japanese version to send.');
        }

        try {
            $started = microtime(true);
            $client = Http::baseUrl('https://api.github.com')
                ->withToken($token)
                ->acceptJson()
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->connectTimeout(5)
                ->timeout(15)
                ->beforeSending(function (Request $request) use ($checkLock, $progress, &$started): void {
                    $checkLock();
                    $started = microtime(true);
                    $progress?->__invoke('request', ['message' => $request->method().' '.parse_url($request->url(), PHP_URL_PATH)]);
                })
                ->afterResponse(function (Response $response) use ($checkLock, $progress, &$started): ?Response {
                    $progress?->__invoke('request', ['message' => 'HTTP '.$response->status().' · '.round((microtime(true) - $started) * 1000).' ms']);
                    $checkLock();

                    return null;
                });

            if ($article->zenn_path !== null) {
                $path = $article->zenn_path;
                $sha = $this->remoteSha($client, $repository, $branch, $path);

                if ($sha === null || $article->zenn_sha === null || $sha !== $article->zenn_sha) {
                    throw new ZennPushException('The Zenn file has changed or was deleted since the last sync. Sending was stopped to protect the remote article.');
                }
            } else {
                $path = $this->newPath($client, $article, $repository, $branch);
                $sha = null;
            }

            $progress?->__invoke('progress', ['message' => 'Checking and syncing images.']);
            $imagesChanged = ($this->syncImages)(
                $article->translations->firstWhere('locale', RenderZennArticle::LOCALE)->content ?? '',
                $client, $repository, $branch, $checkLock, $article->eyecatch_url,
            );

            if ($sha === $this->blobSha($markdown)) {
                return ['zennSlug' => basename($path, '.md'), 'changed' => $imagesChanged];
            }

            $progress?->__invoke('progress', ['message' => 'Committing article.']);
            $zennSlug = basename($path, '.md');
            $client->put("/repos/{$repository}/contents/{$path}", array_filter([
                'message' => "docs: sync {$article->slug} from InkRail",
                'content' => base64_encode($markdown),
                'branch' => $branch,
                'sha' => $sha,
            ], fn (mixed $value): bool => $value !== null))->throw();

            $checkLock();
            $progress?->__invoke('progress', ['message' => 'Saving sync state.']);
            $this->rememberSync($article, $repository, $branch, $path, $this->blobSha($markdown));
        } catch (ConnectionException|RequestException $exception) {
            throw new ZennPushException('GitHub rejected the request or could not be reached.', previous: $exception);
        }

        return ['zennSlug' => $zennSlug, 'changed' => true];
    }

    /**
     * @throws RequestException
     */
    private function remoteSha(PendingRequest $client, string $repository, string $branch, string $path): ?string
    {
        $response = $client->get("/repos/{$repository}/contents/{$path}", ['ref' => $branch]);

        if ($response->notFound()) {
            return null;
        }

        $sha = $response->throw()->json('sha');

        if (! is_string($sha) || $sha === '') {
            throw new ZennPushException('GitHub did not return a file SHA.');
        }

        return $sha;
    }

    private function newPath(PendingRequest $client, Article $article, string $repository, string $branch): string
    {
        $attemptedFilenames = [];
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $slug = ($this->resolveSlug)($article->slug, $attemptedFilenames);
            $attemptedFilenames[] = "{$slug}.md";
            $path = "articles/{$slug}.md";

            if (Article::query()->where('zenn_repository', $repository)->where('zenn_branch', $branch)->where('zenn_path', $path)->exists()) {
                continue;
            }

            if ($this->remoteSha($client, $repository, $branch, $path) === null) {
                return $path;
            }
        }

        throw new ZennPushException('A new Zenn filename could not be allocated.');
    }

    private function blobSha(string $content): string
    {
        return sha1('blob '.strlen($content)."\0".$content);
    }

    private function rememberSync(Article $article, string $repository, string $branch, string $path, string $sha): void
    {
        $article->zenn_repository = $repository;
        $article->zenn_branch = $branch;
        $article->zenn_path = $path;
        $article->zenn_sha = $sha;
        $article->zenn_local_sha = $sha;
        $article->save();
    }
}
