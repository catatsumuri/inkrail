<?php

namespace App\Actions\Zenn;

use App\Models\Article;

/**
 * Render the Japanese version of an article as a Zenn article file.
 * Frontmatter values are preserved semantically; source YAML formatting is not retained.
 */
class RenderZennArticle
{
    public const LOCALE = 'ja';

    /**
     * Returns null when the article has no Japanese version.
     */
    public function __invoke(Article $article): ?string
    {
        $translation = $article->translations->firstWhere('locale', self::LOCALE);

        if ($translation === null) {
            return null;
        }

        $frontmatter = array_replace([
            'title' => '', 'emoji' => '📝', 'type' => 'tech', 'topics' => [], 'published' => false,
        ], $article->zenn_frontmatter ?? [], [
            'title' => $translation->title, 'published' => $article->is_published, 'inkrail_id' => $article->sync_id,
        ]);
        unset($frontmatter['namespace'], $frontmatter['published_at']);
        if ($article->published_at !== null) {
            $frontmatter['published_at'] = $article->published_at->setTimezone('Asia/Tokyo')->format('Y-m-d H:i');
        }
        if ($article->namespace !== null) {
            $frontmatter['namespace'] = $article->namespace->slug;
        }
        $header = '';
        foreach ($frontmatter as $key => $value) {
            $header .= $key.': '.json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        }
        $ending = str_ends_with($translation->content, "\n") ? '' : "\n";

        return "---\n{$header}---\n\n{$translation->content}{$ending}";
    }

    public function localSha(Article $article): ?string
    {
        $markdown = $this($article);

        return $markdown === null ? null : sha1('blob '.strlen($markdown)."\0".$markdown);
    }
}
