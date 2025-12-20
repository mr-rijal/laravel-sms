<?php

namespace MrRijal\LaravelSms\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use MrRijal\LaravelSms\SmsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [SmsServiceProvider::class];
    }
}
