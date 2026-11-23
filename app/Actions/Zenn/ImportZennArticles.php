<?php

namespace App\Actions\Zenn;

use App\Actions\LogSecurityEvent;
use App\Exceptions\ZennImportException;
use App\Models\Article;
use App\Models\ArticleNamespace;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type ParsedArticle from ParseZennArticle
 *
 * The unchanged count means the remote blob has not changed; local edits may remain.
 *
 * @phpstan-type ImportResult array{created: int, updated: int, unchanged: int, conflict: int, invalid: int, items: list<array{path: string, status: string}>}
 */
class ImportZennArticles
{
    public function __construct(
        private FetchZennArticles $fetch,
        private ParseZennArticle $parse,
        private RenderZennArticle $render,
        private LogSecurityEvent $logSecurityEvent,
        private WithZennSyncLock $syncLock,
    ) {}

    /** @return ImportResult */
    public function __invoke(User $owner, ?Closure $progress = null): array
    {
        Gate::forUser($owner)->authorize('import-from-zenn');
        $progress?->__invoke('progress', ['message' => 'Acquiring repository lock.']);
        try {
            return ($this->syncLock)(fn (Closure $checkLock): array => $this->import($owner, $checkLock, $progress));
        } catch (LockTimeoutException $exception) {
            throw new ZennImportException('The repository is busy or the sync lock was lost. Try again shortly.', previous: $exception);
        }
    }

    /** @return ImportResult */
    private function import(User $owner, Closure $checkLock, ?Closure $progress): array
    {
        $remote = ($this->fetch)($checkLock, $progress);
        $progress?->__invoke('progress', ['message' => 'Saving articles.']);
        $documents = [];
        $identityCounts = [];
        foreach ($remote['files'] as $file) {
            try {
                $parsed = $file['content'] === null ? null : ($this->parse)($file['content']);
            } catch (ZennImportException) {
                $parsed = null;
            }
            $documents[] = [...$file, 'parsed' => $parsed];
            if ($parsed !== null && $parsed['sync_id'] !== null) {
                $identityCounts[$parsed['sync_id']] = ($identityCounts[$parsed['sync_id']] ?? 0) + 1;
            }
        }

        $result = DB::transaction(function () use ($owner, $remote, $documents, $identityCounts, $checkLock): array {
            $checkLock();
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'conflict' => 0, 'invalid' => 0, 'items' => []];
            $paths = array_column($documents, 'path');
            foreach ($documents as $document) {
                $checkLock();
                $parsed = $document['parsed'];
                $status = $parsed === null || ($parsed['sync_id'] !== null && $identityCounts[$parsed['sync_id']] > 1)
                    ? 'invalid'
                    : $this->store($owner, $remote['repository'], $remote['branch'], $document['path'], $document['sha'], $parsed, $paths);
                $result[$status]++;
                $result['items'][] = ['path' => $document['path'], 'status' => $status];
            }

            $checkLock();

            return $result;
        });

        ($this->logSecurityEvent)('articles.zenn_imported', $owner, [
            'created' => $result['created'], 'updated' => $result['updated'],
            'unchanged' => $result['unchanged'], 'conflict' => $result['conflict'], 'invalid' => $result['invalid'],
        ], causer: $owner);

        return $result;
    }

    /**
     * @param  ParsedArticle  $parsed
     * @param  list<string>  $remotePaths
     * @return 'created'|'updated'|'unchanged'|'conflict'
     */
    private function store(User $owner, string $repository, string $branch, string $path, string $sha, array $parsed, array $remotePaths): string
    {
        $byPath = Article::query()->where('zenn_repository', $repository)->where('zenn_branch', $branch)->where('zenn_path', $path)->lockForUpdate()->first();
        $byIdentity = $parsed['sync_id'] === null ? null : Article::query()->where('sync_id', $parsed['sync_id'])->lockForUpdate()->first();

        if ($byPath !== null && $byIdentity !== null && ! $byPath->is($byIdentity)) {
            return 'conflict';
        }

        $article = $byIdentity ?? $byPath;
        if ($article !== null) {
            if ($article->user_id !== $owner->id || ($parsed['sync_id'] !== null && $article->sync_id !== $parsed['sync_id'])) {
                return 'conflict';
            }
            if ($article->zenn_path !== null && ($article->zenn_repository !== $repository || $article->zenn_branch !== $branch
                || ($article->zenn_path !== $path && in_array($article->zenn_path, $remotePaths, true)))) {
                return 'conflict';
            }

            if ($article->zenn_path === $path && $article->zenn_sha === $sha) {
                return 'unchanged';
            }

            $localSha = $this->render->localSha($article);
            $baseline = $article->zenn_local_sha ?? $article->zenn_sha;
            if ($baseline === null) {
                $japanese = $article->translations->firstWhere('locale', RenderZennArticle::LOCALE);
                if ($japanese === null || $japanese->title !== $parsed['title'] || $japanese->content !== $parsed['content'] || $article->is_published !== $parsed['is_published']) {
                    return 'conflict';
                }
            } elseif ($localSha === null || $localSha !== $baseline) {
                return 'conflict';
            }
        }

        $created = $article === null;
        if ($article === null) {
            $slug = Str::slug(basename($path, '.md'));
            if ($slug === '' || Article::query()->where('slug', $slug)->exists()) {
                $slug = Str::lower((string) Str::ulid());
            }
            $article = new Article(['slug' => $slug, 'is_published' => $parsed['is_published']]);
            $article->user_id = $owner->id;
            if ($parsed['sync_id'] !== null) {
                $article->sync_id = $parsed['sync_id'];
            }
        }

        if (array_key_exists('namespace', $parsed['frontmatter'])) {
            $namespaceSlug = $parsed['frontmatter']['namespace'];
            $article->namespace()->associate($namespaceSlug === null ? null : ArticleNamespace::query()->firstOrCreate(
                ['slug' => $namespaceSlug],
                ['name' => $namespaceSlug],
            ));
        }
        $article->is_published = $parsed['is_published'];
        $article->zenn_repository = $repository;
        $article->zenn_branch = $branch;
        $article->zenn_path = $path;
        $article->zenn_sha = $sha;
        $article->zenn_frontmatter = $parsed['frontmatter'];
        $editableMetadataChanged = $article->exists && $article->isDirty(['is_published', 'zenn_frontmatter']);
        $article->save();
        if ($editableMetadataChanged) {
            $article->increment('edit_version');
        }
        $article->replaceTranslations([RenderZennArticle::LOCALE => ['title' => $parsed['title'], 'content' => $parsed['content']]]);
        $article->zenn_local_sha = $this->render->localSha($article);
        $article->save();

        return $created ? 'created' : 'updated';
    }
}
