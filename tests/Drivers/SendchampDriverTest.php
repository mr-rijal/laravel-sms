<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Drivers\SendchampDriver;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;

class SendchampDriverTest extends TestCase
{
    public function test_implements_sms_provider(): void
    {
        $driver = new SendchampDriver([
            'api_key' => 'test-key',
            'sender_name' => 'TestSender',
        ]);

        $this->assertInstanceOf(SmsProvider::class, $driver);
    }

    public function test_constructor_throws_when_api_key_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sendchamp configuration is incomplete');

        new SendchampDriver([
            'sender_name' => 'TestSender',
        ]);
    }

    public function test_constructor_throws_when_sender_name_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sendchamp configuration is incomplete');

        new SendchampDriver([
            'api_key' => 'test-key',
        ]);
    }

    public function test_constructor_succeeds_with_valid_config(): void
    {
        $driver = new SendchampDriver([
            'api_key' => 'test-key',
            'sender_name' => 'TestSender',
        ]);

        $this->assertInstanceOf(SendchampDriver::class, $driver);
    }

    public function test_send_throws_when_no_body_text(): void
    {
        $driver = new SendchampDriver([
            'api_key' => 'test-key',
            'sender_name' => 'TestSender',
        ]);
        $message = (new SmsMessage)->to('+2348134844186');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sendchamp SMS requires a non-empty message body');

        $driver->send($message);
    }

    public function test_send_success_with_mock_client(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'code' => 200,
                'data' => [
                    'id' => 'MN-SMS-ad51fcd1-8257-4fc5-b6df-11cf2ddcdfac',
                    'phone_number' => '2348134844186',
                    'reference' => 'MN-SMS-ad51fcd1-8257-4fc5-b6df-11cf2ddcdfac',
                    'status' => 'processing',
                ],
                'errors' => null,
                'message' => 'processing',
                'status' => 'success',
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new SendchampDriver([
            'api_key' => 'test-key',
            'sender_name' => 'TestSender',
            'route' => 'dnd',
        ], $client);

        $message = (new SmsMessage)->to('+2348134844186')->message('Hello Sendchamp');
        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    public function test_send_throws_on_failed_response(): void
    {
        $mock = new MockHandler([
            new Response(400, [], json_encode([
                'code' => 400,
                'data' => null,
                'errors' => 'Message required',
                'message' => 'Message required',
                'status' => 'failed',
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new SendchampDriver([
            'api_key' => 'test-key',
            'sender_name' => 'TestSender',
        ], $client);

        $message = (new SmsMessage)->to('+2348134844186')->message('Test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to send SMS via Sendchamp');

        $driver->send($message);
    }

    public function test_send_uses_default_dnd_route(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'code' => 200,
                'status' => 'success',
                'message' => 'processing',
                'data' => ['id' => 'test', 'status' => 'processing'],
            ])),
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        $driver = new SendchampDriver([
            'api_key' => 'test-key',
            'sender_name' => 'TestSender',
        ], $client);

        $message = (new SmsMessage)->to('+2348134844186')->message('Hello');
        $driver->send($message);

        $requestBody = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertEquals('dnd', $requestBody['route']);
    }
}
