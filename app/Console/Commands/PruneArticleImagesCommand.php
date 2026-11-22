<?php

namespace App\Console\Commands;

use App\Models\ArticleImage;
use App\Models\ArticleRevision;
use App\Models\ArticleTranslation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneArticleImagesCommand extends Command
{
    /** @var string */
    protected $signature = 'article-images:prune {--days=7 : Keep images touched within this many days}';

    /** @var string */
    protected $description = 'Delete uploaded images that no article or revision references';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $deleted = 0;

        ArticleImage::query()->where('updated_at', '<', now()->subDays($days))->each(function (ArticleImage $image) use (&$deleted): void {
            if ($this->isReferenced($image)) {
                return;
            }

            Storage::disk(ArticleImage::disk())->delete($image->storagePath());
            $image->delete();
            $deleted++;
        });

        $this->info("Deleted {$deleted} unreferenced image(s).");

        return self::SUCCESS;
    }

    /**
     * Revisions count as references so that restoring an old version never shows a broken image.
     */
    private function isReferenced(ArticleImage $image): bool
    {
        return ArticleTranslation::withTrashed()->where('content', 'like', "%{$image->path}%")->exists()
            || ArticleRevision::query()->where('content', 'like', "%{$image->path}%")->exists();
    }
}
