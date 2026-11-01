<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use MrRijal\LaravelSms\Notifications\SmsChannel;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sms.php', 'sms');

        // Scoped: the fluent builder holds per-message state, so Octane requests
        // and queue jobs each get a fresh manager instead of a shared singleton.
        $this->app->scoped(SmsManager::class, fn (): SmsManager => new SmsManager);
        $this->app->alias(SmsManager::class, 'laravel-sms');

        $this->callAfterResolving(ChannelManager::class, function (ChannelManager $channels): void {
            $channels->extend('sms', fn (Application $app): SmsChannel => $app->make(SmsChannel::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sms.php' => config_path('sms.php'),
            ], 'sms-config');
        }

        if (filter_var(config('sms.webhooks.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->loadWebhookRoutes();
        }
    }

    protected function loadWebhookRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');
    }
}
