<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Drivers\Msg91Driver;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;

class Msg91DriverTest extends TestCase
{
    public function test_implements_sms_provider(): void
    {
        $driver = new Msg91Driver(['authkey' => 'key', 'sender' => 'SENDER']);

        $this->assertInstanceOf(\MrRijal\LaravelSms\Contracts\SmsProvider::class, $driver);
    }

    public function test_constructor_throws_when_authkey_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('MSG91 configuration is incomplete');

        new Msg91Driver(['sender' => 'SENDER']);
    }

    public function test_constructor_throws_when_sender_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('MSG91 configuration is incomplete');

        new Msg91Driver(['authkey' => 'key']);
    }

    public function test_constructor_succeeds_with_valid_config(): void
    {
        $driver = new Msg91Driver(['authkey' => 'key', 'sender' => 'SENDER']);

        $this->assertInstanceOf(Msg91Driver::class, $driver);
    }

    public function test_send_plain_text_success_with_mock_client(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"type":"success"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new Msg91Driver(['authkey' => 'key', 'sender' => 'SENDER'], $client);
        $message = (new SmsMessage)->to('9812345678')->message('Hello MSG91');

        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    public function test_send_template_success_with_mock_client(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"type":"success"}'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new Msg91Driver(['authkey' => 'key', 'sender' => 'SENDER'], $client);
        $message = (new SmsMessage)->to('9812345678')->template('TPL_1', ['otp' => 123456]);

        $result = $driver->send($message);

        $this->assertTrue($result);
    }
}
