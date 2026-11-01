<?php

namespace MrRijal\LaravelSms\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use MrRijal\LaravelSms\Events\SmsWebhookReceived;
use PHPUnit\Framework\Attributes\DataProvider;

class WebhookRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sms.webhooks.enabled', true);
        $app['config']->set('sms.providers.sendchamp', ['api_key' => 'key', 'sender_name' => 'Sender']);
        $app['config']->set('sms.providers.termii', ['api_key' => 'key', 'sender_id' => 'Sender']);
        $app['config']->set('sms.providers.africastalking', ['api_key' => 'key', 'username' => 'sandbox']);
        $app['config']->set('sms.providers.bulksms', ['token_id' => 'id', 'token_secret' => 'secret']);
    }

    public function test_route_is_named_and_uses_stateless_middleware(): void
    {
        $route = Route::getRoutes()->getByName('sms.webhook');

        $this->assertNotNull($route);
        $this->assertSame('laravel-sms/webhook/{provider}', $route->uri());
        $this->assertContains('api', $route->middleware());
        $this->assertNotContains('web', $route->middleware());
    }

    public function test_post_webhook_is_not_blocked_by_csrf(): void
    {
        Event::fake([SmsWebhookReceived::class]);
        $this->app['config']->set('sms.webhooks.sendchamp.secret', 'shh');
        $body = '{"sms_uid":"abc","status":"delivered"}';

        $this->call('POST', '/laravel-sms/webhook/sendchamp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SENDCHAMP_SIGNATURE' => hash_hmac('sha256', $body, 'shh'),
        ], $body)->assertOk();

        Event::assertDispatched(SmsWebhookReceived::class, fn (SmsWebhookReceived $e): bool => $e->provider === 'sendchamp'
            && $e->messageId === 'abc'
            && $e->status === 'delivered');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function signedProviders(): array
    {
        return [
            'sendchamp' => ['sendchamp', 'X-Sendchamp-Signature', 'sha256'],
            'termii' => ['termii', 'X-Termii-Signature', 'sha512'],
            'bulksms' => ['bulksms', 'X-BulkSMS-Signature', 'sha256'],
        ];
    }

    #[DataProvider('signedProviders')]
    public function test_signed_webhooks_fail_closed_without_a_configured_secret(string $provider, string $header, string $algo): void
    {
        $body = '{"status":"delivered"}';

        $this->call('POST', "/laravel-sms/webhook/{$provider}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_'.strtoupper(str_replace('-', '_', $header)) => hash_hmac($algo, $body, ''),
        ], $body)->assertUnauthorized();
    }

    #[DataProvider('signedProviders')]
    public function test_signed_webhooks_reject_invalid_signatures(string $provider, string $header, string $algo): void
    {
        $this->app['config']->set("sms.webhooks.{$provider}.secret", 'right');
        $body = '{"status":"delivered"}';

        $this->call('POST', "/laravel-sms/webhook/{$provider}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_'.strtoupper(str_replace('-', '_', $header)) => hash_hmac($algo, $body, 'wrong'),
        ], $body)->assertUnauthorized();
    }

    #[DataProvider('signedProviders')]
    public function test_signed_webhooks_accept_valid_signatures(string $provider, string $header, string $algo): void
    {
        Event::fake([SmsWebhookReceived::class]);
        $this->app['config']->set("sms.webhooks.{$provider}.secret", 'right');
        $body = '{"status":"delivered"}';

        $this->call('POST', "/laravel-sms/webhook/{$provider}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_'.strtoupper(str_replace('-', '_', $header)) => hash_hmac($algo, $body, 'right'),
        ], $body)->assertOk();

        Event::assertDispatched(SmsWebhookReceived::class);
    }

    public function test_africastalking_rejects_spoofed_user_agent_without_token(): void
    {
        $this->app['config']->set('sms.webhooks.africastalking.secret', 'at-secret');

        $this->withHeader('User-Agent', 'AfricasTalking')
            ->post('/laravel-sms/webhook/africastalking', ['id' => 'ATX1', 'status' => 'Success'])
            ->assertUnauthorized();
    }

    public function test_africastalking_fails_closed_without_configured_secret(): void
    {
        $this->post('/laravel-sms/webhook/africastalking?token=', ['id' => 'ATX1'])->assertUnauthorized();
    }

    public function test_africastalking_accepts_valid_token_and_strips_it_from_payload(): void
    {
        Event::fake([SmsWebhookReceived::class]);
        $this->app['config']->set('sms.webhooks.africastalking.secret', 'at-secret');

        $this->post('/laravel-sms/webhook/africastalking?token=at-secret', [
            'id' => 'ATX1',
            'status' => 'Success',
            'phoneNumber' => '+254711123456',
        ])->assertOk();

        Event::assertDispatched(SmsWebhookReceived::class, fn (SmsWebhookReceived $e): bool => $e->messageId === 'ATX1'
            && $e->recipient === '+254711123456'
            && ! array_key_exists('token', $e->payload));
    }

    public function test_invalid_provider_names_do_not_match_the_route(): void
    {
        $this->post('/laravel-sms/webhook/'.rawurlencode('../etc'))->assertNotFound();
    }

    public function test_unknown_provider_returns_not_found(): void
    {
        $this->post('/laravel-sms/webhook/nope')->assertNotFound();
    }
}
