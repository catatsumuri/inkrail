<?php

namespace App\Console\Commands;

use App\Actions\ImportSandboxDocuments;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('articles:import-sandbox {--user= : Verified owner username}')]
#[Description('Import private sandbox documents from GitHub')]
class ImportSandboxDocumentsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ImportSandboxDocuments $import): int
    {
        $owner = User::query()->where('username', $this->option('user'))->first();
        if ($owner === null || ! is_string($this->option('user'))) {
            $this->error('Specify an existing owner with --user=username.');

            return self::FAILURE;
        }
        try {
            $this->line(json_encode($import($owner), JSON_THROW_ON_ERROR));
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Sandbox import failed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
