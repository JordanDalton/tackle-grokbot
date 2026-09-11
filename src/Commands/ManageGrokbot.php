<?php

namespace Grokbot\Commands;

use Grokbot\Models\Grokbot;
use Grokbot\Webhook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

abstract class ManageGrokbot extends Command
{
    protected function selectBot(): ?Grokbot
    {
        $bots = Grokbot::orderBy('name')->get();
        if ($bots->isEmpty()) {
            $this->warn('No grokbots registered.');

            return null;
        }
        $name = $this->choice('Grokbot', $bots->pluck('name')->all());

        return $bots->firstWhere('name', $name);
    }

    protected function saveBot(Grokbot $bot): int
    {
        $name = trim((string) $this->ask('Name', $bot->name));
        $instructions = $this->ask('Instructions (purpose and expected payload)', $bot->instructions);
        $url = trim((string) $this->ask('Webhook URL', $bot->webhook_url));
        $token = $this->secret($bot->exists ? 'Bearer token (leave blank to keep)' : 'Bearer token', false);
        $token = $token === null || $token === '' ? $bot->bearer_token : trim($token);
        $enabled = $this->confirm('Enabled?', $bot->exists ? $bot->enabled : true);
        $validator = Validator::make(compact('name', 'instructions', 'url', 'token'), [
            'name' => ['required', 'string', 'max:255', Rule::unique('grokbots', 'name')->ignore($bot->id)],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'url' => ['required', fn ($attribute, $value, $fail) => Webhook::validUrl($value) ?: $fail('Use a Cursor automation HTTPS webhook URL.')],
            'token' => ['required', 'string', 'max:4096', 'regex:/^[^\s]+$/'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $bot->fill(['name' => $name, 'instructions' => $instructions, 'webhook_url' => $url, 'bearer_token' => $token, 'enabled' => $enabled])->save();
        $this->info('Grokbot saved.');

        return self::SUCCESS;
    }
}
