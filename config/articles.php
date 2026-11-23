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

    /*
    | Where images dropped into the article editor are stored, and the longest
    | side (in pixels) they are scaled down to. Images are served from /images
    | to anyone who knows the content-hash URL, including those of drafts.
    */
    'image_disk' => env('ARTICLE_IMAGE_DISK', 'public'),
    'image_max_dimension' => 2048,

    /*
    | Where articles:backup and the dashboard keep backup archives: a disk from
    | config/filesystems.php (use an S3 compatible one such as R2 when the app
    | filesystem is not persistent) and a directory on it. Kept outside the
    | database so migrate:fresh does not remove them.
    */
    'backup_disk' => env('ARTICLES_BACKUP_DISK', 'local'),
    'backup_directory' => env('ARTICLES_BACKUP_DIR', 'backups'),
];
