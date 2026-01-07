<?php

namespace MrRijal\LaravelSms\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use MrRijal\LaravelSms\Drivers\WhatsAppDriver;
use MrRijal\LaravelSms\Events\SmsWebhookReceived;
use MrRijal\LaravelSms\Http\Controllers\WebhookController;
use MrRijal\LaravelSms\SmsMessage;

class WhatsAppTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure WhatsApp provider
        config()->set('sms.drivers.whatsapp', WhatsAppDriver::class);
        config()->set('sms.providers.whatsapp', [
            'phone_number_id' => '123456789',
            'access_token' => 'test_access_token',
            'api_version' => 'v21.0',
        ]);

        config()->set('sms.webhooks.enabled', true);
        config()->set('sms.webhooks.whatsapp', [
            'secret' => 'test_webhook_secret',
            'verify_token' => 'test_verify_token',
        ]);
    }

    public function test_whatsapp_driver_requires_phone_number_id_and_access_token()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WhatsApp Business API configuration is incomplete');

        new WhatsAppDriver([]);
    }

    public function test_whatsapp_driver_requires_phone_number_id()
    {
        $this->expectException(\InvalidArgumentException::class);

        new WhatsAppDriver([
            'access_token' => 'test_token',
        ]);
    }

    public function test_whatsapp_driver_requires_access_token()
    {
        $this->expectException(\InvalidArgumentException::class);

        new WhatsAppDriver([
            'phone_number_id' => '123456789',
        ]);
    }

    public function test_whatsapp_driver_can_send_text_message()
    {
        $mockResponse = new Response(200, [], json_encode([
            'messages' => [['id' => 'wamid.test123']],
        ]));

        $mockHandler = new MockHandler([$mockResponse]);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
        ]);

        // Use reflection to inject mock client
        $reflection = new \ReflectionClass($driver);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($driver, $client);

        $message = new SmsMessage();
        $message->to('+1234567890');
        $message->message('Test message');

        $result = $driver->send($message);

        $this->assertTrue($result);

        // Verify request was made
        $request = $mockHandler->getLastRequest();
        $this->assertNotNull($request);
        $this->assertEquals('POST', $request->getMethod());
        $this->assertStringContainsString('123456789/messages', (string) $request->getUri());

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertEquals('whatsapp', $body['messaging_product']);
        $this->assertEquals('text', $body['type']);
        $this->assertEquals('+1234567890', $body['to']); // WhatsApp driver adds + prefix
        $this->assertEquals('Test message', $body['text']['body']);
    }

    public function test_whatsapp_driver_can_send_template_message()
    {
        $mockResponse = new Response(200, [], json_encode([
            'messages' => [['id' => 'wamid.template123']],
        ]));

        $mockHandler = new MockHandler([$mockResponse]);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
            'template_language' => 'en',
        ]);

        $reflection = new \ReflectionClass($driver);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($driver, $client);

        $message = new SmsMessage();
        $message->to('+1234567890');
        $message->template('welcome_template', ['name' => 'John', 'code' => '1234']);

        $result = $driver->send($message);

        $this->assertTrue($result);

        $request = $mockHandler->getLastRequest();
        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertEquals('template', $body['type']);
        $this->assertEquals('welcome_template', $body['template']['name']);
        $this->assertEquals('en', $body['template']['language']['code']);
        $this->assertArrayHasKey('components', $body['template']);
        $this->assertCount(1, $body['template']['components']);
        $this->assertEquals('body', $body['template']['components'][0]['type']);
    }

    public function test_whatsapp_driver_handles_api_errors()
    {
        $mockResponse = new Response(400, [], json_encode([
            'error' => [
                'message' => 'Invalid phone number',
                'code' => 100,
            ],
        ]));

        $mockHandler = new MockHandler([$mockResponse]);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
        ]);

        $reflection = new \ReflectionClass($driver);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($driver, $client);

        $message = new SmsMessage();
        $message->to('+1234567890');
        $message->message('Test message');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to send WhatsApp');

        $driver->send($message);
    }

    public function test_whatsapp_webhook_verification_succeeds_with_valid_token()
    {
        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
        ]);

        $request = Request::create('/webhook', 'GET', [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'test_verify_token',
            'hub.challenge' => 'test_challenge_123',
        ]);

        $response = $driver->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('test_challenge_123', $response->getContent());
    }

    public function test_whatsapp_webhook_verification_fails_with_invalid_token()
    {
        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
        ]);

        $request = Request::create('/webhook', 'GET', [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => 'wrong_token',
            'hub.challenge' => 'test_challenge_123',
        ]);

        $response = $driver->handleWebhook($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_whatsapp_webhook_verification_fails_with_wrong_mode()
    {
        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
        ]);

        $request = Request::create('/webhook', 'GET', [
            'hub.mode' => 'unsubscribe',
            'hub.verify_token' => 'test_verify_token',
            'hub.challenge' => 'test_challenge_123',
        ]);

        $response = $driver->handleWebhook($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_whatsapp_webhook_handles_status_update()
    {
        Event::fake();

        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'statuses' => [
                                    [
                                        'id' => 'wamid.123',
                                        'status' => 'delivered',
                                        'recipient_id' => '1234567890',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $payloadJson = json_encode($payload);
        $request = Request::create('/webhook', 'POST', [], [], [], [], $payloadJson);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('X-Hub-Signature-256', 'sha256=' . hash_hmac('sha256', $payloadJson, 'test_webhook_secret'));
        // Merge JSON data so $request->all() works
        $request->merge($payload);

        $response = $driver->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());

        Event::assertDispatched(SmsWebhookReceived::class, function ($event) {
            return $event->provider === 'whatsapp'
                && $event->messageId === 'wamid.123'
                && $event->status === 'delivered'
                && $event->recipient === '1234567890';
        });
    }

    public function test_whatsapp_webhook_handles_incoming_message()
    {
        Event::fake();

        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'id' => 'wamid.incoming123',
                                        'from' => '9876543210',
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $payloadJson = json_encode($payload);
        $request = Request::create('/webhook', 'POST', [], [], [], [], $payloadJson);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('X-Hub-Signature-256', 'sha256=' . hash_hmac('sha256', $payloadJson, 'test_webhook_secret'));
        // Merge JSON data so $request->all() works
        $request->merge($payload);

        $response = $driver->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());

        Event::assertDispatched(SmsWebhookReceived::class, function ($event) {
            return $event->provider === 'whatsapp'
                && $event->messageId === 'wamid.incoming123'
                && $event->status === 'received'
                && $event->recipient === '9876543210';
        });
    }

    public function test_whatsapp_webhook_verifies_signature()
    {
        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
        ]);

        $payload = ['test' => 'data'];
        $request = Request::create('/webhook', 'POST', $payload);
        $request->headers->set('X-Hub-Signature-256', 'sha256=' . hash_hmac('sha256', json_encode($payload), 'wrong_secret'));

        $response = $driver->handleWebhook($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Unauthorized', $response->getContent());
    }

    public function test_whatsapp_webhook_allows_requests_without_secret_when_not_configured()
    {
        Event::fake();

        config()->set('sms.webhooks.whatsapp', []);

        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'statuses' => [
                                    [
                                        'id' => 'wamid.123',
                                        'status' => 'sent',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $request = Request::create('/webhook', 'POST', $payload);

        $response = $driver->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());
        Event::assertDispatched(SmsWebhookReceived::class);
    }

    public function test_webhook_controller_routes_to_whatsapp_driver()
    {
        Event::fake();

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'statuses' => [
                                    [
                                        'id' => 'wamid.test',
                                        'status' => 'delivered',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $payloadJson = json_encode($payload);
        $request = Request::create('/laravel-sms/webhook/whatsapp', 'POST', [], [], [], [], $payloadJson);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('X-Hub-Signature-256', 'sha256=' . hash_hmac('sha256', $payloadJson, 'test_webhook_secret'));
        // Merge JSON data so $request->all() works
        $request->merge($payload);

        $controller = new WebhookController(app('laravel-sms'));
        $response = $controller->handle($request, 'whatsapp');

        $this->assertEquals(200, $response->getStatusCode());
        Event::assertDispatched(SmsWebhookReceived::class);
    }

    public function test_webhook_controller_returns_404_for_provider_without_webhook_support()
    {
        $request = Request::create('/laravel-sms/webhook/fake', 'POST');

        $controller = new WebhookController(app('laravel-sms'));
        $response = $controller->handle($request, 'fake');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Webhook not supported for this provider', $response->getContent());
    }

    public function test_webhook_controller_returns_404_for_invalid_provider()
    {
        $request = Request::create('/laravel-sms/webhook/invalid', 'POST');

        $controller = new WebhookController(app('laravel-sms'));
        $response = $controller->handle($request, 'invalid');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Provider not found', $response->getContent());
    }

    public function test_whatsapp_driver_formats_phone_number_with_country_code()
    {
        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
            'default_country_code' => '+1',
        ]);

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('formatPhoneNumber');
        $method->setAccessible(true);

        $formatted = $method->invoke($driver, '1234567890');
        $this->assertEquals('+11234567890', $formatted);
    }

    public function test_whatsapp_driver_preserves_phone_number_with_plus()
    {
        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
        ]);

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('formatPhoneNumber');
        $method->setAccessible(true);

        $formatted = $method->invoke($driver, '+1234567890');
        $this->assertEquals('+1234567890', $formatted);
    }

    public function test_whatsapp_driver_removes_non_numeric_characters()
    {
        $driver = new WhatsAppDriver([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
        ]);

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('formatPhoneNumber');
        $method->setAccessible(true);

        $formatted = $method->invoke($driver, '+1 (234) 567-890');
        $this->assertEquals('+1234567890', $formatted);
    }
}
