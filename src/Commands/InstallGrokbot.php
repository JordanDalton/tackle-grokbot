<?php

namespace Grokbot\Commands;

use Grokbot\Models\Grokbot;
use Illuminate\Console\Command;

class InstallGrokbot extends Command
{
    protected $signature = 'grokbot:install {--force : Run migrations in production without confirmation}';

    protected $description = 'Run Grokbot migrations and optionally register the first bot';

    public function handle(): int
    {
        if ($this->laravel->environment('production') && ! $this->input->isInteractive() && ! $this->option('force')) {
            $this->error('Use --force to install Grokbot in production without interaction.');

            return self::FAILURE;
        }

        $result = $this->call('migrate', [
            '--path' => [realpath(__DIR__.'/../../database/migrations')],
            '--realpath' => true,
            '--force' => (bool) $this->option('force'),
            '--no-interaction' => ! $this->input->isInteractive(),
        ]);

        if ($result !== self::SUCCESS) {
            return $result;
        }

        $this->info('Grokbot installed.');

        if (! Grokbot::exists() && $this->input->isInteractive()
            && $this->confirm('Register your first Grokbot now?', true)) {
            return $this->call('grokbot:register');
        }

        $this->line('Register bots anytime with: php artisan grokbot:register');

        return self::SUCCESS;
    }
}
