<?php

use Grokbot\GrokbotServiceProvider;
use Grokbot\Models\Grokbot;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

class InstallTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [GrokbotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    public function test_install_migrates_only_package_and_allows_skipping_registration(): void
    {
        $this->artisan('grokbot:install')
            ->expectsConfirmation('Register your first Grokbot now?', 'no')->assertSuccessful();

        $this->assertTrue(Schema::hasTable('grokbots'));
        $this->assertTrue(Schema::hasTable('grokbot_deliveries'));
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertSame(0, Grokbot::count());
    }

    public function test_install_can_register_first_bot_and_rerun_without_changing_it(): void
    {
        $this->artisan('grokbot:install')
            ->expectsConfirmation('Register your first Grokbot now?', 'yes')
            ->expectsQuestion('Name', 'review')
            ->expectsQuestion('Instructions (purpose and expected payload)', 'Review PRs')
            ->expectsQuestion('Webhook URL', 'https://api2.cursor.sh/automations/webhook/11111111-2222-3333-4444-555555555555')
            ->expectsQuestion('Bearer token', 'test-token')
            ->expectsConfirmation('Enabled?', 'yes')->assertSuccessful();

        $this->artisan('grokbot:install')->assertSuccessful();
        $this->assertSame(1, Grokbot::count());
        $this->assertSame('test-token', Grokbot::first()->bearer_token);
    }

    public function test_unattended_install_skips_registration(): void
    {
        $this->artisan('grokbot:install', ['--no-interaction' => true])->assertSuccessful();
        $this->assertSame(0, Grokbot::count());
    }

    public function test_production_requires_explicit_force_when_unattended(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('grokbot:install', ['--no-interaction' => true])->assertFailed();
        $this->assertFalse(Schema::hasTable('grokbots'));
        $this->artisan('grokbot:install', ['--no-interaction' => true, '--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('grokbots'));
    }
}
