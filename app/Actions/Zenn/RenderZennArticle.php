<?php

namespace App\Actions\Zenn;

use App\Models\Article;

/**
 * Render the Japanese version of an article as a Zenn article file.
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

        $title = json_encode($translation->title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $published = $article->is_published ? 'true' : 'false';

        return "---\ntitle: {$title}\nemoji: \"📝\"\ntype: \"tech\"\ntopics: []\npublished: {$published}\ninkrail_id: \"{$article->sync_id}\"\n---\n\n{$translation->content}\n";
    }
}
