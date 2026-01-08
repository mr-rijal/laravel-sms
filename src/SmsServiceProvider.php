<?php

namespace MrRijal\LaravelSms;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use MrRijal\LaravelSms\Notifications\SmsChannel;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sms.php', 'sms');

        $this->app->singleton('laravel-sms', fn () => new SmsManager);

        // Register SMS notification channel
        $this->app->make(ChannelManager::class)->extend('sms', function ($app) {
            return new SmsChannel($app['laravel-sms']);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/sms.php' => config_path('sms.php'),
        ], 'sms-config');

        // Register webhook routes if enabled
        if (config('sms.webhooks.enabled', false)) {
            $this->loadWebhookRoutes();
        }
    }

    /**
     * Load webhook routes
     */
    protected function loadWebhookRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');
    }
}
