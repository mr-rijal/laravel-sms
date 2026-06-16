<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Drivers\BulkSmsDriver;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;

class BulkSmsDriverTest extends TestCase
{
    public function test_implements_sms_provider(): void
    {
        $driver = new BulkSmsDriver([
            'token_id' => 'test-id',
            'token_secret' => 'test-secret',
        ]);

        $this->assertInstanceOf(SmsProvider::class, $driver);
    }

    public function test_constructor_throws_when_token_id_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BulkSMS configuration is incomplete');

        new BulkSmsDriver([
            'token_secret' => 'test-secret',
        ]);
    }

    public function test_constructor_throws_when_token_secret_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BulkSMS configuration is incomplete');

        new BulkSmsDriver([
            'token_id' => 'test-id',
        ]);
    }

    public function test_constructor_succeeds_with_valid_config(): void
    {
        $driver = new BulkSmsDriver([
            'token_id' => 'test-id',
            'token_secret' => 'test-secret',
        ]);

        $this->assertInstanceOf(BulkSmsDriver::class, $driver);
    }

    public function test_send_throws_when_no_body_text(): void
    {
        $driver = new BulkSmsDriver([
            'token_id' => 'test-id',
            'token_secret' => 'test-secret',
        ]);
        $message = (new SmsMessage)->to('+2348134844186');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BulkSMS requires a non-empty message body');

        $driver->send($message);
    }

    public function test_send_success_with_mock_client(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode([
                'id' => '12345',
                'type' => 'SENT',
                'to' => '+2348134844186',
                'body' => 'Hello BulkSMS',
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new BulkSmsDriver([
            'token_id' => 'test-id',
            'token_secret' => 'test-secret',
        ], $client);

        $message = (new SmsMessage)->to('+2348134844186')->message('Hello BulkSMS');
        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    public function test_send_includes_from_when_configured(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['id' => '123'])),
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        $driver = new BulkSmsDriver([
            'token_id' => 'test-id',
            'token_secret' => 'test-secret',
            'from' => 'MyBrand',
        ], $client);

        $message = (new SmsMessage)->to('+2348134844186')->message('Hello');
        $driver->send($message);

        $requestBody = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertEquals('MyBrand', $requestBody['from']);
    }

    public function test_send_omits_from_when_not_configured(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['id' => '123'])),
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        $driver = new BulkSmsDriver([
            'token_id' => 'test-id',
            'token_secret' => 'test-secret',
        ], $client);

        $message = (new SmsMessage)->to('+2348134844186')->message('Hello');
        $driver->send($message);

        $requestBody = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayNotHasKey('from', $requestBody);
    }

    public function test_send_throws_on_failed_response(): void
    {
        $mock = new MockHandler([
            new Response(400, [], json_encode([
                'detail' => 'Invalid credentials',
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new BulkSmsDriver([
            'token_id' => 'bad-id',
            'token_secret' => 'bad-secret',
        ], $client);

        $message = (new SmsMessage)->to('+2348134844186')->message('Test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to send SMS via BulkSMS');

        $driver->send($message);
    }
}
