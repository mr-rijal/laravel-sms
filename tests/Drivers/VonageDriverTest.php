<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Drivers\VonageDriver;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;

class VonageDriverTest extends TestCase
{
    public function test_implements_sms_provider(): void
    {
        $driver = new VonageDriver([
            'key' => 'key',
            'secret' => 'secret',
            'from' => 'SENDER',
        ]);

        $this->assertInstanceOf(\MrRijal\LaravelSms\Contracts\SmsProvider::class, $driver);
    }

    public function test_constructor_throws_when_key_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Vonage configuration is incomplete');

        new VonageDriver(['secret' => 'secret', 'from' => 'SENDER']);
    }

    public function test_constructor_throws_when_secret_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Vonage configuration is incomplete');

        new VonageDriver(['key' => 'key', 'from' => 'SENDER']);
    }

    public function test_constructor_throws_when_from_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Vonage configuration is incomplete');

        new VonageDriver(['key' => 'key', 'secret' => 'secret']);
    }

    public function test_constructor_succeeds_with_valid_config(): void
    {
        $driver = new VonageDriver([
            'key' => 'key',
            'secret' => 'secret',
            'from' => 'SENDER',
        ]);

        $this->assertInstanceOf(VonageDriver::class, $driver);
    }

    public function test_send_throws_when_no_text_or_template(): void
    {
        $driver = new VonageDriver([
            'key' => 'key',
            'secret' => 'secret',
            'from' => 'SENDER',
        ]);
        $message = (new SmsMessage)->to('1234567890');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message text or template ID is required');

        $driver->send($message);
    }

    public function test_send_success_with_mock_client(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'messages' => [['status' => '0', 'message-id' => 'msg-123']],
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new VonageDriver([
            'key' => 'key',
            'secret' => 'secret',
            'from' => 'SENDER',
        ], $client);
        $message = (new SmsMessage)->to('1234567890')->message('Hello Vonage');

        $result = $driver->send($message);

        $this->assertTrue($result);
    }
}
