<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    protected static function booted(): void
    {
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
