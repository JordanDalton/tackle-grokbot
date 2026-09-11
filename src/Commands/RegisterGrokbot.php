<?php

namespace Grokbot\Commands;

use Grokbot\Models\Grokbot;

class RegisterGrokbot extends ManageGrokbot
{
    protected $signature = 'grokbot:register';

    protected $description = 'Interactively register a named Grokbot with hidden credentials';

    public function handle(): int
    {
        return $this->saveBot(new Grokbot);
    }
}
