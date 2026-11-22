<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * @property string $locale
 * @property string $title
 * @property string $content
 * @property int $article_id
 * @property ?int $current_revision_id
 */
#[Fillable(['locale', 'title', 'content'])]
class ArticleTranslation extends Model
{
    use SoftDeletes;

    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    public function delete(): ?bool
    {
        return DB::transaction(fn (): ?bool => parent::delete());
    }

    protected static function booted(): void
    {
        static::saved(function (ArticleTranslation $translation): void {
            if ($translation->current_revision_id === null || $translation->wasChanged(['title', 'content', 'deleted_at'])) {
                $translation->recordRevision();
            }
        });
        static::deleted(function (ArticleTranslation $translation): void {
            if (! $translation->isForceDeleting()) {
                $translation->recordRevision();
            }
        });
    }

    public function recordRevision(?int $restoredFromId = null): void
    {
        $otherLocale = $this->locale === 'en' ? 'ja' : 'en';
        $other = self::where('article_id', $this->article_id)->where('locale', $otherLocale)->first();
        $review = ArticleTranslationReview::where('article_id', $this->article_id)->latest('id')->first();
        $catchingUp = ! $this->trashed() && $other !== null && ($review !== null
            ? $other->current_revision_id !== $review->{$otherLocale.'_revision_id'}
            : ($this->current_revision_id === null || $other->current_revision_id > $this->current_revision_id));
        $revision = ArticleRevision::create([
            'article_id' => $this->article_id, 'locale' => $this->locale,
            'title' => $this->title, 'content' => $this->content,
            'is_deleted' => $this->trashed(), 'user_id' => auth()->id(), 'restored_from_id' => $restoredFromId,
        ]);
        $this->forceFill(['current_revision_id' => $revision->id])->saveQuietly();
        if ($catchingUp) {
            ArticleTranslationReview::create([
                'article_id' => $this->article_id,
                'en_revision_id' => $this->locale === 'en' ? $revision->id : $other->current_revision_id,
                'ja_revision_id' => $this->locale === 'ja' ? $revision->id : $other->current_revision_id,
                'user_id' => auth()->id(),
            ]);
        }
        Article::whereKey($this->article_id)->increment('edit_version');
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
