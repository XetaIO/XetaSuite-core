<?php

declare(strict_types=1);

namespace XetaSuite\Console\Commands;

use Illuminate\Console\Command;

class ResetDemoDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'demo:reset';

    /**
     * The console command description.
     */
    protected $description = 'Reset the database to its demo state with fresh seed data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('app.demo_mode')) {
            $this->error('This command can only be run in demo mode (DEMO_MODE=true).');

            return self::FAILURE;
        }

        $this->call('down', ['--retry' => 10]);

        try {
            $this->info('Resetting demo database...');

            $this->call('migrate:fresh', [
                '--force' => true,
                '--seed' => true,
            ]);

            $this->info('Demo database has been reset successfully!');
        } finally {
            $this->call('up');
        }

        return self::SUCCESS;
    }
}
