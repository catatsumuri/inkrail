<?php

namespace App\Console\Commands;

use App\Actions\Backup\RestoreArticleBackup;
use App\Models\User;
use App\Support\ArticleBackupStore;
use Illuminate\Console\Command;
use RuntimeException;

class RestoreArticlesCommand extends Command
{
    /** @var string */
    protected $signature = 'articles:restore
        {path : Path to a backup zip, or the name of a backup on the backup disk}
        {--user= : Username that owns articles whose original owner no longer exists (defaults to the first user)}';

    /** @var string */
    protected $description = 'Restore articles, revision history and images from an articles:backup archive';

    public function handle(RestoreArticleBackup $restore, ArticleBackupStore $store): int
    {
        $fallbackUser = null;

        if (filled($this->option('user'))) {
            $fallbackUser = User::query()->where('username', $this->option('user'))->first();

            if (! $fallbackUser instanceof User) {
                $this->error("User not found: {$this->option('user')}");

                return self::FAILURE;
            }
        }

        $path = $this->argument('path');
        $temporary = null;

        try {
            if (! is_file($path) && $store->exists($path)) {
                $path = $temporary = $store->copyToTemporaryFile($path);
            }

            $result = $restore($path, $fallbackUser);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($temporary !== null) {
                @unlink($temporary);
            }
        }

        $this->info("Restored {$result['articles']} article(s) ({$result['created']} created, {$result['updated']} updated), {$result['namespaces']} namespace(s) and {$result['images']} image(s).");
        if ($result['skipped_github_sources'] > 0) {
            $this->warn("{$result['skipped_github_sources']} Sandbox GitHub source link(s) from another environment were not restored.");
        }

        return self::SUCCESS;
    }
}
