<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Drivers\AfricasTalkingDriver;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;

class AfricasTalkingDriverTest extends TestCase
{
    public function test_implements_sms_provider(): void
    {
        $driver = new AfricasTalkingDriver([
            'api_key' => 'test-key',
            'username' => 'sandbox',
        ]);

        $this->assertInstanceOf(SmsProvider::class, $driver);
    }

    public function test_constructor_throws_when_api_key_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Africa's Talking configuration is incomplete");

        new AfricasTalkingDriver([
            'username' => 'sandbox',
        ]);
    }

    public function test_constructor_throws_when_username_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Africa's Talking configuration is incomplete");

        new AfricasTalkingDriver([
            'api_key' => 'test-key',
        ]);
    }

    public function test_constructor_succeeds_with_valid_config(): void
    {
        $driver = new AfricasTalkingDriver([
            'api_key' => 'test-key',
            'username' => 'sandbox',
        ]);

        $this->assertInstanceOf(AfricasTalkingDriver::class, $driver);
    }

    public function test_send_throws_when_no_body_text(): void
    {
        $driver = new AfricasTalkingDriver([
            'api_key' => 'test-key',
            'username' => 'sandbox',
        ]);
        $message = (new SmsMessage)->to('+254711123456');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Africa's Talking SMS requires a non-empty message body");

        $driver->send($message);
    }

    public function test_send_success_with_mock_client(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode([
                'SMSMessageData' => [
                    'Message' => 'Sent to 1/1 Total Cost: KES 0.8000',
                    'Recipients' => [
                        [
                            'statusCode' => 101,
                            'number' => '+254711123456',
                            'cost' => 'KES 0.8000',
                            'status' => 'Success',
                            'messageId' => 'ATXid_123',
                        ],
                    ],
                ],
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new AfricasTalkingDriver([
            'api_key' => 'test-key',
            'username' => 'sandbox',
        ], $client);

        $message = (new SmsMessage)->to('+254711123456')->message("Hello Africa's Talking");
        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    public function test_send_uses_comma_separated_recipients(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode([
                'SMSMessageData' => [
                    'Message' => 'Sent to 2/2',
                    'Recipients' => [
                        ['statusCode' => 101, 'status' => 'Success'],
                        ['statusCode' => 101, 'status' => 'Success'],
                    ],
                ],
            ])),
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        $driver = new AfricasTalkingDriver([
            'api_key' => 'test-key',
            'username' => 'testuser',
        ], $client);

        $message = (new SmsMessage)->to(['+254711123456', '+254722123456'])->message('Hello');
        $driver->send($message);

        $requestBody = (string) $history[0]['request']->getBody();
        parse_str($requestBody, $parsed);
        $this->assertStringContainsString('+254711123456', $parsed['to']);
        $this->assertStringContainsString('+254722123456', $parsed['to']);
        $this->assertEquals('testuser', $parsed['username']);
    }

    public function test_send_throws_on_recipient_failure(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode([
                'SMSMessageData' => [
                    'Message' => 'Sent to 0/1',
                    'Recipients' => [
                        [
                            'statusCode' => 403,
                            'status' => 'InsufficientBalance',
                            'number' => '+254711123456',
                        ],
                    ],
                ],
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new AfricasTalkingDriver([
            'api_key' => 'test-key',
            'username' => 'sandbox',
        ], $client);

        $message = (new SmsMessage)->to('+254711123456')->message('Test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to send SMS via Africa's Talking");

        $driver->send($message);
    }

    public function test_send_throws_on_http_error(): void
    {
        $mock = new MockHandler([
            new Response(401, [], json_encode([
                'SMSMessageData' => [
                    'Message' => 'Invalid API key',
                ],
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new AfricasTalkingDriver([
            'api_key' => 'bad-key',
            'username' => 'sandbox',
        ], $client);

        $message = (new SmsMessage)->to('+254711123456')->message('Test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to send SMS via Africa's Talking");

        $driver->send($message);
    }
}
