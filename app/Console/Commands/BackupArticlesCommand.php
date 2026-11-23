<?php

namespace App\Console\Commands;

use App\Actions\Backup\CreateArticleBackup;
use Illuminate\Console\Command;
use RuntimeException;

class BackupArticlesCommand extends Command
{
    /** @var string */
    protected $signature = 'articles:backup
        {--output= : Output zip path (defaults to ARTICLES_BACKUP_DIR/inkrail-timestamp.zip)}
        {--description= : Optional note stored in the archive manifest}';

    /** @var string */
    protected $description = 'Back up every article, its revision history and uploaded images as a zip archive';

    public function handle(CreateArticleBackup $backup): int
    {
        $path = $this->option('output') ?: CreateArticleBackup::defaultPath();

        try {
            $result = $backup($path, $this->option('description'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result['missing_images'] as $missing) {
            $this->warn("Image file is missing on disk and was skipped: {$missing}");
        }

        $this->info("Backed up {$result['articles']} article(s), {$result['namespaces']} namespace(s) and {$result['images']} image(s) to:");
        $this->line($path);

        return self::SUCCESS;
    }
}
