<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $article_id
 * @property string $locale
 * @property string $title
 * @property string $content
 * @property bool $is_deleted
 */
class ArticleRevision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_deleted' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Article revisions are immutable.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Article revisions are immutable.');
        });
    }
}
