<?php

namespace Grokbot\Tools;

use Grokbot\Models\Grokbot;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Tackle\Tools\AbstractTool;

class ListGrokbots extends AbstractTool
{
    public function description(): string
    {
        return 'List enabled Grokbots and their instructions. Use page to discover more bots. Credentials are never returned.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['page' => $schema->integer()->description('Page number, starting at 1')];
    }

    public function handle(Request $request): string
    {
        $page = max(1, $request->integer('page', 1));
        $bots = Grokbot::where('enabled', true)->orderBy('id')->forPage($page, 25)->get(['id', 'name', 'instructions', 'enabled']);

        return json_encode(['page' => $page, 'bots' => $bots], JSON_THROW_ON_ERROR);
    }
}
