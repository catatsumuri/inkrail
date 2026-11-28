<?php

namespace App\Actions\Backup;

use App\Actions\Zenn\WithZennSyncLock;
use App\Models\ArticleImage;
use App\Models\User;
use App\Support\RepositoryImageStore;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use ZipArchive;

/**
 * Restores a CreateArticleBackup archive. Articles are matched by sync_id: an
 * existing article is overwritten (translations, revisions and reviews are
 * replaced wholesale), anything else is created. Articles outside the archive
 * are kept. Sandbox source links are restored only in their original environment.
 * The whole restore runs in one transaction.
 */
class RestoreArticleBackup
{
    public function __construct(private WithZennSyncLock $withSyncLock) {}

    /**
     * @return array{articles: int, created: int, updated: int, namespaces: int, images: int, skipped_github_sources: int}
     */
    public function __invoke(string $path, ?User $fallbackUser = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Backup file not found: {$path}");
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Failed to open zip: {$path}");
        }

        try {
            $manifest = $this->readManifest($zip);
            $data = $this->readData($zip);
            $restoreSources = hash_equals(CreateArticleBackup::sourceId(), $manifest['source_id']);
            $fallbackUser ??= User::query()->orderBy('id')->first();

            if (! $fallbackUser instanceof User) {
                throw new RuntimeException('Cannot restore articles without an existing user.');
            }

            $users = User::query()->whereNotNull('username')->pluck('id', 'username');
            $resolveUser = fn (?string $username): int => $users->get($username, $fallbackUser->id);

            $images = $this->verifiedImages($zip, $data['images'] ?? []);
            $repositoryImages = $this->verifiedRepositoryImages($zip, $data['repository_images'] ?? []);
            $writtenImages = [];

            try {
                return ($this->withSyncLock)(function (Closure $check) use ($zip, $data, $users, $resolveUser, $images, $repositoryImages, $restoreSources, &$writtenImages): array {
                    try {
                        return DB::transaction(function () use ($zip, $data, $users, $resolveUser, $images, $repositoryImages, $restoreSources, $check, &$writtenImages): array {
                            DB::table('users')->orderBy('id')->lockForUpdate()->get(['id']);
                            DB::table('article_github_sources')->orderBy('id')->lockForUpdate()->get(['id']);
                            DB::table('articles')->orderBy('id')->lockForUpdate()->get(['id']);
                            foreach ($data['articles'] ?? [] as $article) {
                                $check();
                                $existingId = DB::table('articles')->where('sync_id', $article['sync_id'])->value('id');
                                $this->assertNoConflicts($article, $existingId);
                                $this->assertSourceCanBeRestored($article, $existingId, $restoreSources);
                            }
                            $this->assertRepositoryImagesDoNotConflict($repositoryImages);
                            $namespaceIds = $this->restoreNamespaces($data['namespaces'] ?? []);
                            $counts = ['articles' => 0, 'created' => 0, 'updated' => 0, 'skipped_github_sources' => 0];

                            foreach ($data['articles'] ?? [] as $article) {
                                $existingId = DB::table('articles')->where('sync_id', $article['sync_id'])->value('id');

                                $check();
                                $this->assertNoConflicts($article, $existingId);
                                $this->assertSourceCanBeRestored($article, $existingId, $restoreSources);

                                $articleId = $this->storeArticle($article, $existingId, $namespaceIds, $resolveUser, $restoreSources);
                                $this->storeHistory($articleId, $article, fn (?string $username): ?int => $users->get($username));

                                $counts['articles']++;
                                $counts[$existingId === null ? 'created' : 'updated']++;
                                if (! $restoreSources && ($article['github_source'] ?? null) !== null) {
                                    $counts['skipped_github_sources']++;
                                }
                            }

                            $imageCount = $this->restoreImages($zip, $images, $resolveUser, $writtenImages, $check);
                            $this->restoreRepositoryImages($zip, $repositoryImages, $writtenImages, $check);
                            $check();

                            return [
                                ...$counts,
                                'namespaces' => count($namespaceIds),
                                'images' => $imageCount + count($repositoryImages),
                            ];
                        });
                    } catch (\Throwable $exception) {
                        $this->discardWrittenImages($writtenImages);

                        throw $exception;
                    }
                });
            } catch (LockTimeoutException $exception) {
                throw new RuntimeException('GitHub synchronization or another backup operation is in progress. Please try again.', previous: $exception);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Read and check the manifest of an archive without restoring anything.
     *
     * @return array<string, mixed>
     */
    public function manifest(string $path): array
    {
        $zip = new ZipArchive;

        if (! is_file($path) || $zip->open($path) !== true) {
            throw new RuntimeException('The file is not a valid backup archive.');
        }

        try {
            return $this->readManifest($zip);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(ZipArchive $zip): array
    {
        $manifest = $this->readJson($zip, CreateArticleBackup::MANIFEST_FILE);

        if (($manifest['format'] ?? null) !== CreateArticleBackup::FORMAT) {
            throw new RuntimeException('Unsupported backup format. Create a new backup with the current version of InkRail.');
        }
        if (! is_string($manifest['source_id'] ?? null) || preg_match('/\A[0-9a-f]{64}\z/', $manifest['source_id']) !== 1) {
            throw new RuntimeException('The backup archive is missing its source environment identifier.');
        }

        return $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    private function readData(ZipArchive $zip): array
    {
        $this->readManifest($zip);

        return $this->readJson($zip, CreateArticleBackup::DATA_FILE);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(ZipArchive $zip, string $name): array
    {
        $raw = $zip->getFromName($name);

        if ($raw === false) {
            throw new RuntimeException("{$name} is missing from the backup archive.");
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("{$name} in the backup archive is malformed.", previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException("{$name} in the backup archive is malformed.");
        }

        return $decoded;
    }

    /**
     * @param  list<array<string, mixed>>  $namespaces
     * @return array<string, int> namespace id keyed by slug
     */
    private function restoreNamespaces(array $namespaces): array
    {
        $ids = [];

        foreach ($namespaces as $namespace) {
            $existingId = DB::table('article_namespaces')->where('slug', $namespace['slug'])->value('id');
            $values = [
                'name' => $namespace['slug'] === 'sandbox' ? 'Sandbox' : $namespace['name'],
                'created_at' => $namespace['created_at'],
                'updated_at' => $namespace['updated_at'],
            ];

            if ($existingId === null) {
                $ids[$namespace['slug']] = DB::table('article_namespaces')->insertGetId(['slug' => $namespace['slug'], ...$values]);
            } else {
                DB::table('article_namespaces')->where('id', $existingId)->update($values);
                $ids[$namespace['slug']] = $existingId;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $article
     */
    private function assertNoConflicts(array $article, ?int $existingId): void
    {
        $slugTaken = DB::table('articles')->where('slug', $article['slug'])
            ->when($existingId !== null, fn ($query) => $query->where('id', '!=', $existingId))->exists();

        if ($slugTaken) {
            throw new RuntimeException("Slug \"{$article['slug']}\" is already used by a different article; nothing was restored.");
        }

        if ($article['zenn_path'] === null) {
            return;
        }

        $targetTaken = DB::table('articles')
            ->where('zenn_repository', $article['zenn_repository'])
            ->where('zenn_branch', $article['zenn_branch'])
            ->where('zenn_path', $article['zenn_path'])
            ->when($existingId !== null, fn ($query) => $query->where('id', '!=', $existingId))->exists();

        if ($targetTaken) {
            throw new RuntimeException("Zenn path \"{$article['zenn_path']}\" is already linked to a different article; nothing was restored.");
        }
    }

    /** @param array<string, mixed> $article */
    private function assertSourceCanBeRestored(array $article, ?int $existingId, bool $restoreSources): void
    {
        if (! $restoreSources && $existingId !== null && DB::table('article_github_sources')->where('article_id', $existingId)->exists()) {
            throw new RuntimeException('A Sandbox document is already linked to GitHub in this environment. A backup from another environment cannot overwrite it; nothing was restored.');
        }
        $source = $article['github_source'] ?? null;
        if ($source === null) {
            return;
        }
        if ($article['namespace'] !== 'sandbox' || ! is_array($source)
            || ! is_string($source['repository'] ?? null) || preg_match('/\A[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+\z/', $source['repository']) !== 1
            || ! is_string($source['branch'] ?? null) || $source['branch'] === ''
            || ! is_string($source['path'] ?? null) || ! str_starts_with($source['path'], 'sandbox/') || ! str_ends_with($source['path'], '.md')
            || preg_match('/[\\\\\x00-\x1f\x7f]/', $source['path']) === 1 || array_intersect(explode('/', $source['path']), ['', '.', '..']) !== []
            || ! is_string($source['remote_sha'] ?? null) || preg_match('/\A[0-9a-f]{40}\z/', $source['remote_sha']) !== 1
            || ! is_string($source['local_fingerprint'] ?? null) || preg_match('/\A[0-9a-f]{64}\z/', $source['local_fingerprint']) !== 1
            || ! array_key_exists('frontmatter', $source) || ($source['frontmatter'] !== null && ! is_array($source['frontmatter']))) {
            throw new RuntimeException('The backup archive contains invalid Sandbox GitHub source metadata.');
        }
        if (! $restoreSources) {
            return;
        }
        $linked = DB::table('article_github_sources')->where('repository', $source['repository'])->where('branch', $source['branch'])->where('path', $source['path'])
            ->when($existingId !== null, fn ($query) => $query->where('article_id', '!=', $existingId))->exists();
        $regular = DB::table('articles')->where('zenn_repository', $source['repository'])->where('zenn_branch', $source['branch'])->where('zenn_path', $source['path'])->exists();
        if ($linked || $regular) {
            throw new RuntimeException("Sandbox GitHub path \"{$source['path']}\" is already linked to a different article. Its sync ID was not changed; nothing was restored.");
        }
    }

    /**
     * @param  list<array<string, mixed>>  $images
     * @return list<array<string, mixed>>
     */
    private function verifiedRepositoryImages(ZipArchive $zip, array $images): array
    {
        $seen = [];
        foreach ($images as $image) {
            $path = $image['path'] ?? null;
            if (! is_string($path) || ! RepositoryImageStore::isValidPath($path) || isset($seen[$path])
                || ! is_string($image['sha256'] ?? null) || preg_match('/\A[0-9a-f]{64}\z/', $image['sha256']) !== 1) {
                throw new RuntimeException('The backup archive contains an invalid repository image path or checksum.');
            }
            $seen[$path] = true;
            $stat = $zip->statName(CreateArticleBackup::REPOSITORY_FILES_DIRECTORY.$path);
            if ($stat === false || $stat['size'] > RepositoryImageStore::MAX_BYTES) {
                throw new RuntimeException("The backup archive is missing the repository image file {$path}, or it is too large.");
            }
            $bytes = $zip->getFromName(CreateArticleBackup::REPOSITORY_FILES_DIRECTORY.$path);
            $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'][pathinfo($path, PATHINFO_EXTENSION)];
            $info = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
            if ($info === false || $info['mime'] !== $mime || ! hash_equals($image['sha256'], hash('sha256', $bytes)) || strlen($bytes) !== ($image['size'] ?? null)) {
                throw new RuntimeException("The repository image file {$path} in the backup archive is damaged.");
            }
        }

        return $images;
    }

    /** @param list<array<string, mixed>> $images */
    private function assertRepositoryImagesDoNotConflict(array $images): void
    {
        $disk = Storage::disk(ArticleImage::disk());
        foreach ($images as $image) {
            $storagePath = RepositoryImageStore::DIRECTORY.'/'.$image['path'];
            if ($disk->exists($storagePath) && ! hash_equals($image['sha256'], hash('sha256', $disk->get($storagePath)))) {
                throw new RuntimeException("Repository image \"{$image['path']}\" already exists with different content; nothing was restored.");
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $images
     * @param  list<string>  $written
     */
    private function restoreRepositoryImages(ZipArchive $zip, array $images, array &$written, Closure $check): void
    {
        $disk = Storage::disk(ArticleImage::disk());
        foreach ($images as $image) {
            $check();
            $path = RepositoryImageStore::DIRECTORY.'/'.$image['path'];
            if (! $disk->exists($path)) {
                $bytes = $zip->getFromName(CreateArticleBackup::REPOSITORY_FILES_DIRECTORY.$image['path']);
                if ($bytes === false) {
                    throw new RuntimeException('A repository image file could not be read from the backup.');
                }
                $written[] = $path;
                if (! $disk->put($path, $bytes)) {
                    throw new RuntimeException('A repository image file could not be restored.');
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $article
     * @param  array<string, int>  $namespaceIds
     * @param  callable(?string): int  $resolveUser
     */
    private function storeArticle(array $article, ?int $existingId, array $namespaceIds, callable $resolveUser, bool $restoreSources): int
    {
        $values = [
            'slug' => $article['slug'],
            'is_published' => $article['namespace'] === 'sandbox' ? false : $article['is_published'],
            'published_at' => $article['namespace'] === 'sandbox' ? null : ($article['published_at'] ?? null),
            'edit_version' => $article['edit_version'],
            'user_id' => $resolveUser($article['owner']),
            'namespace_id' => $article['namespace'] !== null ? ($namespaceIds[$article['namespace']] ?? null) : null,
            'zenn_repository' => $article['zenn_repository'],
            'zenn_branch' => $article['zenn_branch'],
            'zenn_path' => $article['zenn_path'],
            'zenn_sha' => $article['zenn_sha'],
            'zenn_local_sha' => $article['zenn_local_sha'],
            'zenn_frontmatter' => $article['zenn_frontmatter'] !== null ? json_encode($article['zenn_frontmatter'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => $article['created_at'],
            'updated_at' => $article['updated_at'],
        ];

        if ($existingId === null) {
            $existingId = DB::table('articles')->insertGetId(['sync_id' => $article['sync_id'], ...$values]);
        } else {
            $existingNamespace = DB::table('articles')->where('id', $existingId)->value('namespace_id');
            if (DB::table('article_namespaces')->where('id', $existingNamespace)->value('slug') === 'sandbox' && $article['namespace'] !== 'sandbox') {
                throw new RuntimeException('Sandbox documents cannot leave their reserved namespace.');
            }
            DB::table('articles')->where('id', $existingId)->update($values);
        }
        if ($restoreSources && $article['namespace'] === 'sandbox' && is_array($article['github_source'] ?? null)) {
            $source = array_intersect_key($article['github_source'], array_flip(['repository', 'branch', 'path', 'remote_sha', 'local_fingerprint', 'frontmatter', 'created_at', 'updated_at']));
            $source['frontmatter'] = $source['frontmatter'] === null ? null : json_encode($source['frontmatter'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            DB::table('article_github_sources')->updateOrInsert(['article_id' => $existingId], $source);
        } elseif ($restoreSources && $article['namespace'] === 'sandbox') {
            DB::table('article_github_sources')->where('article_id', $existingId)->delete();
        }

        return $existingId;
    }

    /**
     * Replaces the article's translations, revisions and reviews. The query
     * builder is used on purpose: model events would record new revisions.
     *
     * @param  array<string, mixed>  $article
     * @param  callable(?string): ?int  $findUser
     */
    private function storeHistory(int $articleId, array $article, callable $findUser): void
    {
        DB::table('article_translation_reviews')->where('article_id', $articleId)->delete();
        DB::table('article_translations')->where('article_id', $articleId)->delete();
        DB::table('article_revisions')->where('article_id', $articleId)->delete();

        $revisionIds = [];

        foreach ($article['revisions'] as $revision) {
            $revisionIds[$revision['id']] = DB::table('article_revisions')->insertGetId([
                'article_id' => $articleId,
                'locale' => $revision['locale'],
                'title' => $revision['title'],
                'content' => $revision['content'],
                'is_deleted' => $revision['is_deleted'],
                'user_id' => $findUser($revision['user']),
                'restored_from_id' => $revisionIds[$revision['restored_from_id']] ?? null,
                'created_at' => $revision['created_at'],
            ]);
        }

        foreach ($article['translations'] as $translation) {
            DB::table('article_translations')->insert([
                'article_id' => $articleId,
                'locale' => $translation['locale'],
                'title' => $translation['title'],
                'content' => $translation['content'],
                'current_revision_id' => $revisionIds[$translation['current_revision_id']] ?? null,
                'created_at' => $translation['created_at'],
                'updated_at' => $translation['updated_at'],
                'deleted_at' => $translation['deleted_at'],
            ]);
        }

        foreach ($article['reviews'] as $review) {
            if (! isset($revisionIds[$review['en_revision_id']], $revisionIds[$review['ja_revision_id']])) {
                continue;
            }

            DB::table('article_translation_reviews')->insert([
                'article_id' => $articleId,
                'en_revision_id' => $revisionIds[$review['en_revision_id']],
                'ja_revision_id' => $revisionIds[$review['ja_revision_id']],
                'user_id' => $findUser($review['user']),
                'created_at' => $review['created_at'],
            ]);
        }
    }

    /**
     * Check every image in the archive before anything is written: the path
     * must be a content-hash name, the file must be present, and its bytes
     * must hash to that name. A damaged archive therefore fails up front
     * instead of half way through the restore.
     *
     * @param  list<array<string, mixed>>  $images
     * @return list<array<string, mixed>>
     */
    private function verifiedImages(ZipArchive $zip, array $images): array
    {
        foreach ($images as $image) {
            $imagePath = (string) $image['path'];

            if (preg_match('/\A([0-9a-f]{64})\.(png|jpg|gif|webp)\z/', $imagePath, $matches) !== 1) {
                throw new RuntimeException('The backup archive contains an invalid image path.');
            }

            $stream = $zip->getStream(CreateArticleBackup::FILES_DIRECTORY.$imagePath);

            if (! is_resource($stream)) {
                throw new RuntimeException("The backup archive is missing the image file {$imagePath}.");
            }

            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            fclose($stream);

            if (! hash_equals($matches[1], hash_final($hash))) {
                throw new RuntimeException("The image file {$imagePath} in the backup archive is damaged.");
            }
        }

        return $images;
    }

    /**
     * Image files are named after their content, so an existing file is already
     * the right one and is never overwritten. Newly written paths are collected
     * so a failed restore can remove them again.
     *
     * @param  list<array<string, mixed>>  $images
     * @param  callable(?string): int  $resolveUser
     * @param  list<string>  $written
     */
    private function restoreImages(ZipArchive $zip, array $images, callable $resolveUser, array &$written, Closure $check): int
    {
        $disk = Storage::disk(ArticleImage::disk());

        foreach ($images as $image) {
            $check();
            $imagePath = ArticleImage::DIRECTORY.'/'.$image['path'];

            if (! $disk->exists($imagePath)) {
                $written[] = $imagePath;
                if (! $disk->put($imagePath, (string) $zip->getFromName(CreateArticleBackup::FILES_DIRECTORY.$image['path']))) {
                    throw new RuntimeException('An image file could not be restored.');
                }
            }

            DB::table('article_images')->updateOrInsert(['path' => $image['path']], [
                'user_id' => $resolveUser($image['owner']),
                'mime' => $image['mime'],
                'size' => $image['size'],
                'created_at' => $image['created_at'],
                'updated_at' => $image['updated_at'],
            ]);
        }

        return count($images);
    }

    /**
     * @param  list<string>  $paths
     */
    private function discardWrittenImages(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk(ArticleImage::disk())->delete($path);
        }
    }
}
