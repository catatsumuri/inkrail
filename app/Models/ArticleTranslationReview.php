<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $en_revision_id
 * @property int $ja_revision_id
 */
class ArticleTranslationReview extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Translation reviews are immutable.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Translation reviews are immutable.');
        });
    }
}
