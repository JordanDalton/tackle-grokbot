<?php

namespace Grokbot;

use Grokbot\Models\Delivery;
use Grokbot\Models\Grokbot;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class Webhook
{
    public static function validUrl(string $url): bool
    {
        return (bool) preg_match('~^https://api2\.cursor\.sh/automations/webhook/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$~iD', $url);
    }

    public function send(int $id, array $payload): Delivery
    {
        $bot = Grokbot::where('enabled', true)->find($id);
        if (! $bot || ! $bot->bearer_token || ! self::validUrl($bot->webhook_url ?? '')) {
            throw ValidationException::withMessages(['grokbot_id' => 'Grokbot is unavailable or invalid.']);
        }
        $encoded = json_encode((object) $payload, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 65536) {
            throw ValidationException::withMessages(['payload' => 'Payload must not exceed 64 KiB.']);
        }
        $delivery = $bot->deliveries()->create(['payload' => $this->redact($payload, $bot->bearer_token), 'status' => 'sending']);
        $start = microtime(true);
        try {
            $response = Http::withToken($bot->bearer_token)->acceptJson()
                ->connectTimeout(10)->timeout(30)->withoutRedirecting()
                ->withBody($encoded, 'application/json')->post($bot->webhook_url);
            $delivery->fill([
                'status' => $response->successful() ? 'accepted' : 'failed',
                'http_status' => $response->status(),
                'error' => $response->successful() ? null : 'Webhook returned a non-success HTTP status.',
            ]);
        } catch (Throwable) {
            // Exception messages and response bodies can contain credentials.
            $delivery->fill(['status' => 'unknown', 'error' => 'Transport failed; remote acceptance is unknown.']);
        }
        $delivery->duration_ms = (int) round((microtime(true) - $start) * 1000);
        $delivery->save();

        return $delivery;
    }

    private function redact(array $payload, string $token): array
    {
        foreach ($payload as $key => $value) {
            if (preg_match('/authorization|token|password|secret|api[_-]?key/i', (string) $key)) {
                $payload[$key] = '[REDACTED]';
            } elseif (is_object($value)) {
                $payload[$key] = (object) $this->redact((array) $value, $token);
            } elseif (is_array($value)) {
                $payload[$key] = $this->redact($value, $token);
            } elseif (is_string($value)) {
                $payload[$key] = str_replace($token, '[REDACTED]', $value);
            }
        }

        return $payload;
    }
}
