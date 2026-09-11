<?php

namespace Grokbot;

use Grokbot\Commands\DeliveryHistory;
use Grokbot\Tools\ListGrokbotDeliveries;
use Grokbot\Tools\ListGrokbots;
use Grokbot\Tools\SendToGrokbot;
use Illuminate\Support\ServiceProvider;

class GrokbotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ListGrokbots::class, SendToGrokbot::class, ListGrokbotDeliveries::class], 'tackle.tools');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        if ($this->app->runningInConsole()) {
            $this->commands([Commands\RegisterGrokbot::class, Commands\EditGrokbot::class, Commands\RemoveGrokbot::class, DeliveryHistory::class]);
        }
    }
}
