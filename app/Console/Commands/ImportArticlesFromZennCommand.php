<?php

namespace App\Console\Commands;

use App\Actions\Zenn\ImportZennArticles;
use App\Exceptions\ZennImportException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;

class ImportArticlesFromZennCommand extends Command
{
    /** @var string */
    protected $signature = 'articles:import-zenn {--user= : Email address of the verified publisher who will own the imported articles}';

    /** @var string */
    protected $description = 'Import Japanese articles from the configured Zenn GitHub repository';

    public function handle(ImportZennArticles $import): int
    {
        $email = $this->option('user');
        $owner = is_string($email) && $email !== '' ? User::query()->where('email', $email)->first() : null;
        if ($owner === null) {
            $this->error('Specify an existing publisher with --user=email@example.com.');

            return self::FAILURE;
        }

        try {
            $result = $import($owner);
        } catch (ZennImportException|AuthorizationException $exception) {
            $this->error($exception instanceof AuthorizationException ? 'The owner must be a verified Zenn publisher.' : $exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Path', 'Result'], array_map(fn (array $item): array => [$item['path'], $item['status'] === 'unchanged' ? 'remote_unchanged' : $item['status']], $result['items']));
        $this->info("Created: {$result['created']}; updated: {$result['updated']}; remote unchanged: {$result['unchanged']}; conflicts: {$result['conflict']}; invalid: {$result['invalid']}.");

        return $result['conflict'] > 0 || $result['invalid'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
