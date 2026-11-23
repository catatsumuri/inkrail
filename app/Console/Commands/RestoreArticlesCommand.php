<?php

namespace App\Console\Commands;

use App\Actions\Backup\RestoreArticleBackup;
use App\Models\User;
use Illuminate\Console\Command;
use RuntimeException;

class RestoreArticlesCommand extends Command
{
    /** @var string */
    protected $signature = 'articles:restore
        {path : Path to a backup zip created by articles:backup}
        {--user= : Username that owns articles whose original owner no longer exists (defaults to the first user)}';

    /** @var string */
    protected $description = 'Restore articles, revision history and images from an articles:backup archive';

    public function handle(RestoreArticleBackup $restore): int
    {
        $fallbackUser = null;

        if (filled($this->option('user'))) {
            $fallbackUser = User::query()->where('username', $this->option('user'))->first();

            if (! $fallbackUser instanceof User) {
                $this->error("User not found: {$this->option('user')}");

                return self::FAILURE;
            }
        }

        try {
            $result = $restore($this->argument('path'), $fallbackUser);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Restored {$result['articles']} article(s) ({$result['created']} created, {$result['updated']} updated), {$result['namespaces']} namespace(s) and {$result['images']} image(s).");

        return self::SUCCESS;
    }
}
