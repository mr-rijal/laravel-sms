<?php

namespace MrRijal\LaravelSms\Tests;

use Illuminate\Notifications\Notifiable;
use MrRijal\LaravelSms\Drivers\FakeDriver;
use MrRijal\LaravelSms\Notifications\SmsNotification;

class SmsNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeDriver::reset();
        $this->app['config']->set('sms.default', 'fake');
        $this->app['config']->set('sms.queue', false);
    }

    protected function tearDown(): void
    {
        FakeDriver::reset();
        parent::tearDown();
    }

    public function test_sends_plain_message_via_fake_driver(): void
    {
        $notifiable = new class
        {
            use Notifiable;

            public function routeNotificationForSms(): string
            {
                return '9812345678';
            }
        };

        $notifiable->notify(new SmsNotification(message: 'Hello from notification'));

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame('Hello from notification', FakeDriver::$messages[0]['message']);
        $this->assertSame(['9812345678'], FakeDriver::$messages[0]['to']);
    }

    public function test_sends_template_via_fake_driver(): void
    {
        $notifiable = new class
        {
            use Notifiable;

            public function routeNotificationForSms(): string
            {
                return '9800000000';
            }
        };

        $notifiable->notify(new SmsNotification(
            templateId: 'TPL_1',
            variables: ['otp' => 111222]
        ));

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame('TPL_1', FakeDriver::$messages[0]['template']);
        $this->assertSame(['otp' => 111222], FakeDriver::$messages[0]['vars']);
    }

    public function test_constructor_requires_message_or_template(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Either message or templateId must be provided');

        new SmsNotification;
    }
}
