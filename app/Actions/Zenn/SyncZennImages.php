<?php

namespace App\Actions\Zenn;

use App\Exceptions\ZennPushException;
use App\Models\ArticleImage;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Parser\MarkdownParser;

class SyncZennImages
{
    private const MAX_BYTES = 3_000_000;

    /**
     * Validate every referenced repository image before uploading missing files.
     * Existing remote images are never overwritten or deleted.
     */
    public function __invoke(string $content, PendingRequest $client, string $repository, string $branch, Closure $checkLock): bool
    {
        $environment = new Environment;
        $environment->addExtension(new CommonMarkCoreExtension);
        $document = (new MarkdownParser($environment))->parse($content);
        $paths = [];
        foreach ($document->iterator() as $node) {
            if ($node instanceof Image && str_starts_with($node->getUrl(), '/images/')) {
                $paths[$node->getUrl()] = true;
            }
        }

        $disk = Storage::disk(ArticleImage::disk());
        $uploads = [];
        foreach (array_keys($paths) as $url) {
            $url = rawurldecode($url);
            $segments = explode('/', substr($url, 1));
            if (array_intersect($segments, ['', '.', '..']) !== []
                || preg_match('/[\\\\\x00-\x1f?#]/', $url) === 1
                || ! in_array(pathinfo($url, PATHINFO_EXTENSION), ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
                throw new ZennPushException('A repository image has an unsupported path or extension.');
            }
            $path = substr($url, 1);
            $apiPath = implode('/', array_map(rawurlencode(...), $segments));
            $filename = basename($path);
            $localPath = ArticleImage::DIRECTORY.'/'.$filename;
            $bytes = null;
            if ($path === 'images/'.$filename && preg_match('/\A[0-9a-f]{64}\.(png|jpg|gif|webp)\z/', $filename) === 1 && $disk->exists($localPath)) {
                if ($disk->size($localPath) > self::MAX_BYTES) {
                    throw new ZennPushException('A repository image exceeds the Zenn 3 MB limit.');
                }
                $bytes = $disk->get($localPath);
                if (! is_string($bytes) || strlen($bytes) > self::MAX_BYTES || hash('sha256', $bytes) !== pathinfo($filename, PATHINFO_FILENAME)) {
                    throw new ZennPushException('A local image is damaged or unreadable.');
                }
            }

            $response = $client->get("/repos/{$repository}/contents/{$apiPath}", ['ref' => $branch]);
            if ($response->notFound()) {
                if ($bytes === null) {
                    throw new ZennPushException('A referenced image is missing locally and on GitHub.');
                }
                $uploads[$apiPath] = $bytes;

                continue;
            }

            $response->throw();
            $size = $response->json('size');
            if ($response->json('type') !== 'file' || ! is_int($size) || $size > self::MAX_BYTES) {
                throw new ZennPushException('A remote image is invalid or exceeds the Zenn 3 MB limit.');
            }
            if ($bytes !== null && $response->json('sha') !== sha1('blob '.strlen($bytes)."\0".$bytes)) {
                throw new ZennPushException('A repository image differs from the local image; it was not overwritten.');
            }
        }

        foreach ($uploads as $path => $bytes) {
            $checkLock();
            $client->put("/repos/{$repository}/contents/{$path}", [
                'message' => 'docs: sync image from InkRail',
                'content' => base64_encode($bytes),
                'branch' => $branch,
            ])->throw();
        }

        return $uploads !== [];
    }
}
