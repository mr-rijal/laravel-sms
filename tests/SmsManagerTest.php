<?php

namespace MrRijal\LaravelSms\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use MrRijal\LaravelSms\Drivers\FakeDriver;
use MrRijal\LaravelSms\Events\SmsSending;
use MrRijal\LaravelSms\Events\SmsSent;
use MrRijal\LaravelSms\Facades\Sms;
use MrRijal\LaravelSms\Jobs\SendSmsJob;

class SmsManagerTest extends TestCase
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

    public function test_send_now_via_fake_driver_stores_message(): void
    {
        Sms::provider('fake')
            ->to('9812345678')
            ->message('Hello from manager')
            ->sendNow();

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame(['9812345678'], FakeDriver::$messages[0]['to']);
        $this->assertSame('Hello from manager', FakeDriver::$messages[0]['message']);
    }

    public function test_send_uses_default_provider_when_not_specified(): void
    {
        $this->app['config']->set('sms.default', 'fake');

        Sms::to('9812345678')->message('Default provider')->sendNow();

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame('Default provider', FakeDriver::$messages[0]['message']);
    }

    public function test_provider_switch_works(): void
    {
        Sms::provider('fake')->to('9812345678')->message('Via fake')->sendNow();

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame('Via fake', FakeDriver::$messages[0]['message']);
    }

    public function test_multiple_recipients(): void
    {
        Sms::provider('fake')
            ->to(['9812345678', '9800000000'])
            ->message('Bulk')
            ->sendNow();

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame(['9812345678', '9800000000'], FakeDriver::$messages[0]['to']);
    }

    public function test_template_message_via_manager(): void
    {
        Sms::provider('fake')
            ->to('9812345678')
            ->template('TPL_OTP', ['otp' => 654321])
            ->sendNow();

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame('TPL_OTP', FakeDriver::$messages[0]['template']);
        $this->assertSame(['otp' => 654321], FakeDriver::$messages[0]['vars']);
    }

    public function test_send_dispatches_sms_sending_event(): void
    {
        Event::fake([SmsSending::class]);

        Sms::provider('fake')->to('9812345678')->message('Event test')->sendNow();

        Event::assertDispatched(SmsSending::class, function ($event) {
            return $event->provider === 'fake'
                && $event->message->getTo() === ['9812345678']
                && $event->message->getText() === 'Event test';
        });
    }

    public function test_send_dispatches_sms_sent_event_on_success(): void
    {
        Event::fake([SmsSent::class]);

        Sms::provider('fake')->to('9812345678')->message('Success')->sendNow();

        Event::assertDispatched(SmsSent::class, function ($event) {
            return $event->provider === 'fake' && $event->success === true && $event->error === null;
        });
    }

    public function test_send_later_dispatches_job(): void
    {
        Queue::fake();

        Sms::provider('fake')
            ->to('9812345678')
            ->message('Queued')
            ->sendLater();

        Queue::assertPushed(SendSmsJob::class, function ($job) {
            return $job->provider === 'fake'
                && $job->message->getTo() === ['9812345678']
                && $job->message->getText() === 'Queued';
        });
    }

    public function test_send_later_at_dispatches_delayed_job(): void
    {
        Queue::fake();
        $datetime = new \DateTimeImmutable('+10 minutes');

        Sms::provider('fake')
            ->to('9812345678')
            ->message('Scheduled')
            ->sendLaterAt($datetime);

        Queue::assertPushed(SendSmsJob::class, function ($job) use ($datetime) {
            return $job->provider === 'fake' && $job->message->getText() === 'Scheduled';
        });

        $this->assertEquals($datetime->getTimestamp(), Queue::pushed(SendSmsJob::class)[0]->delay->getTimestamp());
    }

    public function test_send_throws_when_no_recipient(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one recipient is required');

        Sms::provider('fake')->message('No to')->sendNow();
    }

    public function test_send_throws_when_no_message_or_template(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message text or template ID is required');

        Sms::provider('fake')->to('9812345678')->sendNow();
    }

    public function test_unknown_provider_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SMS driver [unknown_driver] not configured');

        Sms::provider('unknown_driver')->to('9812345678')->message('Hi')->sendNow();
    }
}
