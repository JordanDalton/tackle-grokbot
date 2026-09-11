<?php

namespace Grokbot\Tools;

use Grokbot\Models\Delivery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Tackle\Tools\AbstractTool;

class ListGrokbotDeliveries extends AbstractTool
{
    public function description(): string
    {
        return 'Read delivery history and sanitized payloads, including deliveries for removed Grokbots. Supports bot ID and pagination.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['grokbot_id' => $schema->integer(), 'page' => $schema->integer()];
    }

    public function handle(Request $request): string
    {
        $query = Delivery::query();
        if ($request->has('grokbot_id')) {
            $query->where('grokbot_id', $request->integer('grokbot_id'));
        }
        $page = max(1, $request->integer('page', 1));

        return json_encode(['page' => $page, 'deliveries' => $query->latest('id')->forPage($page, 25)->get()], JSON_THROW_ON_ERROR);
    }
}
