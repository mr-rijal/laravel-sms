<?php

namespace MrRijal\LaravelSms\Tests;

use MrRijal\LaravelSms\SmsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Register package service providers for the test application.
     *
     * @param \Illuminate\Foundation\Application $app The application instance used by the testbench.
     * @return array List of service provider class names to register.
     */
    protected function getPackageProviders($app): array
    {
        return [SmsServiceProvider::class];
    }

    /**
     * Register package facade aliases for the test application.
     *
     * @return array Associative array mapping facade alias names to their fully-qualified facade class names.
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Sms' => \MrRijal\LaravelSms\Facades\Sms::class,
        ];
    }

    /**
     * Configure the test application environment for package tests.
     *
     * Sets default SMS driver to "fake" and disables SMS queuing.
     *
     * @param \Illuminate\Contracts\Foundation\Application|\Illuminate\Foundation\Application $app The application container for the test environment.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('sms.default', 'fake');
        $app['config']->set('sms.queue', false);
    }
}