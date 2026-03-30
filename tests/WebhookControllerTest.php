<?php

namespace MrRijal\LaravelSms\Tests;

use Illuminate\Http\Request;
use MrRijal\LaravelSms\Http\Controllers\WebhookController;

class WebhookControllerTest extends TestCase
{
    /**
     * Real GET requests use a query string with dotted keys (hub.mode, ...); PHP exposes them as hub_mode, ...
     *
     * @param  array<string, string>  $facebookHubQuery  Keys use Facebook's dotted names; they are encoded into the URI.
     */
    private function createWhatsappVerificationRequest(array $facebookHubQuery): Request
    {
        $query = http_build_query($facebookHubQuery);

        return Request::create("/laravel-sms/webhook/whatsapp?{$query}", 'GET');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('sms.providers.whatsapp', [
            'phone_number_id' => '123456',
            'access_token' => 'test-token',
            'api_version' => 'v21.0',
        ]);
    }

    public function test_returns_404_when_driver_has_no_webhook_handler(): void
    {
        $controller = new WebhookController($this->app->make('laravel-sms'));
        $request = Request::create('/laravel-sms/webhook/fake', 'POST');

        $response = $controller->handle($request, 'fake');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('not supported', (string) $response->getContent());
    }

    public function test_returns_404_for_unknown_provider(): void
    {
        $controller = new WebhookController($this->app->make('laravel-sms'));
        $request = Request::create('/laravel-sms/webhook/missing', 'POST');

        $response = $controller->handle($request, 'missing');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_whatsapp_get_verification_returns_challenge_when_token_matches(): void
    {
        $this->app['config']->set('sms.webhooks.whatsapp.verify_token', 'my-secret-token');

        $controller = new WebhookController($this->app->make('laravel-sms'));
        $request = $this->createWhatsappVerificationRequest([
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'my-secret-token',
            'hub.challenge' => 'challenge-123',
        ]);

        $response = $controller->handle($request, 'whatsapp');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('challenge-123', $response->getContent());
    }

    public function test_whatsapp_get_verification_returns_403_when_token_mismatches(): void
    {
        $this->app['config']->set('sms.webhooks.whatsapp.verify_token', 'expected');

        $controller = new WebhookController($this->app->make('laravel-sms'));
        $request = $this->createWhatsappVerificationRequest([
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'wrong',
            'hub.challenge' => 'challenge-123',
        ]);

        $response = $controller->handle($request, 'whatsapp');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_whatsapp_get_verification_returns_403_when_verify_token_not_configured(): void
    {
        $this->app['config']->set('sms.webhooks.whatsapp', []);

        $controller = new WebhookController($this->app->make('laravel-sms'));
        $request = $this->createWhatsappVerificationRequest([
            'hub.mode' => 'subscribe',
            'hub.verify_token' => '',
            'hub.challenge' => 'challenge-123',
        ]);

        $response = $controller->handle($request, 'whatsapp');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_whatsapp_get_verification_returns_403_when_verify_token_config_is_whitespace_only(): void
    {
        $this->app['config']->set('sms.webhooks.whatsapp.verify_token', '   ');

        $controller = new WebhookController($this->app->make('laravel-sms'));
        $request = $this->createWhatsappVerificationRequest([
            'hub.mode' => 'subscribe',
            'hub.verify_token' => '   ',
            'hub.challenge' => 'challenge-123',
        ]);

        $response = $controller->handle($request, 'whatsapp');

        $this->assertSame(403, $response->getStatusCode());
    }
}
