<?php

namespace App\Actions\Backup;

use App\Models\ArticleImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use ZipArchive;

/**
 * Restores a CreateArticleBackup archive. Articles are matched by sync_id: an
 * existing article is overwritten (translations, revisions and reviews are
 * replaced wholesale), anything else is created. Nothing is ever deleted, and
 * the whole restore runs in one transaction.
 */
class RestoreArticleBackup
{
    /**
     * @return array{articles: int, created: int, updated: int, namespaces: int, images: int}
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
            $data = $this->readData($zip);
            $fallbackUser ??= User::query()->orderBy('id')->first();

            if (! $fallbackUser instanceof User) {
                throw new RuntimeException('Cannot restore articles without an existing user.');
            }

            $users = User::query()->whereNotNull('username')->pluck('id', 'username');
            $resolveUser = fn (?string $username): int => $users->get($username, $fallbackUser->id);

            $images = $this->verifiedImages($zip, $data['images'] ?? []);
            $writtenImages = [];

            try {
                return DB::transaction(function () use ($zip, $data, $users, $resolveUser, $images, &$writtenImages): array {
                    $namespaceIds = $this->restoreNamespaces($data['namespaces'] ?? []);
                    $counts = ['articles' => 0, 'created' => 0, 'updated' => 0];

                    foreach ($data['articles'] ?? [] as $article) {
                        $existingId = DB::table('articles')->where('sync_id', $article['sync_id'])->value('id');

                        $this->assertNoConflicts($article, $existingId);

                        $articleId = $this->storeArticle($article, $existingId, $namespaceIds, $resolveUser);
                        $this->storeHistory($articleId, $article, fn (?string $username): ?int => $users->get($username));

                        $counts['articles']++;
                        $counts[$existingId === null ? 'created' : 'updated']++;
                    }

                    return [
                        ...$counts,
                        'namespaces' => count($namespaceIds),
                        'images' => $this->restoreImages($zip, $images, $resolveUser, $writtenImages),
                    ];
                });
            } catch (\Throwable $exception) {
                $this->discardWrittenImages($writtenImages);

                throw $exception;
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
            throw new RuntimeException('Unsupported backup format.');
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
                'name' => $namespace['name'],
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

    /**
     * @param  array<string, mixed>  $article
     * @param  array<string, int>  $namespaceIds
     * @param  callable(?string): int  $resolveUser
     */
    private function storeArticle(array $article, ?int $existingId, array $namespaceIds, callable $resolveUser): int
    {
        $values = [
            'slug' => $article['slug'],
            'is_published' => $article['is_published'],
            'published_at' => $article['published_at'] ?? null,
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
            return DB::table('articles')->insertGetId(['sync_id' => $article['sync_id'], ...$values]);
        }

        DB::table('articles')->where('id', $existingId)->update($values);

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
    private function restoreImages(ZipArchive $zip, array $images, callable $resolveUser, array &$written): int
    {
        $disk = Storage::disk(ArticleImage::disk());

        foreach ($images as $image) {
            $imagePath = ArticleImage::DIRECTORY.'/'.$image['path'];

            if (! $disk->exists($imagePath)) {
                $disk->put($imagePath, (string) $zip->getFromName(CreateArticleBackup::FILES_DIRECTORY.$image['path']));
                $written[] = $imagePath;
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
