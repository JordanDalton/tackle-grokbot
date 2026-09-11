<?php

namespace Grokbot\Commands;

class RemoveGrokbot extends ManageGrokbot
{
    protected $signature = 'grokbot:remove';

    protected $description = 'Remove a Grokbot and erase credentials while keeping delivery history';

    public function handle(): int
    {
        $bot = $this->selectBot();
        if (! $bot) {
            return self::FAILURE;
        }
        if (! $this->confirm('Remove this Grokbot and erase its credentials?', false)) {
            return self::SUCCESS;
        }
        $bot->delete();
        $this->info('Grokbot removed. Delivery history retained.');

        return self::SUCCESS;
    }
}
