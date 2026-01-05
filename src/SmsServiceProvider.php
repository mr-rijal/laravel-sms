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
    }
}
