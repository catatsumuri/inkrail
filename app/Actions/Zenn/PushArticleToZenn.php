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

        if (! is_string($repository) || ! is_string($token) || $repository === '' || $token === '') {
            throw new ZennPushException('The Zenn repository is not configured.');
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
                ->timeout(15);

            $existing = $this->existingFiles($client, $repository, $branch);
            $zennSlug = ($this->resolveSlug)($article->slug, array_keys($existing));
            $filename = "{$zennSlug}.md";
            $sha = $existing[$filename] ?? null;

            if ($sha !== null && $sha === $this->blobSha($markdown)) {
                return ['zennSlug' => $zennSlug, 'changed' => false];
            }

            $client->put("/repos/{$repository}/contents/articles/{$filename}", array_filter([
                'message' => "docs: sync {$article->slug} from InkRail",
                'content' => base64_encode($markdown),
                'branch' => $branch,
                'sha' => $sha,
            ], fn (mixed $value): bool => $value !== null))->throw();
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
    private function existingFiles(PendingRequest $client, string $repository, mixed $branch): array
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
}
