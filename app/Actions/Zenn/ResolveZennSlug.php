<?php

namespace App\Actions\Zenn;

use Illuminate\Support\Str;

/**
 * Build a Zenn slug that satisfies Zenn's length and character rules and is unlikely to collide site-wide.
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
        $base = Str::limit($base, self::MAX_LENGTH - self::SUFFIX_LENGTH - 1, '');
        $base = strlen($base) + self::SUFFIX_LENGTH + 1 < self::MIN_LENGTH ? "article-{$base}" : $base;

        $existingFilenames = is_array($existingFilenames) ? $existingFilenames : iterator_to_array($existingFilenames);

        do {
            $slug = $base.'-'.bin2hex(random_bytes(self::SUFFIX_LENGTH / 2));
        } while (in_array("{$slug}.md", $existingFilenames, true));

        return $slug;
    }
}
