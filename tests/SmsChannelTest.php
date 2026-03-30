<?php

namespace MrRijal\LaravelSms\Tests;

use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use MrRijal\LaravelSms\Drivers\FakeDriver;
use MrRijal\LaravelSms\Notifications\SmsChannel;
use MrRijal\LaravelSms\Notifications\SmsNotification;

class SmsChannelTest extends TestCase
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

    public function test_send_invokes_to_sms_on_notification(): void
    {
        $channel = new SmsChannel;
        $notifiable = new class
        {
            use Notifiable;

            public function routeNotificationForSms(): string
            {
                return '9812345678';
            }
        };

        $channel->send($notifiable, new SmsNotification(message: 'Channel path'));

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame('Channel path', FakeDriver::$messages[0]['message']);
    }

    public function test_send_throws_when_notification_has_no_to_sms(): void
    {
        $instance = new class
        {
            use Notifiable;

            public function routeNotificationForSms(): string
            {
                return '9812345678';
            }
        };

        $channel = new SmsChannel;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing toSms');

        $channel->send($instance, new class extends Notification {});
    }
}
