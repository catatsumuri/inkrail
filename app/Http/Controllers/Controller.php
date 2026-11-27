<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** @return array{title: string, url: string, meta: array<string, string>} */
    protected function socialMetadata(string $title, string $description, string $type, string $url, ?string $image = null): array
    {
        $meta = [
            'description' => $description,
            'og:title' => $title,
            'og:description' => $description,
            'og:type' => $type,
            'og:url' => $url,
            'og:site_name' => (string) config('app.name'),
            'twitter:card' => $image === null ? 'summary' : 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
        ];
        if ($image !== null) {
            $meta += ['og:image' => $image, 'og:image:alt' => $title, 'twitter:image' => $image, 'twitter:image:alt' => $title];
        }

        return ['title' => $title, 'url' => $url, 'meta' => $meta];
    }
}
