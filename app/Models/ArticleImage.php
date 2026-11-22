<?php

namespace App\Models;

use Database\Factories\ArticleImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An image uploaded for use in article Markdown.
 *
 * The path is the SHA-256 of the stored bytes plus an extension, so identical
 * uploads share one file. Articles reference it as `/images/{path}`; which
 * articles do is derived from their content, not stored here.
 *
 * @property int $id
 * @property int $user_id
 * @property string $path
 * @property string $mime
 * @property int $size
 */
#[Fillable(['user_id', 'path', 'mime', 'size'])]
class ArticleImage extends Model
{
    /** @use HasFactory<ArticleImageFactory> */
    use HasFactory;

    public const DIRECTORY = 'article-images';

    public static function disk(): string
    {
        return config('articles.image_disk');
    }

    public function storagePath(): string
    {
        return self::DIRECTORY.'/'.$this->path;
    }

    public function url(): string
    {
        return '/images/'.$this->path;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
