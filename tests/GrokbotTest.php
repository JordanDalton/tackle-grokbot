<?php

use Grokbot\GrokbotServiceProvider;
use Grokbot\Models\Delivery;
use Grokbot\Models\Grokbot;
use Grokbot\Tools\ListGrokbotDeliveries;
use Grokbot\Tools\ListGrokbots;
use Grokbot\Tools\SendToGrokbot;
use Grokbot\Webhook;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Orchestra\Testbench\TestCase;
use Tackle\Agents\DefaultCodingAgent;
use Tackle\TackleServiceProvider;

class GrokbotTest extends TestCase
{
    private const URL = 'https://api2.cursor.sh/automations/webhook/11111111-2222-3333-4444-555555555555';

    protected function getPackageProviders($app): array
    {
        return [TackleServiceProvider::class, GrokbotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate')->run();
        Http::preventStrayRequests();
    }

    private function bot(array $attributes = []): Grokbot
    {
        return Grokbot::create(array_merge(['name' => 'review', 'instructions' => 'Send a PR number.', 'webhook_url' => self::URL, 'bearer_token' => 'test-private-token', 'enabled' => true], $attributes));
    }

    public function test_registration_creates_multiple_bots_with_encrypted_hidden_credentials(): void
    {
        foreach (['review', 'build'] as $name) {
            $this->artisan('grokbot:register')
                ->expectsQuestion('Name', $name)
                ->expectsQuestion('Instructions (purpose and expected payload)', 'Send context')
                ->expectsQuestion('Webhook URL', self::URL)
                ->expectsQuestion('Bearer token', 'test-private-token')
                ->expectsConfirmation('Enabled?', 'yes')
                ->assertSuccessful();
        }
        $this->assertSame(2, Grokbot::count());
        $bot = Grokbot::first();
        $this->assertSame('test-private-token', $bot->bearer_token);
        $this->assertStringNotContainsString('test-private-token', DB::table('grokbots')->value('bearer_token'));
        $this->assertArrayNotHasKey('bearer_token', $bot->toArray());
        $this->assertArrayNotHasKey('webhook_url', $bot->toArray());
    }

    public function test_edit_can_keep_or_rotate_credentials_and_disable_bot(): void
    {
        $bot = $this->bot();
        foreach (['', 'replacement-token'] as $token) {
            $this->artisan('grokbot:edit')->expectsChoice('Grokbot', 'review', ['review'])
                ->expectsQuestion('Name', 'review')->expectsQuestion('Instructions (purpose and expected payload)', 'Updated instructions')
                ->expectsQuestion('Webhook URL', self::URL)->expectsQuestion('Bearer token (leave blank to keep)', $token)
                ->expectsConfirmation('Enabled?', 'no')->assertSuccessful();
            $bot->refresh();
            $this->assertSame($token ?: 'test-private-token', $bot->bearer_token);
            $this->assertFalse($bot->enabled);
        }
    }

    public function test_sending_logs_before_http_and_never_exposes_response_credentials(): void
    {
        $bot = $this->bot();
        Http::fake(function ($request) {
            $this->assertSame('sending', Delivery::first()->status);
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer test-private-token'));
            $this->assertSame(self::URL, $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertSame(['task' => 'Review PR', 'nested' => ['token' => 'sensitive']], $request->data());

            return Http::response('test-private-token', 202);
        });
        $output = app(SendToGrokbot::class)->handle(new Request(['grokbot_id' => $bot->id, 'payload' => '{"task":"Review PR","nested":{"token":"sensitive"}}']));
        $this->assertStringContainsString('accepted', $output);
        $this->assertStringNotContainsString('test-private-token', $output);
        $this->assertSame('[REDACTED]', Delivery::first()->payload['nested']['token']);
        $this->assertSame(202, Delivery::first()->http_status);
        Http::assertSentCount(1);
    }

    public function test_failures_and_timeouts_are_logged_without_retry_or_secret_leak(): void
    {
        $bot = $this->bot();
        Http::fake([self::URL => Http::response('Bearer test-private-token', 401)]);
        $delivery = app(Webhook::class)->send($bot->id, ['task' => 'hello']);
        $this->assertSame('failed', $delivery->status);
        $this->assertStringNotContainsString('test-private-token', $delivery->toJson());
        Http::assertSentCount(1);
        Http::fake(fn () => throw new ConnectionException('test-private-token'));
        $delivery = app(Webhook::class)->send($bot->id, []);
        $this->assertSame('unknown', $delivery->status);
        $this->assertStringNotContainsString('test-private-token', $delivery->toJson());
    }

    public function test_removal_clears_credentials_preserves_history_and_blocks_send(): void
    {
        $bot = $this->bot();
        $bot->deliveries()->create(['payload' => ['task' => 'review'], 'status' => 'accepted']);
        $this->artisan('grokbot:remove')->expectsChoice('Grokbot', 'review', ['review'])
            ->expectsConfirmation('Remove this Grokbot and erase its credentials?', 'yes')->assertSuccessful();
        $this->assertSame(0, Grokbot::count());
        $deleted = Grokbot::withTrashed()->first();
        $this->assertNull($deleted->bearer_token);
        $this->assertNull($deleted->webhook_url);
        $this->assertFalse($deleted->enabled);
        $this->assertSame(1, Delivery::count());
        $this->assertSame('review', Delivery::first()->grokbot->name);
        $this->assertStringContainsString('unavailable', app(SendToGrokbot::class)->handle(new Request(['grokbot_id' => $bot->id, 'payload' => '{}'])));
        $this->assertStringContainsString('review', app(ListGrokbotDeliveries::class)->handle(new Request(['grokbot_id' => $bot->id])));
        Http::assertNothingSent();
    }

    public function test_cancelled_removal_keeps_bot(): void
    {
        $this->bot();
        $this->artisan('grokbot:remove')->expectsChoice('Grokbot', 'review', ['review'])
            ->expectsConfirmation('Remove this Grokbot and erase its credentials?', 'no')->assertSuccessful();
        $this->assertSame(1, Grokbot::count());
    }

    public function test_discovery_is_paginated_and_excludes_disabled_bots_and_credentials(): void
    {
        for ($i = 0; $i < 27; $i++) {
            $this->bot(['name' => 'bot-'.$i]);
        }
        $this->bot(['name' => 'disabled', 'enabled' => false]);
        $tool = app(ListGrokbots::class);
        $first = $tool->handle(new Request);
        $this->assertCount(25, json_decode($first, true)['bots']);
        $this->assertCount(2, json_decode($tool->handle(new Request(['page' => 2])), true)['bots']);
        $this->assertStringNotContainsString('test-private-token', $first);
        $this->assertStringNotContainsString('webhook_url', $first);
    }

    public function test_invalid_payload_and_disabled_bot_do_not_send(): void
    {
        $bot = $this->bot(['enabled' => false]);
        $tool = app(SendToGrokbot::class);
        foreach (['[]', 'null', '{bad', '{}'] as $payload) {
            $tool->handle(new Request(['grokbot_id' => $bot->id, 'payload' => $payload]));
        }
        $bot->update(['enabled' => true]);
        $output = $tool->handle(new Request(['grokbot_id' => $bot->id, 'payload' => json_encode(['text' => str_repeat('a', 65537)])]));
        $this->assertStringContainsString('64 KiB', $output);
        $this->assertSame(0, Delivery::count());
        Http::assertNothingSent();
    }

    public function test_tools_are_registered_and_respect_tackle_allowlist(): void
    {
        $this->assertCount(3, iterator_to_array(app()->tagged('tackle.tools')));
        config()->set('tackle.tools', ['ListGrokbots']);
        $tools = app(DefaultCodingAgent::class)->tools();
        $this->assertCount(1, $tools);
    }

    public function test_url_validation_rejects_other_hosts_and_redirect_parameters(): void
    {
        $this->assertTrue(Webhook::validUrl(self::URL));
        foreach (['http://localhost/test', self::URL.'?redirect=evil', 'https://api2.cursor.sh.evil.test/automations/webhook/11111111-2222-3333-4444-555555555555'] as $url) {
            $this->assertFalse(Webhook::validUrl($url));
        }
    }
}
