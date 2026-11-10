<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $content
 * @property string $slug
 * @property bool $is_published
 */
#[Fillable(['title', 'content', 'slug', 'is_published'])]
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
            $this->translations()->whereNotIn('locale', array_keys($translations))->delete();
            foreach ($translations as $locale => $translation) {
                $this->translations()->updateOrCreate(['locale' => $locale], $translation);
            }
        });
        $this->unsetRelation('translations');
    }

    protected static function booted(): void
    {
        static::saved(function (Article $article): void {
            if ($article->pendingTranslation !== []) {
                $locale = app()->getLocale();
                $existing = $article->translations()->where('locale', $locale)->first();
                $article->translations()->updateOrCreate(['locale' => $locale], [
                    'title' => $article->pendingTranslation['title'] ?? $existing->title ?? '',
                    'content' => $article->pendingTranslation['content'] ?? $existing->content ?? '',
                ]);
                $article->pendingTranslation = [];
                $article->unsetRelation('translations');
            }
        });
        static::creating(function (Article $article): void {
            if (blank($article->slug)) {
                $article->slug = Str::lower((string) Str::ulid());
            }
        });
    }

    /** @return array{is_published: 'boolean'} */
    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
