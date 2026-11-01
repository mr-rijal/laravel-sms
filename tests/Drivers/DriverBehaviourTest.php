<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use Aws\Command;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\Sns\SnsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MrRijal\LaravelSms\Drivers;
use MrRijal\LaravelSms\Exceptions\DeliveryException;
use MrRijal\LaravelSms\Exceptions\InvalidConfigurationException;
use MrRijal\LaravelSms\Exceptions\InvalidMessageException;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;

class DriverBehaviourTest extends TestCase
{
    private const TWILIO = ['sid' => 'AC1', 'token' => 'tok', 'from' => '+15550000000'];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_per_recipient_driver_reports_partial_failure_without_losing_successes(): void
    {
        Http::fake(function (Request $request) {
            return $request['To'] === '+15551230002'
                ? Http::response(['message' => 'Unreachable'], 400)
                : Http::response(['sid' => 'SM-'.$request['To']], 201);
        });

        try {
            (new Drivers\TwilioDriver(self::TWILIO))->send((new SmsMessage)->to(['+15551230001', '+15551230002', '+15551230003'])->message('Hi'));
            $this->fail('Expected DeliveryException.');
        } catch (DeliveryException $e) {
            $this->assertSame(['+15551230002'], $e->failedRecipients);
            $this->assertTrue($e->hasPartialDelivery());
            $this->assertSame(['+15551230001', '+15551230003'], $e->partial?->recipients());
            $this->assertStringContainsString('(1 of 3 recipients failed)', $e->getMessage());
        }
    }

    public function test_per_recipient_requests_are_sent_concurrently_in_chunks(): void
    {
        config(['sms.http.concurrency' => 2]);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

        $numbers = ['+15551230001', '+15551230002', '+15551230003', '+15551230004', '+15551230005'];
        $result = (new Drivers\TwilioDriver(self::TWILIO))->send((new SmsMessage)->to($numbers)->message('Hi'));

        $this->assertSame($numbers, $result->recipients());
        Http::assertSentCount(5);
    }

    public function test_transport_errors_become_delivery_exceptions(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28'));

        $this->expectException(DeliveryException::class);
        $this->expectExceptionMessage('Failed to send SMS via Twilio (transport error).');

        (new Drivers\TwilioDriver(self::TWILIO))->send((new SmsMessage)->to('+15551230001')->message('Hi'));
    }

    public function test_message_sender_overrides_configured_sender(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

        (new Drivers\TwilioDriver(self::TWILIO))->send((new SmsMessage)->to('+15551230001')->message('Hi')->from('ACME'));

        Http::assertSent(fn (Request $r): bool => $r['From'] === 'ACME');
    }

    public function test_twilio_can_use_a_messaging_service(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

        (new Drivers\TwilioDriver(['sid' => 'AC1', 'token' => 'tok', 'messaging_service_sid' => 'MG1']))
            ->send((new SmsMessage)->to('+15551230001')->message('Hi'));

        Http::assertSent(fn (Request $r): bool => $r['MessagingServiceSid'] === 'MG1' && ! isset($r['From']));
    }

    public function test_text_only_drivers_reject_template_messages(): void
    {
        $this->expectException(InvalidMessageException::class);
        $this->expectExceptionMessage('Twilio requires message text; template-only messages are not supported.');

        (new Drivers\TwilioDriver(self::TWILIO))->send((new SmsMessage)->to('+15551230001')->template('OTP'));
    }

    public function test_vonage_sends_non_gsm_text_as_unicode(): void
    {
        Http::fake(['rest.nexmo.com/*' => Http::response(['messages' => [['status' => '0', 'message-id' => 'V1']]])]);

        (new Drivers\VonageDriver(['key' => 'k', 'secret' => 's', 'from' => 'Acme']))
            ->send((new SmsMessage)->to('+15551230001')->message('नमस्ते'));

        Http::assertSent(fn (Request $r): bool => $r['type'] === 'unicode');
    }

    public function test_msg91_templates_use_the_flow_api_in_one_request(): void
    {
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'success', 'request_id' => 'R1'])]);

        $result = (new Drivers\Msg91Driver(['authkey' => 'auth', 'sender' => 'ACME']))
            ->send((new SmsMessage)->to(['+919800000001', '+919800000002'])->template('TPL1', ['otp' => '1234']));

        $this->assertSame('R1', $result->firstId());
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r): bool => $r->hasHeader('authkey', 'auth')
            && $r['template_id'] === 'TPL1'
            && $r['recipients'][1] === ['mobiles' => '919800000002', 'otp' => '1234']);
    }

    public function test_whatsapp_sends_templates_with_body_parameters(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

        (new Drivers\WhatsAppDriver(['phone_number_id' => '123', 'access_token' => 'tok', 'template_language' => 'en_US']))
            ->send((new SmsMessage)->to('+15551230001')->template('order_update', ['name' => 'Ana', 'order' => 42]));

        Http::assertSent(fn (Request $r): bool => $r['type'] === 'template'
            && $r['template']['name'] === 'order_update'
            && $r['template']['language']['code'] === 'en_US'
            && $r['template']['components'][0]['parameters'][1] === ['type' => 'text', 'text' => '42']);
    }

    public function test_brevo_supports_its_own_templates(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 1], 201)]);

        (new Drivers\BrevoDriver(['api_key' => 'KEY', 'sender' => 'Acme']))
            ->send((new SmsMessage)->to('+15551230001')->template('12', ['code' => '9876']));

        Http::assertSent(fn (Request $r): bool => $r['templateId'] === 12 && $r['params'] === ['code' => '9876'] && ! isset($r['content']));
    }

    public function test_bulk_drivers_report_per_recipient_rejections(): void
    {
        Http::fake(['platform.clickatell.com/*' => Http::response(['messages' => [
            ['apiMessageId' => 'K1', 'accepted' => true],
            ['accepted' => false, 'error' => ['code' => 101, 'description' => 'Invalid destination']],
        ]], 202)]);

        try {
            (new Drivers\ClickatellDriver(['api_key' => 'KEY']))->send((new SmsMessage)->to(['+15551230001', '+15551230002'])->message('Hi'));
            $this->fail('Expected DeliveryException.');
        } catch (DeliveryException $e) {
            $this->assertSame(['+15551230002'], $e->failedRecipients);
            $this->assertSame(['+15551230001'], $e->partial?->recipients());
            $this->assertStringContainsString('Invalid destination', $e->getMessage());
        }
    }

    public function test_africastalking_reports_per_recipient_rejections(): void
    {
        Http::fake(['api.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => [
            ['statusCode' => 101, 'number' => '+254711000001', 'status' => 'Success', 'messageId' => 'A1'],
            ['statusCode' => 405, 'number' => '+254711000002', 'status' => 'InsufficientBalance'],
        ]]], 201)]);

        try {
            (new Drivers\AfricasTalkingDriver(['api_key' => 'KEY', 'username' => 'app']))
                ->send((new SmsMessage)->to(['+254711000001', '+254711000002'])->message('Hi'));
            $this->fail('Expected DeliveryException.');
        } catch (DeliveryException $e) {
            $this->assertSame(['+254711000002'], $e->failedRecipients);
            $this->assertSame(['+254711000001'], $e->partial?->recipients());
        }
    }

    public function test_config_values_are_validated(): void
    {
        foreach ([
            fn () => new Drivers\SinchDriver(['service_plan_id' => 'p', 'api_token' => 't', 'from' => 'f', 'region' => 'mars']),
            fn () => new Drivers\InfobipDriver(['api_key' => 'k', 'sender' => 's', 'base_url' => 'http://insecure.example']),
            fn () => new Drivers\TermiiDriver(['api_key' => 'k', 'sender_id' => 's', 'channel' => 'pigeon']),
            fn () => new Drivers\SendchampDriver(['api_key' => 'k', 'sender_name' => 's', 'route' => 'bogus']),
            fn () => new Drivers\BirdDriver(['api_key' => 'k', 'from' => 'f', 'category' => 'spam']),
        ] as $make) {
            try {
                $make();
                $this->fail('Expected InvalidConfigurationException.');
            } catch (InvalidConfigurationException $e) {
                $this->assertMatchesRegularExpression('/must (be one of|use HTTPS)/', $e->getMessage());
            }
        }
    }

    public function test_aws_sns_publishes_and_reports_failures_per_recipient(): void
    {
        $client = $this->createStub(SnsClient::class);
        $client->method('__call')->willReturnCallback(function (string $name, array $args): Result {
            if ($args[0]['PhoneNumber'] === '+15551230002') {
                throw new AwsException('denied', new Command('Publish'), ['message' => 'Phone number opted out']);
            }

            return new Result(['MessageId' => 'aws-'.$args[0]['PhoneNumber']]);
        });

        $driver = new Drivers\AwsSnsDriver(['region' => 'us-east-1'], $client);

        $this->assertSame('aws-+15551230001', $driver->send((new SmsMessage)->to('+15551230001')->message('Hi'))->firstId());

        try {
            $driver->send((new SmsMessage)->to(['+15551230001', '+15551230002'])->message('Hi'));
            $this->fail('Expected DeliveryException.');
        } catch (DeliveryException $e) {
            $this->assertSame(['+15551230002'], $e->failedRecipients);
            $this->assertStringContainsString('Phone number opted out', $e->getMessage());
        }
    }

    public function test_log_driver_writes_redacted_recipients(): void
    {
        Log::spy();

        $result = (new Drivers\LogDriver)->send((new SmsMessage)->to('+15551234567')->message('Debug'));

        $this->assertStringStartsWith('log-', (string) $result->firstId());
        Log::shouldHaveReceived('debug')->once()->withArgs(fn (string $message, array $context): bool => $context['recipients'] === ['***4567'] && $context['message'] === 'Debug');
    }

    public function test_fake_driver_records_messages(): void
    {
        Drivers\FakeDriver::reset();

        (new Drivers\FakeDriver)->send((new SmsMessage)->to('+15551234567')->message('Hi')->from('ACME'));

        $this->assertSame('ACME', Drivers\FakeDriver::$messages[0]['from']);
        Drivers\FakeDriver::reset();
        $this->assertSame([], Drivers\FakeDriver::$messages);
    }

    public function test_numbers_without_a_plus_work_for_per_recipient_drivers(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        $driver = new Drivers\TwilioDriver(self::TWILIO);

        $this->assertSame(['9779812345678'], $driver->send((new SmsMessage)->to('9779812345678')->message('Hi'))->recipients());
        $this->assertSame(
            ['9779812345678', '9779800000000'],
            $driver->send((new SmsMessage)->to(['9779812345678', '9779800000000'])->message('Hi'))->recipients(),
        );
    }

    public function test_a_mid_batch_auth_failure_reports_only_unsent_recipients(): void
    {
        config(['sms.http.concurrency' => 1]);
        $tokenCalls = 0;
        Http::fake([
            'api.bandwidth.com/*' => function () use (&$tokenCalls) {
                return ++$tokenCalls === 1
                    ? Http::response(['access_token' => 'T', 'expires_in' => 3600])
                    : Http::response(['error' => 'invalid_client'], 401);
            },
            'messaging.bandwidth.com/*' => function () {
                Cache::flush(); // token expires after the first send

                return Http::response(['id' => 'bw1'], 202);
            },
        ]);

        try {
            (new Drivers\BandwidthDriver([
                'account_id' => '1', 'application_id' => 'a', 'client_id' => 'c', 'client_secret' => 's', 'from' => '+15550000000',
            ]))->send((new SmsMessage)->to(['+15551230001', '+15551230002', '+15551230003'])->message('Hi'));
            $this->fail('Expected DeliveryException.');
        } catch (DeliveryException $e) {
            $this->assertSame(['+15551230001'], $e->partial?->recipients());
            $this->assertSame(['+15551230002', '+15551230003'], $e->failedRecipients);
            $this->assertStringContainsString('Failed to authenticate with Bandwidth', $e->getMessage());
        }
    }

    public function test_a_401_drops_the_cached_oauth_token(): void
    {
        Http::fake([
            'api.sendpulse.com/oauth/access_token' => Http::sequence()
                ->push(['access_token' => 'OLD', 'expires_in' => 3600])
                ->push(['access_token' => 'NEW', 'expires_in' => 3600]),
            'api.sendpulse.com/sms/send' => Http::sequence()
                ->push(['message' => 'Token revoked'], 401)
                ->push(['result' => true, 'campaign_id' => 7]),
        ]);
        $driver = new Drivers\SendPulseDriver(['client_id' => 'cid', 'client_secret' => 'cs', 'sender' => 'Acme']);

        try {
            $driver->send((new SmsMessage)->to('+15551230001')->message('Hi'));
        } catch (DeliveryException) {
        }

        $this->assertSame('7', $driver->send((new SmsMessage)->to('+15551230001')->message('Hi'))->firstId());
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'sms/send') && $r->hasHeader('Authorization', 'Bearer NEW'));
    }
}
