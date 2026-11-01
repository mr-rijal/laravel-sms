<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use MrRijal\LaravelSms\Console\SendTestSmsCommand;
use MrRijal\LaravelSms\Contracts\Factory;
use MrRijal\LaravelSms\Notifications\SmsChannel;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sms.php', 'sms');

        // Scoped, so Octane requests and queue jobs each get a fresh manager and driver cache.
        $this->app->scoped('laravel-sms', fn (Application $app): SmsManager => new SmsManager($app));
        $this->app->alias('laravel-sms', SmsManager::class);
        $this->app->alias('laravel-sms', Factory::class);

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

            $this->commands([SendTestSmsCommand::class]);

            if (class_exists(AboutCommand::class)) {
                AboutCommand::add('Laravel SMS', fn (): array => [
                    'Default provider' => $this->app->make(SmsManager::class)->getDefaultDriver(),
                    'Queue' => filter_var(config('sms.queue'), FILTER_VALIDATE_BOOLEAN) ? 'enabled' : 'disabled',
                    'Webhooks' => filter_var(config('sms.webhooks.enabled'), FILTER_VALIDATE_BOOLEAN) ? 'enabled' : 'disabled',
                ]);
            }
        }

        if (filter_var(config('sms.webhooks.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');
        }
    }
}
