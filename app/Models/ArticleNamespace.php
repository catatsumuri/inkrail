<?php

namespace App\Models;

use Database\Factories\ArticleNamespaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Article grouping metadata, independent of ownership and publication permissions.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 */
#[Fillable(['name', 'slug'])]
class ArticleNamespace extends Model
{
    protected static function booted(): void
    {
        static::updating(function (ArticleNamespace $namespace): void {
            if ($namespace->getRawOriginal('slug') === 'sandbox' && $namespace->isDirty(['slug', 'name'])) {
                throw new \LogicException('The sandbox namespace is reserved.');
            }
        });
        static::deleting(function (ArticleNamespace $namespace): void {
            if ($namespace->slug === 'sandbox') {
                throw new \LogicException('The sandbox namespace is reserved.');
            }
        });
    }

    /** @use HasFactory<ArticleNamespaceFactory> */
    use HasFactory;

    /** @return HasMany<Article, $this> */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'namespace_id');
    }
}
