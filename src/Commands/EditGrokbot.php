<?php

namespace Grokbot\Commands;

class EditGrokbot extends ManageGrokbot
{
    protected $signature = 'grokbot:edit';

    protected $description = 'Edit a Grokbot or rotate its credentials';

    public function handle(): int
    {
        $bot = $this->selectBot();

        return $bot ? $this->saveBot($bot) : self::FAILURE;
    }
}
