<?php

namespace App\Actions\Zenn;

use Illuminate\Support\Str;

/**
 * Preserve readable slugs, adding a suffix only for short names or filename collisions.
 */
class ResolveZennSlug
{
    private const SUFFIX_LENGTH = 8;

    private const MAX_LENGTH = 50;

    private const MIN_LENGTH = 12;

    /**
     * Generate a new slug without adopting another article by its filename prefix.
     *
     * @param  iterable<string>  $existingFilenames  Filenames in the Zenn articles directory.
     */
    public function __invoke(string $articleSlug, iterable $existingFilenames): string
    {
        $base = Str::of($articleSlug)->lower()->replaceMatches('/[^a-z0-9_-]+/', '-')->trim('-_')->toString();
        $existingFilenames = is_array($existingFilenames) ? $existingFilenames : iterator_to_array($existingFilenames);
        $candidate = Str::limit($base, self::MAX_LENGTH, '');

        if (strlen($candidate) >= self::MIN_LENGTH && ! in_array("{$candidate}.md", $existingFilenames, true)) {
            return $candidate;
        }

        $base = Str::limit($base, self::MAX_LENGTH - self::SUFFIX_LENGTH - 1, '');
        $base = strlen($base) + self::SUFFIX_LENGTH + 1 < self::MIN_LENGTH ? "article-{$base}" : $base;

        do {
            $slug = $base.'-'.bin2hex(random_bytes(self::SUFFIX_LENGTH / 2));
        } while (in_array("{$slug}.md", $existingFilenames, true));

        return $slug;
    }
}
