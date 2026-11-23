<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $content
 * @property string $slug
 * @property bool $is_published
 * @property int $edit_version
 * @property string $sync_id
 * @property string|null $zenn_repository
 * @property string|null $zenn_branch
 * @property string|null $zenn_path
 * @property string|null $zenn_sha SHA of the exact remote blob at the last sync.
 * @property string|null $zenn_local_sha SHA of the locally rendered article at the last sync; formatting may differ from the remote blob.
 * @property array<string, mixed>|null $zenn_frontmatter
 */
#[Fillable(['title', 'content', 'slug', 'is_published'])]
#[Hidden(['sync_id', 'zenn_repository', 'zenn_branch', 'zenn_path', 'zenn_sha', 'zenn_local_sha', 'zenn_frontmatter'])]
class Article extends Model
{
    /** @var array<string, string> */
    private array $pendingTranslation = [];

    /** @var list<string> */
    protected $with = ['translations'];

    /** @return HasMany<ArticleTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ArticleTranslation::class);
    }

    /** @return HasMany<ArticleTranslation, $this> */
    public function allTranslations(): HasMany
    {
        return $this->hasMany(ArticleTranslation::class)->withTrashed();
    }

    /** @return HasOne<ArticleTranslationReview, $this> */
    public function latestReview(): HasOne
    {
        return $this->hasOne(ArticleTranslationReview::class)->latestOfMany();
    }

    public function resolvedTranslation(): ?ArticleTranslation
    {
        foreach (array_unique([app()->getLocale(), config('app.fallback_locale'), ...config('articles.locales')]) as $locale) {
            $translation = $this->translations->firstWhere('locale', $locale);
            if ($translation !== null) {
                return $translation;
            }
        }

        return null;
    }

    public function getTitleAttribute(): string
    {
        return $this->pendingTranslation['title'] ?? $this->resolvedTranslation()->title ?? '';
    }

    public function getContentAttribute(): string
    {
        return $this->pendingTranslation['content'] ?? $this->resolvedTranslation()->content ?? '';
    }

    public function setTitleAttribute(string $value): void
    {
        $this->pendingTranslation['title'] = $value;
    }

    public function setContentAttribute(string $value): void
    {
        $this->pendingTranslation['content'] = $value;
    }

    /** @return array{resolvedLocale: ?string, availableLocales: list<string>} */
    public function languageMetadata(): array
    {
        return [
            'resolvedLocale' => $this->resolvedTranslation()?->locale,
            'availableLocales' => array_values($this->translations->map(fn (ArticleTranslation $translation): string => $translation->locale)->all()),
        ];
    }

    /** @param array<string, array{title: string, content: string}> $translations */
    public function replaceTranslations(array $translations): void
    {
        DB::transaction(function () use ($translations): void {
            foreach ($translations as $locale => $translation) {
                $existing = $this->translations()->withTrashed()->firstOrNew(['locale' => $locale]);
                $existing->fill($translation);
                $existing->deleted_at = null;
                $existing->save();
            }
        });
        $this->unsetRelation('translations');
        $this->unsetRelation('allTranslations');
        $this->unsetRelation('latestReview');
    }

    /** @return array<string, array{state: string, revisionId: ?int, baselineId: ?int}> */
    public function translationStatus(): array
    {
        $this->loadMissing(['allTranslations', 'latestReview']);
        $review = $this->latestReview;
        $translations = $this->allTranslations->keyBy('locale');
        $states = [];
        foreach (config('articles.locales') as $locale) {
            $translation = $translations->get($locale);
            $other = $locale === 'en' ? 'ja' : 'en';
            $otherTranslation = $translations->get($other);
            $state = $translation === null ? 'missing' : ($translation->trashed() ? 'deleted' : 'reviewed');
            if ($translation !== null && ! $translation->trashed() && $review !== null) {
                $otherChanged = $otherTranslation === null || $otherTranslation->trashed() || $otherTranslation->current_revision_id !== $review->{$other.'_revision_id'};
                $state = $otherChanged ? 'other_changed' : 'reviewed';
            } elseif ($translation !== null && ! $translation->trashed() && $otherTranslation !== null && ! $otherTranslation->trashed()) {
                $state = $otherTranslation->current_revision_id > $translation->current_revision_id ? 'other_changed' : 'reviewed';
            }
            $states[$locale] = ['state' => $state, 'revisionId' => $translation?->current_revision_id, 'baselineId' => $review?->{$locale.'_revision_id'}];
        }

        $this->unsetRelation('allTranslations');
        $this->unsetRelation('latestReview');

        return $states;
    }

    protected static function booted(): void
    {
        static::saved(function (Article $article): void {
            if ($article->pendingTranslation !== []) {
                $locale = app()->getLocale();
                $existing = $article->translations()->where('locale', $locale)->first();
                $article->replaceTranslations([$locale => [
                    'title' => $article->pendingTranslation['title'] ?? $existing->title ?? '',
                    'content' => $article->pendingTranslation['content'] ?? $existing->content ?? '',
                ]]);
                $article->pendingTranslation = [];
                $article->unsetRelation('translations');
            }
        });
        static::creating(function (Article $article): void {
            if (blank($article->sync_id)) {
                $article->sync_id = (string) Str::uuid();
            }

            if (blank($article->slug)) {
                $article->slug = Str::lower((string) Str::ulid());
            }
        });
    }

    /** @return array{is_published: 'boolean', zenn_frontmatter: 'array'} */
    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'zenn_frontmatter' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
