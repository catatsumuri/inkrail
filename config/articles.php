<?php

return [
    'locales' => ['en', 'ja'],
    'public_index_enabled' => env('ARTICLES_PUBLIC_INDEX_ENABLED', true),
    'zenn_repository_path' => env('ZENN_REPOSITORY_PATH'),

    /*
    | Email addresses (comma separated in ZENN_PUBLISHERS) of the users allowed to
    | send their articles to the Zenn GitHub repository. When empty, nobody can.
    */
    'zenn_publishers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ZENN_PUBLISHERS', '')),
    ))),
];
