<?php

namespace MrRijal\LaravelSms\Tests;

use MrRijal\LaravelSms\SmsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SmsServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Sms' => \MrRijal\LaravelSms\Facades\Sms::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sms.default', 'fake');
        $app['config']->set('sms.queue', false);
    }
}
