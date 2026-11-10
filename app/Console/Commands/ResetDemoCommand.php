<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResetDemoCommand extends Command
{
    /** @var string */
    protected $signature = 'demo:reset';

    /** @var string */
    protected $description = 'Reset the SQLite live demo to its seeded state';

    public function handle(): int
    {
        if (! config('app.demo_mode') || DB::connection()->getDriverName() !== 'sqlite') {
            $this->error('Demo reset requires DEMO_MODE=true and a SQLite database.');

            return self::FAILURE;
        }

        FreshCommand::prohibit(false);
        WipeCommand::prohibit(false);

        try {
            $exitCode = $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

            if ($exitCode === self::SUCCESS) {
                Log::notice('Live demo database reset to its seeded state.');
            }

            return $exitCode;
        } finally {
            FreshCommand::prohibit(app()->isProduction());
            WipeCommand::prohibit(app()->isProduction());
        }
    }
}
