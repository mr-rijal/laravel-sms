<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use MrRijal\LaravelSms\Notifications\SmsChannel;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sms.php', 'sms');

        $this->app->singleton('laravel-sms', fn (): SmsManager => new SmsManager);

        $this->app->make(ChannelManager::class)->extend('sms', function (): SmsChannel {
            return new SmsChannel;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/sms.php' => config_path('sms.php'),
        ], 'sms-config');

        if (config('sms.webhooks.enabled', false) === true) {
            $this->loadWebhookRoutes();
        }
    }

    protected function loadWebhookRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');
    }
}
