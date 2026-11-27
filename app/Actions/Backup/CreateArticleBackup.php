<?php

namespace App\Actions\Backup;

use App\Actions\StoreArticleImage;
use App\Models\ArticleImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Writes every article, with its translations, revision history and uploaded
 * images, into one zip archive that RestoreArticleBackup can read back.
 */
class CreateArticleBackup
{
    public const FORMAT = 1;

    public const DATA_FILE = 'data.json';

    public const MANIFEST_FILE = 'manifest.json';

    public const FILES_DIRECTORY = '_files/';

    /**
     * @return array{articles: int, namespaces: int, images: int, missing_images: list<string>}
     */
    public function __invoke(string $path, ?string $description = null): array
    {
        File::ensureDirectoryExists(dirname($path));

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Failed to create zip: {$path}");
        }

        $usernames = DB::table('users')->pluck('username', 'id');

        $namespaces = DB::table('article_namespaces')->orderBy('id')->get();
        $namespaceSlugs = $namespaces->pluck('slug', 'id');

        $articles = DB::table('articles')->orderBy('id')->get()
            ->map(fn (\stdClass $article): array => $this->articleData($article, $usernames, $namespaceSlugs))
            ->all();

        $images = [];
        $missingImages = [];
        $disk = Storage::disk(ArticleImage::disk());

        foreach (ArticleImage::query()->orderBy('id')->get() as $image) {
            if (! $disk->exists($image->storagePath())) {
                $missingImages[] = $image->path;

                continue;
            }

            $zip->addFromString(self::FILES_DIRECTORY.$image->path, (string) $disk->get($image->storagePath()));
            $images[] = [
                'path' => $image->path,
                'mime' => $image->mime,
                'size' => $image->size,
                'owner' => $usernames->get($image->user_id),
                'created_at' => $image->created_at?->toIso8601String(),
                'updated_at' => $image->updated_at?->toIso8601String(),
            ];
        }

        foreach ($this->untrackedReferencedImages() as $path => $mime) {
            if (! $disk->exists(ArticleImage::DIRECTORY.'/'.$path)) {
                $missingImages[] = $path;

                continue;
            }

            $zip->addFromString(self::FILES_DIRECTORY.$path, (string) $disk->get(ArticleImage::DIRECTORY.'/'.$path));
            $images[] = [
                'path' => $path,
                'mime' => $mime,
                'size' => $disk->size(ArticleImage::DIRECTORY.'/'.$path),
                'owner' => null,
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];
        }

        $data = [
            'namespaces' => $namespaces->map(fn (object $namespace): array => [
                'name' => $namespace->name,
                'slug' => $namespace->slug,
                'created_at' => $namespace->created_at,
                'updated_at' => $namespace->updated_at,
            ])->all(),
            'articles' => $articles,
            'images' => $images,
        ];

        $zip->addFromString(self::MANIFEST_FILE, $this->encode([
            'format' => self::FORMAT,
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
            'created_at' => now()->toIso8601String(),
            'article_count' => count($articles),
            'namespace_count' => count($data['namespaces']),
            'image_count' => count($images),
        ]));
        $zip->addFromString(self::DATA_FILE, $this->encode($data));

        $zip->close();

        return [
            'articles' => count($articles),
            'namespaces' => count($data['namespaces']),
            'images' => count($images),
            'missing_images' => $missingImages,
        ];
    }

    /**
     * Images that articles, revisions or eyecatches point to but that have no
     * article_images row, mapped to their MIME type. Without this they would
     * silently be left out of the archive.
     *
     * @return array<string, string>
     */
    private function untrackedReferencedImages(): array
    {
        $known = array_flip(ArticleImage::query()->pluck('path')->all());
        $pattern = '/(?<![0-9a-f])[0-9a-f]{64}\.(png|jpg|gif|webp)\b/';
        $found = [];

        $scan = function (?string $text) use ($pattern, $known, &$found): void {
            if ($text !== null && preg_match_all($pattern, $text, $matches) > 0) {
                foreach ($matches[0] as $index => $path) {
                    if (! isset($known[$path])) {
                        $found[$path] = array_flip(StoreArticleImage::EXTENSIONS)[$matches[1][$index]];
                    }
                }
            }
        };

        foreach (['article_revisions', 'article_translations'] as $table) {
            foreach (DB::table($table)->select('content')->orderBy('id')->cursor() as $row) {
                $scan($row->content);
            }
        }

        foreach (DB::table('articles')->whereNotNull('zenn_frontmatter')->pluck('zenn_frontmatter') as $frontmatter) {
            $scan($frontmatter);
        }

        return $found;
    }

    /**
     * @param  Collection<int|string, string|null>  $usernames
     * @param  Collection<int|string, string>  $namespaceSlugs
     * @return array<string, mixed>
     */
    private function articleData(\stdClass $article, $usernames, $namespaceSlugs): array
    {
        $revisions = DB::table('article_revisions')->where('article_id', $article->id)->orderBy('id')->get();

        return [
            'sync_id' => $article->sync_id,
            'slug' => $article->slug,
            'is_published' => (bool) $article->is_published,
            'published_at' => $article->published_at,
            'edit_version' => $article->edit_version,
            'owner' => $usernames->get($article->user_id),
            'namespace' => $namespaceSlugs->get($article->namespace_id),
            'github_source' => DB::table('article_github_sources')->where('article_id', $article->id)->first(),
            'zenn_repository' => $article->zenn_repository,
            'zenn_branch' => $article->zenn_branch,
            'zenn_path' => $article->zenn_path,
            'zenn_sha' => $article->zenn_sha,
            'zenn_local_sha' => $article->zenn_local_sha,
            'zenn_frontmatter' => $article->zenn_frontmatter !== null ? json_decode($article->zenn_frontmatter, true) : null,
            'created_at' => $article->created_at,
            'updated_at' => $article->updated_at,
            'revisions' => $revisions->map(fn (object $revision): array => [
                'id' => $revision->id,
                'locale' => $revision->locale,
                'title' => $revision->title,
                'content' => $revision->content,
                'is_deleted' => (bool) $revision->is_deleted,
                'user' => $usernames->get($revision->user_id),
                'restored_from_id' => $revision->restored_from_id,
                'created_at' => $revision->created_at,
            ])->all(),
            'translations' => DB::table('article_translations')->where('article_id', $article->id)->orderBy('id')->get()
                ->map(fn (object $translation): array => [
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'content' => $translation->content,
                    'current_revision_id' => $translation->current_revision_id,
                    'created_at' => $translation->created_at,
                    'updated_at' => $translation->updated_at,
                    'deleted_at' => $translation->deleted_at,
                ])->all(),
            'reviews' => DB::table('article_translation_reviews')->where('article_id', $article->id)->orderBy('id')->get()
                ->map(fn (object $review): array => [
                    'en_revision_id' => $review->en_revision_id,
                    'ja_revision_id' => $review->ja_revision_id,
                    'user' => $usernames->get($review->user_id),
                    'created_at' => $review->created_at,
                ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
