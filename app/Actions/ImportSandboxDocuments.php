<?php

namespace App\Actions;

use App\Actions\Zenn\FetchZennArticles;
use App\Actions\Zenn\WithZennSyncLock;
use App\Models\Article;
use App\Models\ArticleNamespace;
use App\Models\User;
use App\Support\RepositoryImageStore;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ImportSandboxDocuments
{
    public function __construct(private FetchZennArticles $fetch, private WithZennSyncLock $lock, private LogSecurityEvent $audit, private RepositoryImageStore $images) {}

    /** @return array<string, mixed> */
    public function __invoke(User $owner, ?Closure $progress = null): array
    {
        Gate::forUser($owner)->authorize('import-from-zenn');

        $progress?->__invoke('progress', ['message' => 'Acquiring repository lock.']);

        return ($this->lock)(function (Closure $check) use ($owner, $progress): array {
            $remote = ($this->fetch)($check, $progress, scope: 'sandbox');
            $progress?->__invoke('progress', ['message' => 'Saving articles.']);
            $counts = $this->images->importFiles($remote['images'], $check);
            foreach ($remote['files'] as $file) {
                $check();
                try {
                    if ($file['content'] === null) {
                        throw new \InvalidArgumentException('Invalid blob.');
                    }
                    $content = $file['content'];
                    $metadata = [];
                    if (preg_match('/\A---\R/', $content) === 1 && preg_match('/\A---\R(.*?)\R---(?:\R|\z)/s', $content) !== 1) {
                        throw new \InvalidArgumentException('Unclosed frontmatter.');
                    }
                    if (preg_match('/\A---\R(.*?)\R---(?:\R|\z)/s', $content, $matches) === 1) {
                        $metadata = Yaml::parse($matches[1]) ?? [];
                        if (! is_array($metadata)) {
                            throw new \InvalidArgumentException('Invalid frontmatter.');
                        }
                        $content = substr($content, strlen($matches[0]));
                    }
                    preg_match('/^#\s+(.+)$/m', $content, $heading);
                    $title = $metadata['title'] ?? ($heading[1] ?? basename($file['path'], '.md'));
                    if (! is_string($title) || trim($title) === '' || mb_strlen($title) > 255) {
                        throw new \InvalidArgumentException('Invalid title.');
                    }
                    $status = DB::transaction(function () use ($owner, $remote, $file, $title, $content, $metadata, $check): string {
                        User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
                        $source = DB::table('article_github_sources')->where('repository', $remote['repository'])->where('branch', $remote['branch'])->where('path', $file['path'])->lockForUpdate()->first();
                        $article = $source === null ? null : Article::query()->where('id', $source->article_id)->lockForUpdate()->firstOrFail();
                        if ($article !== null) {
                            if ($article->user_id !== $owner->id || ! $article->isSandbox()) {
                                return 'conflict';
                            }
                            if ($source->remote_sha === $file['sha']) {
                                return 'unchanged';
                            }
                            $translation = $article->translations->firstWhere('locale', 'ja');
                            if ($translation === null || self::fingerprint($translation->title, $translation->content) !== $source->local_fingerprint) {
                                return 'conflict';
                            }
                        }
                        $created = $article === null;
                        if ($article === null) {
                            $article = new Article;
                            $article->user_id = $owner->id;
                            $article->namespace()->associate(ArticleNamespace::query()->firstOrCreate(['slug' => 'sandbox'], ['name' => 'Sandbox']));
                            $article->save();
                        }
                        $article->replaceTranslations(['ja' => ['title' => $title, 'content' => $content]]);
                        DB::table('article_github_sources')->updateOrInsert(['article_id' => $article->id], [
                            'repository' => $remote['repository'], 'branch' => $remote['branch'], 'path' => $file['path'],
                            'remote_sha' => $file['sha'], 'local_fingerprint' => self::fingerprint($title, $content),
                            'frontmatter' => json_encode($metadata, JSON_THROW_ON_ERROR), 'updated_at' => now(), 'created_at' => $source->created_at ?? now(),
                        ]);
                        $check();

                        return $created ? 'created' : 'updated';
                    });
                } catch (ParseException|\InvalidArgumentException|\JsonException) {
                    $status = 'invalid';
                }
                $counts[$status]++;
                $counts['items'][] = ['path' => $file['path'], 'status' => $status];
            }
            ($this->audit)('articles.sandbox_imported', $owner, array_intersect_key($counts, array_flip(['created', 'updated', 'unchanged', 'conflict', 'invalid'])), causer: $owner);

            return $counts;
        });
    }

    private static function fingerprint(string $title, string $content): string
    {
        return hash('sha256', serialize([$title, $content]));
    }
}
