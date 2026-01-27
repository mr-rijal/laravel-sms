<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Drivers\SparrowDriver;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;

class SparrowDriverTest extends TestCase
{
    public function test_implements_sms_provider(): void
    {
        $driver = new SparrowDriver(['token' => 'token', 'from' => 'SENDER']);

        $this->assertInstanceOf(\MrRijal\LaravelSms\Contracts\SmsProvider::class, $driver);
    }

    public function test_constructor_throws_when_token_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sparrow configuration is incomplete');

        new SparrowDriver(['from' => 'SENDER']);
    }

    public function test_constructor_throws_when_from_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sparrow configuration is incomplete');

        new SparrowDriver(['token' => 'token']);
    }

    public function test_constructor_succeeds_with_valid_config(): void
    {
        $driver = new SparrowDriver(['token' => 'token', 'from' => 'SENDER']);

        $this->assertInstanceOf(SparrowDriver::class, $driver);
    }

    public function test_send_throws_when_no_text_or_template(): void
    {
        $driver = new SparrowDriver(['token' => 'token', 'from' => 'SENDER']);
        $message = (new SmsMessage)->to('9812345678');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message text or template ID is required');

        $driver->send($message);
    }

    public function test_send_success_with_mock_client(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"status":"success"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new SparrowDriver(['token' => 'token', 'from' => 'SENDER'], $client);
        $message = (new SmsMessage)->to('9812345678')->message('Hello Sparrow');

        $result = $driver->send($message);

        $this->assertTrue($result);
    }
}
