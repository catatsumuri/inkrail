<?php

namespace App\Support;

class ArticleEyecatch
{
    public const PATTERN = '~\A(?:/images/)?(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9][a-zA-Z0-9._-]*\.(?:png|jpg|jpeg|gif|webp)\z~';

    public static function filename(mixed $value): ?string
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            return null;
        }

        return str_starts_with($value, '/images/') ? substr($value, 8) : $value;
    }
}
