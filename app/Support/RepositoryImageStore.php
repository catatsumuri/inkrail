<?php

namespace App\Support;

use App\Models\ArticleImage;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RepositoryImageStore
{
    public const DIRECTORY = 'repository-images';

    public const MAX_BYTES = 3_000_000;

    /** @var array<string, string> */
    private const MIME_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    public static function isValidPath(string $path): bool
    {
        return preg_match('/[\\\\\x00-\x1f\x7f%?#]/', $path) !== 1
            && array_intersect(explode('/', $path), ['', '.', '..']) === []
            && isset(self::MIME_TYPES[pathinfo($path, PATHINFO_EXTENSION)]);
    }

    /** @return 'created'|'updated'|'unchanged' */
    public function put(string $path, string $bytes): string
    {
        if (! self::isValidPath($path) || strlen($bytes) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Invalid repository image.');
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info['mime'] !== self::MIME_TYPES[pathinfo($path, PATHINFO_EXTENSION)]) {
            throw new InvalidArgumentException('Invalid repository image content.');
        }

        $disk = Storage::disk(ArticleImage::disk());
        $storagePath = self::DIRECTORY.'/'.$path;
        $exists = $disk->exists($storagePath);
        if ($exists && $disk->get($storagePath) === $bytes) {
            return 'unchanged';
        }

        if (! $disk->put($storagePath, $bytes)) {
            throw new RuntimeException('The repository image could not be stored.');
        }

        return $exists ? 'updated' : 'created';
    }

    public function response(string $path): StreamedResponse
    {
        abort_unless(self::isValidPath($path), 404);
        $disk = Storage::disk(ArticleImage::disk());
        $storagePath = self::DIRECTORY.'/'.$path;
        abort_unless($disk->exists($storagePath), 404);

        return $disk->response($storagePath, null, [
            'Content-Type' => self::MIME_TYPES[pathinfo($path, PATHINFO_EXTENSION)],
            'Cache-Control' => 'public, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
