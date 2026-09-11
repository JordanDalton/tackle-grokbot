<?php

namespace Grokbot\Tools;

use Grokbot\Webhook;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use JsonException;
use Laravel\Ai\Tools\Request;
use Tackle\Tools\AbstractTool;

class SendToGrokbot extends AbstractTool
{
    public function description(): string
    {
        return 'Send a JSON object to an enabled Grokbot by ID. Read its instructions first. Returns delivery status; accepted does not mean the remote work is complete. Do not automatically resend unknown deliveries.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['grokbot_id' => $schema->integer()->required(), 'payload' => $schema->string()->description('JSON object matching the bot instructions, up to 64 KiB')->required()];
    }

    public function handle(Request $request): string
    {
        try {
            $json = $this->arg($request, 'payload');
            $object = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            if (! $object instanceof \stdClass) {
                return 'Error: payload must be a JSON object.';
            }
            // Keep nested objects intact when sending; conversion is only at the root.
            $delivery = app(Webhook::class)->send($request->integer('grokbot_id'), (array) $object);

            return json_encode($delivery->only(['id', 'grokbot_id', 'status', 'http_status', 'error']), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'Error: payload must be valid JSON.';
        } catch (ValidationException $e) {
            return json_encode(['errors' => $e->errors()], JSON_THROW_ON_ERROR);
        }
    }
}
