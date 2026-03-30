<?php

namespace MrRijal\LaravelSms\Tests;

use MrRijal\LaravelSms\Drivers\FakeDriver;
use MrRijal\LaravelSms\Jobs\SendSmsJob;
use MrRijal\LaravelSms\SmsMessage;

class SendSmsJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeDriver::reset();
    }

    protected function tearDown(): void
    {
        FakeDriver::reset();
        parent::tearDown();
    }

    public function test_handle_sends_message_through_manager(): void
    {
        $job = new SendSmsJob(
            (new SmsMessage)->to('9812345678')->message('From job'),
            'fake'
        );

        $job->handle($this->app->make('laravel-sms'));

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame('From job', FakeDriver::$messages[0]['message']);
        $this->assertSame(['9812345678'], FakeDriver::$messages[0]['to']);
    }

    public function test_job_exposes_message_and_provider(): void
    {
        $message = (new SmsMessage)->to('9811111111')->message('Payload');
        $job = new SendSmsJob($message, 'fake');

        $this->assertSame('fake', $job->provider);
        $this->assertSame(['9811111111'], $job->message->getTo());
    }
}
