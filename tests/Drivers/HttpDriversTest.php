<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MrRijal\LaravelSms\Drivers;
use MrRijal\LaravelSms\Exceptions\DeliveryException;
use MrRijal\LaravelSms\Exceptions\InvalidConfigurationException;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * One contract test per HTTP driver: correct request on success, bounded error on failure,
 * and a clear exception for incomplete configuration.
 */
class HttpDriversTest extends TestCase
{
    private const TO = ['+15551230001', '+15551230002'];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
    }

    /**
     * @return array<string, array{class-string<Drivers\Driver>, array<string, mixed>, string, int, array<mixed>, Closure(Request): bool, int, array<mixed>, string}>
     */
    public static function drivers(): array
    {
        return [
            'twilio' => [
                Drivers\TwilioDriver::class, ['sid' => 'AC1', 'token' => 'tok', 'from' => '+15550000000'],
                'api.twilio.com/2010-04-01/Accounts/AC1/Messages.json', 201, ['sid' => 'SM1', 'status' => 'queued'],
                fn (Request $r): bool => $r->hasHeader('Authorization', 'Basic '.base64_encode('AC1:tok')) && $r['Body'] === 'Hello' && $r['From'] === '+15550000000',
                400, ['message' => 'The To number is invalid'], 'Failed to send SMS via Twilio (HTTP 400): The To number is invalid',
            ],
            'vonage' => [
                Drivers\VonageDriver::class, ['key' => 'k', 'secret' => 's', 'from' => 'Acme'],
                'rest.nexmo.com/sms/json', 200, ['messages' => [['status' => '0', 'message-id' => 'V1']]],
                fn (Request $r): bool => $r['api_key'] === 'k' && in_array($r['to'], ['15551230001', '15551230002'], true) && ! isset($r['type']),
                200, ['messages' => [['status' => '2', 'error-text' => 'Missing to param']]], 'Failed to send SMS via Vonage (HTTP 200): Missing to param',
            ],
            'msg91' => [
                Drivers\Msg91Driver::class, ['authkey' => 'auth', 'sender' => 'ACME'],
                'api.msg91.com/api/v2/sendsms', 200, ['type' => 'success', 'message' => 'M1'],
                fn (Request $r): bool => $r['authkey'] === 'auth' && $r['sender'] === 'ACME',
                200, ['type' => 'error', 'message' => 'Invalid authkey'], 'Failed to send SMS via MSG91 (HTTP 200): Invalid authkey',
            ],
            'sparrow' => [
                Drivers\SparrowDriver::class, ['token' => 'tok', 'from' => 'Demo'],
                'api.sparrowsms.com/v2/sms/', 200, ['response_code' => 200, 'response' => '2 messages queued', 'message_id' => 'SP1'],
                fn (Request $r): bool => $r['to'] === '15551230001,15551230002' && $r['token'] === 'tok',
                403, ['response_code' => 1002, 'response' => 'Invalid Token'], 'Failed to send SMS via Sparrow (HTTP 403): Invalid Token',
            ],
            'telnyx' => [
                Drivers\TelnyxDriver::class, ['api_key' => 'KEY', 'from' => '+15550000000'],
                'api.telnyx.com/v2/messages', 200, ['data' => ['id' => 'T1', 'to' => [['status' => 'queued']]]],
                fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer KEY') && $r['type'] === 'SMS',
                422, ['errors' => [['detail' => 'Invalid destination']]], 'Failed to send SMS via Telnyx (HTTP 422): Invalid destination',
            ],
            'plivo' => [
                Drivers\PlivoDriver::class, ['auth_id' => 'MA1', 'auth_token' => 'tok', 'from' => '+15550000000'],
                'api.plivo.com/v1/Account/MA1/Message/', 202, ['message_uuid' => ['P1', 'P2']],
                fn (Request $r): bool => $r['dst'] === '+15551230001<+15551230002' && $r['log'] === false,
                400, ['error' => 'src parameter not present'], 'Failed to send SMS via Plivo (HTTP 400): src parameter not present',
            ],
            'infobip' => [
                Drivers\InfobipDriver::class, ['api_key' => 'KEY', 'sender' => 'Acme', 'base_url' => 'https://xyz.api.infobip.com/'],
                'xyz.api.infobip.com/sms/3/messages', 200, ['messages' => [['messageId' => 'I1'], ['messageId' => 'I2']]],
                fn (Request $r): bool => $r->hasHeader('Authorization', 'App KEY') && $r['messages'][0]['destinations'][1]['to'] === '15551230002',
                401, ['requestError' => ['serviceException' => ['text' => 'Invalid login details']]], 'Failed to send SMS via Infobip (HTTP 401): Invalid login details',
            ],
            'sendchamp' => [
                Drivers\SendchampDriver::class, ['api_key' => 'KEY', 'sender_name' => 'Acme'],
                'api.sendchamp.com/api/v1/sms/send', 200, ['status' => 'success', 'data' => ['business_uid' => 'S1']],
                fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer KEY') && $r['route'] === 'dnd' && $r['to'] === ['15551230001', '15551230002'],
                400, ['status' => 'failed', 'message' => 'Insufficient balance'], 'Failed to send SMS via Sendchamp (HTTP 400): Insufficient balance',
            ],
            'termii' => [
                Drivers\TermiiDriver::class, ['api_key' => 'KEY', 'sender_id' => 'Acme'],
                'api.ng.termii.com/api/sms/send', 200, ['code' => 'ok', 'message_id' => 'TM1'],
                fn (Request $r): bool => $r['api_key'] === 'KEY' && $r['channel'] === 'generic' && $r['type'] === 'plain',
                400, ['code' => 'error', 'message' => 'Sender not approved'], 'Failed to send SMS via Termii (HTTP 400): Sender not approved',
            ],
            'africastalking' => [
                Drivers\AfricasTalkingDriver::class, ['api_key' => 'KEY', 'username' => 'sandbox', 'sandbox' => 'true'],
                'api.sandbox.africastalking.com/version1/messaging', 201, ['SMSMessageData' => ['Recipients' => [
                    ['statusCode' => 101, 'number' => '+15551230001', 'status' => 'Success', 'messageId' => 'A1'],
                    ['statusCode' => 101, 'number' => '+15551230002', 'status' => 'Success', 'messageId' => 'A2'],
                ]]],
                fn (Request $r): bool => $r->hasHeader('apiKey', 'KEY') && $r['to'] === '+15551230001,+15551230002',
                401, ['SMSMessageData' => ['Message' => 'Invalid API key']], "Failed to send SMS via Africa's Talking (HTTP 401): Invalid API key",
            ],
            'bulksms' => [
                Drivers\BulkSmsDriver::class, ['token_id' => 'id', 'token_secret' => 'secret', 'from' => 'Acme'],
                'api.bulksms.com/v1/messages', 201, [['id' => 'B1', 'status' => ['type' => 'ACCEPTED']], ['id' => 'B2']],
                fn (Request $r): bool => $r->hasHeader('Authorization', 'Basic '.base64_encode('id:secret')) && $r->data()[1]['to'] === '+15551230002',
                400, ['detail' => 'Invalid number'], 'Failed to send SMS via BulkSMS (HTTP 400): Invalid number',
            ],
            'sinch' => [
                Drivers\SinchDriver::class, ['service_plan_id' => 'PLAN', 'api_token' => 'tok', 'from' => '+15550000000', 'region' => 'eu'],
                'eu.sms.api.sinch.com/xms/v1/PLAN/batches', 201, ['id' => 'batch1'],
                fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer tok') && $r['to'] === self::TO && $r['body'] === 'Hello',
                400, ['code' => 'syntax_invalid_parameter_format', 'text' => 'Invalid phone number'], 'Failed to send SMS via Sinch (HTTP 400): Invalid phone number',
            ],
            'bird' => [
                Drivers\BirdDriver::class, ['api_key' => 'bk_us1_x', 'from' => '+15550000000'],
                'us1.platform.bird.com/v1/sms/messages', 202, ['id' => 'sms_1', 'status' => 'accepted'],
                fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer bk_us1_x') && $r['category'] === 'transactional' && $r['text'] === 'Hello',
                422, ['message' => 'Invalid sender'], 'Failed to send SMS via Bird (HTTP 422): Invalid sender',
            ],
            'clicksend' => [
                Drivers\ClickSendDriver::class, ['username' => 'user', 'api_key' => 'key'],
                'rest.clicksend.com/v3/sms/send', 200, ['response_code' => 'SUCCESS', 'data' => ['messages' => [
                    ['status' => 'SUCCESS', 'message_id' => 'C1'], ['status' => 'SUCCESS', 'message_id' => 'C2'],
                ]]],
                fn (Request $r): bool => $r->hasHeader('Authorization', 'Basic '.base64_encode('user:key')) && count($r['messages']) === 2,
                401, ['response_code' => 'UNAUTHORIZED', 'response_msg' => 'Invalid credentials'], 'Failed to send SMS via ClickSend (HTTP 401): Invalid credentials',
            ],
            'clickatell' => [
                Drivers\ClickatellDriver::class, ['api_key' => 'KEY'],
                'platform.clickatell.com/v1/message', 202, ['messages' => [
                    ['apiMessageId' => 'K1', 'accepted' => true], ['apiMessageId' => 'K2', 'accepted' => true],
                ]],
                fn (Request $r): bool => $r->hasHeader('Authorization', 'KEY') && $r['messages'][0]['to'] === '15551230001' && $r['messages'][0]['channel'] === 'sms',
                401, ['error' => ['code' => 1, 'description' => 'Invalid or missing API key']], 'Failed to send SMS via Clickatell (HTTP 401): Invalid or missing API key',
            ],
            'brevo' => [
                Drivers\BrevoDriver::class, ['api_key' => 'KEY', 'sender' => 'Acme'],
                'api.brevo.com/v3/transactionalSMS/send', 201, ['messageId' => 1511882900176220],
                fn (Request $r): bool => $r->hasHeader('api-key', 'KEY') && $r['content'] === 'Hello' && $r['type'] === 'transactional',
                400, ['code' => 'invalid_parameter', 'message' => 'Invalid sender'], 'Failed to send SMS via Brevo (HTTP 400): Invalid sender',
            ],
            'whatsapp' => [
                Drivers\WhatsAppDriver::class, ['phone_number_id' => '123', 'access_token' => 'tok'],
                'graph.facebook.com/v21.0/123/messages', 200, ['messages' => [['id' => 'wamid.1']]],
                fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer tok') && $r['type'] === 'text' && $r['text']['body'] === 'Hello',
                400, ['error' => ['message' => 'Invalid parameter']], 'Failed to send SMS via WhatsApp (HTTP 400): Invalid parameter',
            ],
        ];
    }

    /**
     * @param  class-string<Drivers\Driver>  $class
     * @param  array<string, mixed>  $config
     * @param  array<mixed>  $okBody
     * @param  Closure(Request): bool  $assertRequest
     */
    #[DataProvider('drivers')]
    public function test_sends_and_returns_accepted_recipients(string $class, array $config, string $url, int $okStatus, array $okBody, Closure $assertRequest, mixed ...$failureCase): void
    {
        Http::fake(["{$url}*" => Http::response($okBody, $okStatus)]);

        $result = (new $class($config))->send((new SmsMessage)->to(self::TO)->message('Hello'));

        $this->assertSame(self::TO, $result->recipients());
        $this->assertNotEmpty($result->ids());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), $url) && $assertRequest($request));
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent', 'mr-rijal/laravel-sms'));
    }

    /**
     * @param  class-string<Drivers\Driver>  $class
     * @param  array<string, mixed>  $config
     * @param  array<mixed>  $okBody
     * @param  array<mixed>  $errorBody
     */
    #[DataProvider('drivers')]
    public function test_failure_throws_delivery_exception_with_provider_detail(
        string $class, array $config, string $url, int $okStatus, array $okBody, Closure $assertRequest,
        int $errorStatus, array $errorBody, string $expected,
    ): void {
        Http::fake(["{$url}*" => Http::response($errorBody, $errorStatus)]);

        try {
            (new $class($config))->send((new SmsMessage)->to(self::TO)->message('Hello'));
            $this->fail('Expected DeliveryException.');
        } catch (DeliveryException $e) {
            $this->assertStringStartsWith($expected, $e->getMessage());
            $this->assertSame(self::TO, $e->failedRecipients);
            $this->assertFalse($e->hasPartialDelivery());
        }
    }

    /**
     * @param  class-string<Drivers\Driver>  $class
     */
    #[DataProvider('drivers')]
    public function test_incomplete_configuration_is_rejected(string $class, mixed ...$rest): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('configuration is incomplete');

        new $class([]);
    }

    public function test_bandwidth_fetches_and_caches_an_oauth_token(): void
    {
        Http::fake([
            'api.bandwidth.com/api/v1/oauth2/token' => Http::response(['access_token' => 'BW_TOKEN', 'expires_in' => 3600]),
            'messaging.bandwidth.com/*' => Http::response(['id' => 'bw1'], 202),
        ]);

        $driver = new Drivers\BandwidthDriver([
            'account_id' => '9900', 'application_id' => 'app', 'client_id' => 'cid', 'client_secret' => 'csecret', 'from' => '+15550000000',
        ]);

        $driver->send((new SmsMessage)->to('+15551230001')->message('One'));
        $result = $driver->send((new SmsMessage)->to('+15551230002')->message('Two'));

        $this->assertSame('bw1', $result->firstId());
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'oauth2/token')
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('cid:csecret'))
            && $r['grant_type'] === 'client_credentials');
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'users/9900/messages')
            && $r->hasHeader('Authorization', 'Bearer BW_TOKEN')
            && $r['applicationId'] === 'app');
    }

    public function test_sendpulse_authenticates_then_sends(): void
    {
        Http::fake([
            'api.sendpulse.com/oauth/access_token' => Http::response(['access_token' => 'SP_TOKEN', 'expires_in' => 3600]),
            'api.sendpulse.com/sms/send' => Http::response(['result' => true, 'campaign_id' => 42]),
        ]);

        $result = (new Drivers\SendPulseDriver(['client_id' => 'cid', 'client_secret' => 'cs', 'sender' => 'Acme']))
            ->send((new SmsMessage)->to(self::TO)->message('Hello'));

        $this->assertSame('42', $result->firstId());
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'sms/send')
            && $r->hasHeader('Authorization', 'Bearer SP_TOKEN')
            && $r['phones'] === ['15551230001', '15551230002']);
    }

    public function test_failed_authentication_does_not_leak_credentials(): void
    {
        Http::fake(['api.sendpulse.com/oauth/access_token' => Http::response(['error' => 'invalid_client'], 401)]);

        try {
            (new Drivers\SendPulseDriver(['client_id' => 'cid', 'client_secret' => 'super-secret', 'sender' => 'Acme']))
                ->send((new SmsMessage)->to('+15551230001')->message('Hello'));
            $this->fail('Expected DeliveryException.');
        } catch (DeliveryException $e) {
            $this->assertSame('Failed to authenticate with SendPulse (HTTP 401).', $e->getMessage());
            $this->assertStringNotContainsString('super-secret', $e->getMessage());
        }
    }
}
