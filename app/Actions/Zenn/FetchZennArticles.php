<?php

namespace App\Actions\Zenn;

use App\Exceptions\ZennImportException;
use App\Support\RepositoryImageStore;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** @phpstan-type RemoteArticle array{path: string, sha: string, content: ?string} */
class FetchZennArticles
{
    /** @return array{repository: string, branch: string, files: list<RemoteArticle>, images: list<RemoteArticle>} */
    public function __invoke(Closure $checkLock, ?Closure $progress = null, string $scope = 'articles'): array
    {
        $repository = config('services.github_zenn.repository');
        $branch = config('services.github_zenn.branch');
        $token = config('services.github_zenn.token');

        if (! is_string($repository) || ! is_string($branch) || ! is_string($token) || $repository === '' || $branch === '' || $token === '') {
            throw new ZennImportException('The Zenn repository is not configured.');
        }

        try {
            $client = Http::baseUrl('https://api.github.com')->withToken($token)->acceptJson()
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])->connectTimeout(5)->timeout(15)
                ->beforeSending($checkLock)->afterResponse(function () use ($checkLock): ?Response {
                    $checkLock();

                    return null;
                });
            $progress?->__invoke('progress', ['message' => 'Reading the article list from GitHub.']);
            $tree = $client->get('/repos/'.$repository.'/git/trees/'.rawurlencode($branch), ['recursive' => 1])->throw()->json();

            if (! is_array($tree) || ($tree['truncated'] ?? true) !== false || ! is_array($tree['tree'] ?? null)) {
                throw new ZennImportException('GitHub did not return a complete article tree.');
            }

            $entries = [];
            foreach ($tree['tree'] as $entry) {
                if (($entry['type'] ?? null) !== 'blob' || ! in_array($entry['mode'] ?? '', ['100644', '100755'], true)
                    || ! is_string($entry['path'] ?? null)) {
                    continue;
                }

                $isImage = $scope === 'sandbox' && str_starts_with($entry['path'], 'images/')
                    && RepositoryImageStore::isValidPath(substr($entry['path'], 7));
                if (! $isImage && preg_match($scope === 'sandbox' ? '/\Asandbox\/(?!.*\/\.{1,2}\/)[^\\\\\x00-\x1f]+\.md\z/' : '/\Aarticles\/[a-z0-9_-]{12,50}\.md\z/', $entry['path']) !== 1) {
                    continue;
                }

                if (! is_string($entry['sha'] ?? null) || preg_match('/\A[0-9a-f]{40}\z/', $entry['sha']) !== 1) {
                    throw new ZennImportException('GitHub returned an invalid blob identity.');
                }

                if ($scope === 'sandbox' && array_intersect(explode('/', $entry['path']), ['', '.', '..']) !== []) {
                    continue;
                }

                $entries[] = $entry;
            }

            $files = [];
            $images = [];
            foreach ($entries as $index => $entry) {
                $isImage = str_starts_with($entry['path'], 'images/');
                $progress?->__invoke('progress', ['message' => $isImage ? 'Reading image :current of :total.' : 'Reading article :current of :total.', 'replace' => ['current' => $index + 1, 'total' => count($entries)]]);
                $maxBytes = $isImage ? RepositoryImageStore::MAX_BYTES : 2_000_000;
                $content = null;
                if (($entry['size'] ?? PHP_INT_MAX) <= $maxBytes) {
                    $blob = $client->get("/repos/{$repository}/git/blobs/{$entry['sha']}")->throw();
                    $encoded = $blob->json('content');
                    if ($blob->json('encoding') === 'base64' && is_string($encoded)) {
                        $decoded = base64_decode($encoded, true);
                        if (is_string($decoded) && strlen($decoded) <= $maxBytes && sha1('blob '.strlen($decoded)."\0".$decoded) === $entry['sha']) {
                            $content = $decoded;
                        }
                    }
                }
                $file = ['path' => $entry['path'], 'sha' => $entry['sha'], 'content' => $content];
                if ($isImage) {
                    $images[] = $file;
                } else {
                    $files[] = $file;
                }
            }

            return ['repository' => $repository, 'branch' => $branch, 'files' => $files, 'images' => $images];
        } catch (ConnectionException|RequestException) {
            throw new ZennImportException('The Zenn repository could not be read from GitHub.');
        }
    }
}
