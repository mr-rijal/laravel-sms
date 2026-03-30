<?php

namespace MrRijal\LaravelSms\Tests;

use Illuminate\Http\Request;
use MrRijal\LaravelSms\Http\Controllers\WebhookController;

class WebhookControllerTest extends TestCase
{
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
        $request = Request::create(
            '/laravel-sms/webhook/whatsapp',
            'GET',
            [
                'hub.mode' => 'subscribe',
                'hub.verify_token' => 'my-secret-token',
                'hub.challenge' => 'challenge-123',
            ]
        );

        $response = $controller->handle($request, 'whatsapp');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('challenge-123', $response->getContent());
    }

    public function test_whatsapp_get_verification_returns_403_when_token_mismatches(): void
    {
        $this->app['config']->set('sms.webhooks.whatsapp.verify_token', 'expected');

        $controller = new WebhookController($this->app->make('laravel-sms'));
        $request = Request::create(
            '/laravel-sms/webhook/whatsapp',
            'GET',
            [
                'hub.mode' => 'subscribe',
                'hub.verify_token' => 'wrong',
                'hub.challenge' => 'challenge-123',
            ]
        );

        $response = $controller->handle($request, 'whatsapp');

        $this->assertSame(403, $response->getStatusCode());
    }
}
