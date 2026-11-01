<?php

namespace MrRijal\LaravelSms\Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use MrRijal\LaravelSms\Drivers\FakeDriver;
use MrRijal\LaravelSms\Events\SmsSent;
use MrRijal\LaravelSms\Exceptions\DeliveryException;
use MrRijal\LaravelSms\Facades\Sms;
use MrRijal\LaravelSms\Jobs\SendSmsJob;
use MrRijal\LaravelSms\Notifications\SmsChannel;
use MrRijal\LaravelSms\Notifications\SmsNotification;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Traits\HasSms;
use Orchestra\Testbench\Exceptions\Handler;
use PHPUnit\Framework\AssertionFailedError;

class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeDriver::reset();
    }

    public function test_sms_fake_records_sent_and_queued_messages(): void
    {
        $fake = Sms::fake();

        Sms::to('+1 555 123 0001')->message('Your code is 1234')->sendNow();
        Sms::to(['9812345678', '9800000000'])->message('Later')->sendLater();

        Sms::assertSent(fn (SmsMessage $m): bool => $m->getText() === 'Your code is 1234');
        Sms::assertSentTo('+15551230001');
        Sms::assertSentCount(1);
        Sms::assertQueued(2);
        Sms::assertQueued(fn (SmsMessage $m): bool => $m->getTo() === ['9800000000']);
        Sms::assertNotSent(fn (SmsMessage $m): bool => $m->getText() === 'Nope');
        $this->assertCount(1, $fake->sent());
        $this->assertSame([], FakeDriver::$messages);
    }

    public function test_sms_fake_behaves_like_a_real_send(): void
    {
        Event::fake([SmsSent::class]);
        config(['sms.random_drivers' => ['fake']]);
        Sms::fake();

        $this->assertSame('fake', Sms::provider('random')->to('9812345678')->message('Hi')->sendNow()?->provider);
        Event::assertDispatched(SmsSent::class);

        $this->expectExceptionMessage('SMS driver [typo] not configured.');
        Sms::provider('typo')->to('9812345678')->message('Hi')->sendNow();
    }

    public function test_sms_fake_is_used_by_the_notification_channel_and_jobs(): void
    {
        Sms::fake();
        $this->app->forgetScopedInstances();

        (new SmsUser('9812345678'))->notify(new SmsNotification(message: 'Via channel'));
        dispatch_sync(new SendSmsJob(SmsMessage::create('Via job')->to('9812345678')));

        Sms::assertSentCount(2);
        $this->assertSame([], FakeDriver::$messages);
    }

    public function test_sms_fake_assertions_fail_when_nothing_matches(): void
    {
        Sms::fake();
        Sms::assertNothingSent();
        Sms::assertNothingQueued();

        $this->expectException(AssertionFailedError::class);
        Sms::assertSentTo('9812345678');
    }

    public function test_failover_moves_only_failed_recipients_to_the_next_provider(): void
    {
        Http::fake([
            'api.twilio.com/*' => fn (Request $r) => $r['To'] === '+15551230002'
                ? Http::response(['message' => 'Unreachable'], 400)
                : Http::response(['sid' => 'SM1'], 201),
            'rest.nexmo.com/*' => Http::response(['messages' => [['status' => '0', 'message-id' => 'V1']]]),
        ]);
        config([
            'sms.providers.twilio' => ['sid' => 'AC1', 'token' => 't', 'from' => '+15550000000'],
            'sms.providers.vonage' => ['key' => 'k', 'secret' => 's', 'from' => 'Acme'],
            'sms.providers.failover' => ['drivers' => ['twilio', 'vonage']],
        ]);

        $result = Sms::provider('failover')->to(['+15551230001', '+15551230002'])->message('Hi')->sendNow();

        $this->assertSame(['+15551230001', '+15551230002'], $result?->recipients());
        $this->assertSame(['SM1', 'V1'], $result?->ids());
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'nexmo') && $r['to'] === '15551230002');
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'nexmo') && $r['to'] === '15551230001');
    }

    public function test_failover_throws_when_every_provider_fails(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Down'], 500)]);
        config([
            'sms.providers.twilio' => ['sid' => 'AC1', 'token' => 't', 'from' => '+15550000000'],
            'sms.providers.failover' => ['drivers' => ['twilio', 'not-configured']],
        ]);

        $this->expectException(DeliveryException::class);
        $this->expectExceptionMessage('All failover providers failed');

        Sms::provider('failover')->to('+15551230001')->message('Hi')->sendNow();
    }

    public function test_notification_channel_sends_sms_notification(): void
    {
        $user = new SmsUser('9812345678');

        $user->notify(new SmsNotification(message: 'Shipped'));
        $user->notify(new SmsNotification(templateId: 'OTP', variables: ['otp' => 1]));

        $this->assertSame(['9812345678'], FakeDriver::$messages[0]['to']);
        $this->assertSame('Shipped', FakeDriver::$messages[0]['message']);
        $this->assertSame('OTP', FakeDriver::$messages[1]['template']);
    }

    public function test_notification_can_return_a_string_and_route_on_demand(): void
    {
        Notification::route('sms', '9800000000')->notify(new StringSmsNotification);

        $this->assertSame(['9800000000'], FakeDriver::$messages[0]['to']);
        $this->assertSame('Plain text', FakeDriver::$messages[0]['message']);
    }

    public function test_notification_without_a_route_is_skipped(): void
    {
        $result = $this->app->make(SmsChannel::class)->send(new AnonymousNotifiable, new StringSmsNotification);

        $this->assertNull($result);
        $this->assertSame([], FakeDriver::$messages);
    }

    public function test_notification_without_to_sms_is_rejected(): void
    {
        $this->expectExceptionMessage('missing toSms');

        $this->app->make(SmsChannel::class)->send(new SmsUser('9812345678'), new class extends BaseNotification {});
    }

    public function test_has_sms_trait_helpers(): void
    {
        $user = new SmsUser('9812345678');

        $this->assertSame('fake-1', $user->sms('Welcome')?->firstId());
        $user->smsTemplate('OTP', ['otp' => 5]);

        $this->assertSame(['otp' => 5], FakeDriver::$messages[1]['vars']);
    }

    public function test_queued_job_is_encrypted_and_sends_through_the_manager(): void
    {
        config(['sms.retry.tries' => 5, 'sms.retry.backoff' => [1, 2]]);
        $job = new SendSmsJob(SmsMessage::create('Queued')->to('9812345678'), 'fake');

        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        $this->assertSame(5, $job->tries);
        $this->assertSame([1, 2], $job->backoff);

        dispatch_sync($job);

        $this->assertSame('Queued', FakeDriver::$messages[0]['message']);
    }

    public function test_test_command_sends_and_reports_the_message_id(): void
    {
        $this->artisan('sms:test', ['to' => '9812345678', '--provider' => 'fake'])
            ->expectsOutputToContain('Sent via [fake] (message ID fake-1)')
            ->assertSuccessful();

        $this->artisan('sms:test', ['to' => 'not-a-number'])
            ->expectsOutputToContain('Invalid phone number format')
            ->assertFailed();
    }

    public function test_failover_skips_providers_that_cannot_send_the_message_type(): void
    {
        config(['sms.providers.twilio' => ['sid' => 'AC1', 'token' => 't', 'from' => '+15550000000']]);
        config(['sms.providers.failover' => ['drivers' => [' twilio', 'fake ']]]);

        $result = Sms::provider('failover')->to('9812345678')->template('OTP', ['otp' => 1])->sendNow();

        $this->assertSame(['9812345678'], $result?->recipients());
        $this->assertSame('OTP', FakeDriver::$messages[0]['template']);
    }

    public function test_failing_sms_sent_listener_does_not_fail_an_accepted_send(): void
    {
        Event::listen(SmsSent::class, fn () => throw new \RuntimeException('listener broke'));
        $this->app->instance(ExceptionHandler::class, $handler = new class extends Handler
        {
            public array $reported = [];

            public function __construct() {}

            public function report(\Throwable $e): void
            {
                $this->reported[] = $e->getMessage();
            }
        });

        dispatch_sync(new SendSmsJob(SmsMessage::create('Once')->to('9812345678')));

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame(['listener broke'], $handler->reported);
    }

    public function test_sms_fake_keeps_custom_drivers_and_snapshots_messages(): void
    {
        Sms::extend('acme', fn () => new FakeDriver);
        Sms::fake();

        $message = SmsMessage::create('Original')->to('9812345678')->provider('acme');
        Sms::sendNow($message);
        $message->message('Changed later');

        Sms::assertSent(fn (SmsMessage $m): bool => $m->getText() === 'Original');
    }

    public function test_channel_does_not_mutate_a_reused_message(): void
    {
        $notification = new ReusedMessageNotification;

        (new SmsUser('9812345678'))->notify($notification);
        (new SmsUser('9800000000'))->notify($notification);

        $this->assertSame(['9812345678'], FakeDriver::$messages[0]['to']);
        $this->assertSame(['9800000000'], FakeDriver::$messages[1]['to']);
        $this->assertSame([], $notification->message->getTo());
    }
}

class ReusedMessageNotification extends BaseNotification
{
    public SmsMessage $message;

    public function __construct()
    {
        $this->message = SmsMessage::create('Shared');
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        return $this->message;
    }
}

class SmsUser
{
    use HasSms;
    use Notifiable;

    public function __construct(public string $phone) {}

    public function routeNotificationForSms(): string
    {
        return $this->phone;
    }
}

class StringSmsNotification extends BaseNotification
{
    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(mixed $notifiable): string
    {
        return 'Plain text';
    }
}
