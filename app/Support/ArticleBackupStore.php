<?php

namespace App\Support;

use App\Actions\Backup\CreateArticleBackup;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Keeps backup archives on the configured backup disk (an S3 compatible disk
 * such as R2 on hosts without a persistent filesystem). Zip handling always
 * happens on a local temporary copy.
 */
class ArticleBackupStore
{
    public const FILENAME_PATTERN = '/\Ainkrail-\d{8}-\d{6}(-[a-z0-9]{4})?\.zip\z/';

    public function __construct(private CreateArticleBackup $create) {}

    public function diskName(): string
    {
        return (string) config('articles.backup_disk');
    }

    public function isValidFilename(string $filename): bool
    {
        return preg_match(self::FILENAME_PATTERN, $filename) === 1;
    }

    /**
     * @return list<array{filename: string, size: int, created_at: string}>
     */
    public function list(): array
    {
        $disk = $this->disk();

        $backups = collect($disk->files($this->directory()))
            ->map(fn (string $path): string => basename($path))
            ->filter(fn (string $filename): bool => $this->isValidFilename($filename))
            ->map(fn (string $filename): array => [
                'filename' => $filename,
                'size' => $disk->size($this->path($filename)),
                'created_at' => date(DATE_ATOM, $disk->lastModified($this->path($filename))),
            ])
            ->sortByDesc('created_at')
            ->all();

        return array_values($backups);
    }

    public function exists(string $filename): bool
    {
        return $this->isValidFilename($filename) && $this->disk()->exists($this->path($filename));
    }

    /**
     * Build a fresh backup and keep it on the backup disk.
     *
     * @return array{filename: string, articles: int, namespaces: int, images: int, missing_images: list<string>}
     */
    public function create(?string $description = null): array
    {
        $temporary = $this->temporaryPath();

        try {
            $result = ($this->create)($temporary, $description);

            return ['filename' => $this->put($temporary), ...$result];
        } finally {
            @unlink($temporary);
        }
    }

    /**
     * Keep an existing local archive on the backup disk under a new name.
     */
    public function put(string $localPath): string
    {
        $filename = 'inkrail-'.now()->format('Ymd-His').'.zip';

        if ($this->disk()->exists($this->path($filename))) {
            $filename = str_replace('.zip', '-'.strtolower(Str::random(4)).'.zip', $filename);
        }

        if ($this->disk()->putFileAs($this->directory(), new File($localPath), $filename) === false) {
            throw new RuntimeException('The backup could not be written to the backup disk.');
        }

        return $filename;
    }

    public function download(string $filename): StreamedResponse
    {
        return $this->disk()->download($this->path($filename), $filename);
    }

    public function delete(string $filename): void
    {
        $this->disk()->delete($this->path($filename));
    }

    /**
     * Copy a stored archive to a temporary local file the caller must unlink.
     */
    public function copyToTemporaryFile(string $filename): string
    {
        $stream = $this->disk()->readStream($this->path($filename));

        if (! is_resource($stream)) {
            throw new RuntimeException('The backup could not be read from the backup disk.');
        }

        $temporary = $this->temporaryPath();
        $target = fopen($temporary, 'wb');

        if ($target === false) {
            fclose($stream);

            throw new RuntimeException('Could not create a temporary file.');
        }

        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);

        return $temporary;
    }

    private function temporaryPath(): string
    {
        return tempnam(sys_get_temp_dir(), 'inkrail-backup-') ?: throw new RuntimeException('Could not create a temporary file.');
    }

    private function directory(): string
    {
        return trim((string) config('articles.backup_directory'), '/');
    }

    private function path(string $filename): string
    {
        return ltrim($this->directory().'/'.$filename, '/');
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }
}
