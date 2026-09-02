<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use Illuminate\Support\Facades\Log;
use MrRijal\LaravelSms\Drivers\LogDriver;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;

class LogDriverTest extends TestCase
{
    public function test_logs_the_message_without_sending_it(): void
    {
        Log::spy();
        $driver = new LogDriver;

        $this->assertTrue($driver->send((new SmsMessage)->to('+15551234567')->message('Debug message')));

        Log::shouldHaveReceived('debug')->once()->with('SMS captured by log driver', [
            'provider' => 'log',
            'recipients' => ['***4567'],
            'message' => 'Debug message',
            'template_id' => null,
            'variables' => [],
        ]);
    }
}
