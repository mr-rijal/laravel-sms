<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Drivers\TermiiDriver;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;

class TermiiDriverTest extends TestCase
{
    public function test_implements_sms_provider(): void
    {
        $driver = new TermiiDriver([
            'api_key' => 'test-key',
            'sender_id' => 'TestSender',
        ]);

        $this->assertInstanceOf(SmsProvider::class, $driver);
    }

    public function test_constructor_throws_when_api_key_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Termii configuration is incomplete');

        new TermiiDriver([
            'sender_id' => 'TestSender',
        ]);
    }

    public function test_constructor_throws_when_sender_id_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Termii configuration is incomplete');

        new TermiiDriver([
            'api_key' => 'test-key',
        ]);
    }

    public function test_constructor_succeeds_with_valid_config(): void
    {
        $driver = new TermiiDriver([
            'api_key' => 'test-key',
            'sender_id' => 'TestSender',
        ]);

        $this->assertInstanceOf(TermiiDriver::class, $driver);
    }

    public function test_send_throws_when_no_body_text(): void
    {
        $driver = new TermiiDriver([
            'api_key' => 'test-key',
            'sender_id' => 'TestSender',
        ]);
        $message = (new SmsMessage)->to('+2348134844186');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Termii SMS requires a non-empty message body');

        $driver->send($message);
    }

    public function test_send_success_with_mock_client(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'code' => 'ok',
                'message_id' => 'msg-123',
                'message' => 'Successfully Sent',
                'balance' => 100,
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new TermiiDriver([
            'api_key' => 'test-key',
            'sender_id' => 'TestSender',
            'channel' => 'generic',
        ], $client);

        $message = (new SmsMessage)->to('+2348134844186')->message('Hello Termii');
        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    public function test_send_includes_api_key_in_payload(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'code' => 'ok',
                'message' => 'Successfully Sent',
            ])),
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        $driver = new TermiiDriver([
            'api_key' => 'my-secret-key',
            'sender_id' => 'TestSender',
        ], $client);

        $message = (new SmsMessage)->to('+2348134844186')->message('Hello');
        $driver->send($message);

        $requestBody = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertEquals('my-secret-key', $requestBody['api_key']);
        $this->assertEquals('TestSender', $requestBody['from']);
        $this->assertEquals('generic', $requestBody['channel']);
        $this->assertEquals('plain', $requestBody['type']);
    }

    public function test_send_throws_on_failed_response(): void
    {
        $mock = new MockHandler([
            new Response(400, [], json_encode([
                'message' => 'Invalid API key',
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new TermiiDriver([
            'api_key' => 'bad-key',
            'sender_id' => 'TestSender',
        ], $client);

        $message = (new SmsMessage)->to('+2348134844186')->message('Test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to send SMS via Termii');

        $driver->send($message);
    }
}
