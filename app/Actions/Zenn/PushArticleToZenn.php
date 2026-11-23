<?php

namespace App\Actions\Zenn;

use App\Exceptions\ZennPushException;
use App\Models\Article;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Commit the Japanese version of an article to the Zenn GitHub repository through the GitHub contents API.
 */
class PushArticleToZenn
{
    public function __construct(
        private RenderZennArticle $render,
        private ResolveZennSlug $resolveSlug,
    ) {}

    /**
     * @return array{zennSlug: string, changed: bool}
     *
     * @throws ZennPushException
     */
    public function __invoke(Article $article): array
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
            $client = Http::baseUrl('https://api.github.com')
                ->withToken($token)
                ->acceptJson()
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->connectTimeout(5)
                ->timeout(15);

            $existing = $this->existingFiles($client, $repository, $branch);
            $zennSlug = $article->zenn_path !== null
                ? basename($article->zenn_path, '.md')
                : ($this->resolveSlug)($article->slug, array_keys($existing));
            $filename = "{$zennSlug}.md";
            $path = "articles/{$filename}";
            $sha = $existing[$filename] ?? null;

            if (Article::query()->where('zenn_repository', $repository)->where('zenn_branch', $branch)
                ->where('zenn_path', $path)->whereKeyNot($article->id)->exists()) {
                throw new ZennPushException('The Zenn file is already linked to another article.');
            }

            if ($sha !== null && $sha === $this->blobSha($markdown)) {
                $this->rememberSync($article, $repository, $branch, $path, $sha);

                return ['zennSlug' => $zennSlug, 'changed' => false];
            }

            if ($article->zenn_path !== null) {
                if ($sha === null || $article->zenn_sha === null || $sha !== $article->zenn_sha) {
                    throw new ZennPushException('The Zenn file has changed or was deleted since the last sync. Import its latest version before sending.');
                }
            } elseif ($sha !== null) {
                $legacyMarkdown = str_replace("inkrail_id: \"{$article->sync_id}\"\n", '', $markdown);

                if ($sha !== $this->blobSha($legacyMarkdown)) {
                    throw new ZennPushException('The existing Zenn file cannot be safely linked to this article.');
                }
            }

            $client->put("/repos/{$repository}/contents/{$path}", array_filter([
                'message' => "docs: sync {$article->slug} from InkRail",
                'content' => base64_encode($markdown),
                'branch' => $branch,
                'sha' => $sha,
            ], fn (mixed $value): bool => $value !== null))->throw();

            $this->rememberSync($article, $repository, $branch, $path, $this->blobSha($markdown));
        } catch (ConnectionException|RequestException $exception) {
            throw new ZennPushException('GitHub rejected the request or could not be reached.', previous: $exception);
        }

        return ['zennSlug' => $zennSlug, 'changed' => true];
    }

    /**
     * @return array<string, string> Blob SHA by filename.
     *
     * @throws RequestException
     */
    private function existingFiles(PendingRequest $client, string $repository, string $branch): array
    {
        $response = $client->get("/repos/{$repository}/contents/articles", array_filter(['ref' => $branch]));

        if ($response->notFound()) {
            return [];
        }

        $files = [];

        foreach ($response->throw()->json() as $entry) {
            $files[$entry['name']] = $entry['sha'];
        }

        return $files;
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
        $article->save();
    }
}
