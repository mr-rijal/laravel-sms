<?php

namespace MrRijal\LaravelSms\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use MrRijal\LaravelSms\Contracts\Factory;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Drivers\FakeDriver;
use MrRijal\LaravelSms\Events\SmsFailed;
use MrRijal\LaravelSms\Events\SmsSending;
use MrRijal\LaravelSms\Events\SmsSent;
use MrRijal\LaravelSms\Exceptions\DeliveryException;
use MrRijal\LaravelSms\Exceptions\InvalidConfigurationException;
use MrRijal\LaravelSms\Facades\Sms;
use MrRijal\LaravelSms\Jobs\SendSmsJob;
use MrRijal\LaravelSms\PendingSms;
use MrRijal\LaravelSms\SmsManager;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

class SmsManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeDriver::reset();
    }

    public function test_send_now_returns_a_result_with_provider_and_ids(): void
    {
        $result = Sms::to('9812345678')->message('Hello')->sendNow();

        $this->assertInstanceOf(SmsResult::class, $result);
        $this->assertSame('fake', $result->provider);
        $this->assertSame(['9812345678'], $result->recipients());
        $this->assertSame('fake-1', $result->firstId());
        $this->assertSame('Hello', FakeDriver::$messages[0]['message']);
    }

    public function test_each_facade_call_starts_a_fresh_message(): void
    {
        $first = Sms::to('9812345678');
        $second = Sms::to('9800000000');

        $this->assertInstanceOf(PendingSms::class, $first);
        $this->assertSame(['9812345678'], $first->getMessage()->getTo());
        $this->assertSame(['9800000000'], $second->getMessage()->getTo());
    }

    public function test_builder_supports_provider_template_from_and_conditionals(): void
    {
        Sms::provider('fake')
            ->to('9812345678')
            ->template('OTP', ['code' => 1])
            ->from('ACME')
            ->when(true, fn (PendingSms $sms) => $sms->to('9800000000'))
            ->sendNow();

        $this->assertSame(['9812345678', '9800000000'], FakeDriver::$messages[0]['to']);
        $this->assertSame('OTP', FakeDriver::$messages[0]['template']);
        $this->assertSame('ACME', FakeDriver::$messages[0]['from']);
    }

    public function test_events_are_dispatched_with_the_result(): void
    {
        Event::fake([SmsSending::class, SmsSent::class]);

        Sms::to('9812345678')->message('Hi')->sendNow();

        Event::assertDispatched(SmsSending::class, fn (SmsSending $e): bool => $e->provider === 'fake');
        Event::assertDispatched(SmsSent::class, fn (SmsSent $e): bool => $e->result->firstId() === 'fake-1');
    }

    public function test_a_sending_listener_can_cancel_the_send(): void
    {
        Event::listen(SmsSending::class, fn (): bool => false);

        $this->assertNull(Sms::to('9812345678')->message('Hi')->sendNow());
        $this->assertSame([], FakeDriver::$messages);
    }

    public function test_failures_dispatch_sms_failed_and_rethrow(): void
    {
        Event::fake([SmsFailed::class]);
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Bad number'], 400)]);
        config(['sms.providers.twilio' => ['sid' => 'AC1', 'token' => 't', 'from' => '+15550000000']]);

        try {
            Sms::provider('twilio')->to('+15551230001')->message('Hi')->sendNow();
            $this->fail('Expected DeliveryException.');
        } catch (DeliveryException $e) {
            Event::assertDispatched(SmsFailed::class, fn (SmsFailed $event): bool => $event->exception === $e && $event->provider === 'twilio');
        }
    }

    public function test_send_queues_when_queue_is_enabled(): void
    {
        Queue::fake();
        config(['sms.queue' => 'true']);

        $this->assertNull(Sms::to('9812345678')->message('Queued')->send());

        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_queueing_creates_one_encrypted_job_per_recipient_on_the_configured_queue(): void
    {
        Queue::fake();
        config(['sms.queue_connection' => 'redis', 'sms.queue_name' => 'sms']);

        Sms::provider('fake')->to(['9812345678', '9800000000'])->message('Hi')->sendLater();

        Queue::assertPushedOn('sms', SendSmsJob::class, fn (SendSmsJob $job): bool => $job->connection === 'redis');
        $this->assertSame(
            [['9812345678'], ['9800000000']],
            Queue::pushed(SendSmsJob::class)->map(fn (SendSmsJob $job): array => $job->message->getTo())->all(),
        );
        Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->message->getProvider() === 'fake');
    }

    public function test_send_later_at_delays_every_job(): void
    {
        Queue::fake();
        $at = now()->addMinutes(10);

        Sms::to(['9812345678', '9800000000'])->message('Hi')->sendLaterAt($at);

        Queue::assertPushed(SendSmsJob::class, 2);
        Queue::pushed(SendSmsJob::class)->each(fn (SendSmsJob $job) => $this->assertEquals($at, $job->delay));
    }

    public function test_random_provider_picks_from_the_pool(): void
    {
        config(['sms.random_drivers' => ['fake']]);

        $this->assertSame('fake', Sms::provider('random')->to('9812345678')->message('Hi')->sendNow()?->provider);

        config(['sms.random_drivers' => []]);
        $this->expectException(InvalidConfigurationException::class);
        Sms::provider('random')->to('9812345678')->message('Hi')->sendNow();
    }

    public function test_unknown_or_invalid_drivers_are_rejected(): void
    {
        try {
            Sms::driver('nope');
            $this->fail('Expected exception.');
        } catch (InvalidConfigurationException $e) {
            $this->assertSame('SMS driver [nope] not configured.', $e->getMessage());
        }

        config(['sms.drivers.bad' => \stdClass::class]);
        $this->expectExceptionMessage('must implement');
        Sms::driver('bad');
    }

    public function test_custom_drivers_can_be_registered_with_extend(): void
    {
        $driver = new class implements SmsProvider
        {
            public function send(SmsMessage $message): SmsResult
            {
                return SmsResult::accepted($message->getTo(), 'custom-1');
            }
        };

        Sms::extend('custom', fn () => $driver);

        $this->assertSame('custom-1', Sms::provider('custom')->to('9812345678')->message('Hi')->sendNow()?->firstId());
    }

    public function test_drivers_are_cached_per_manager_and_can_be_forgotten(): void
    {
        $manager = $this->app->make(SmsManager::class);

        $this->assertSame($manager->driver('fake'), $manager->driver('fake'));
        $first = $manager->driver('fake');
        $this->assertNotSame($first, $manager->forgetDrivers()->driver('fake'));
    }

    public function test_custom_drivers_survive_queue_and_octane_scope_resets(): void
    {
        Sms::extend('acme', fn () => new FakeDriver);

        $this->assertSame($this->app->make(SmsManager::class), $this->app->make(Factory::class));

        $this->app->forgetScopedInstances();

        $this->assertInstanceOf(FakeDriver::class, Sms::driver('acme'));
    }

    public function test_macros_receive_the_manager(): void
    {
        Sms::macro('sendOtp', fn (string $number, int $otp): ?SmsResult => $this->to($number)->template('OTP', ['otp' => $otp])->sendNow());

        Sms::sendOtp('9812345678', 1234);

        $this->assertSame(['otp' => 1234], FakeDriver::$messages[0]['vars']);
    }
}
