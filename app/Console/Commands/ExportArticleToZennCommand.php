<?php

namespace App\Console\Commands;

use App\Actions\Zenn\RenderZennArticle;
use App\Actions\Zenn\ResolveZennSlug;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportArticleToZennCommand extends Command
{
    /** @var string */
    protected $signature = 'articles:export-zenn
        {slug : InkRail article slug}
        {--path= : Zenn repository directory (defaults to ZENN_REPOSITORY_PATH)}';

    /** @var string */
    protected $description = 'Write the Japanese version of an article as a Zenn article file';

    public function handle(RenderZennArticle $render, ResolveZennSlug $resolveSlug): int
    {
        $repository = $this->option('path') ?: config('articles.zenn_repository_path');

        if (! is_string($repository) || ! is_dir($repository.'/articles')) {
            $this->error('Zenn repository with an articles directory was not found. Pass --path or set ZENN_REPOSITORY_PATH.');

            return self::FAILURE;
        }

        $article = Article::query()->where('slug', $this->argument('slug'))->first();

        if (! $article instanceof Article) {
            $this->error('Article was not found.');

            return self::FAILURE;
        }

        $markdown = $render($article);

        if ($markdown === null) {
            $this->error('The article has no Japanese version to export.');

            return self::FAILURE;
        }

        $files = File::glob($repository.'/articles/*.md');
        $linkedFiles = array_values(array_filter($files, function (string $file) use ($article): bool {
            preg_match('/\A---\R(.*?)\R---(?:\R|\z)/s', File::get($file), $matches);

            return isset($matches[1]) && preg_match('/^inkrail_id: "'.preg_quote($article->sync_id, '/').'"\r?$/m', $matches[1]) === 1;
        }));

        if (count($linkedFiles) > 1) {
            $this->error('Multiple Zenn files have the same InkRail identity. Export was stopped.');

            return self::FAILURE;
        }

        $zennSlug = $linkedFiles !== []
            ? basename($linkedFiles[0], '.md')
            : $resolveSlug($article->slug, array_map(basename(...), $files));
        $path = "{$repository}/articles/{$zennSlug}.md";

        File::put($path, $markdown);

        $this->info("Exported to {$path}");

        return self::SUCCESS;
    }
}
