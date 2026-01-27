<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Drivers\TwilioDriver;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;

class TwilioDriverTest extends TestCase
{
    public function test_implements_sms_provider(): void
    {
        $driver = new TwilioDriver([
            'sid' => 'AC123',
            'token' => 'secret',
            'from' => '+15551234567',
        ]);

        $this->assertInstanceOf(\MrRijal\LaravelSms\Contracts\SmsProvider::class, $driver);
    }

    public function test_constructor_throws_when_sid_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Twilio configuration is incomplete');

        new TwilioDriver([
            'token' => 'secret',
            'from' => '+15551234567',
        ]);
    }

    public function test_constructor_throws_when_token_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Twilio configuration is incomplete');

        new TwilioDriver([
            'sid' => 'AC123',
            'from' => '+15551234567',
        ]);
    }

    public function test_constructor_throws_when_from_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Twilio configuration is incomplete');

        new TwilioDriver([
            'sid' => 'AC123',
            'token' => 'secret',
        ]);
    }

    public function test_constructor_succeeds_with_valid_config(): void
    {
        $driver = new TwilioDriver([
            'sid' => 'AC123',
            'token' => 'secret',
            'from' => '+15551234567',
        ]);

        $this->assertInstanceOf(TwilioDriver::class, $driver);
    }

    public function test_send_throws_when_no_text_or_template(): void
    {
        $driver = new TwilioDriver([
            'sid' => 'AC123',
            'token' => 'secret',
            'from' => '+15551234567',
        ]);
        $message = (new SmsMessage)->to('+15559876543');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message text or template ID is required');

        $driver->send($message);
    }

    public function test_send_success_with_mock_client(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['sid' => 'SM123'])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new TwilioDriver([
            'sid' => 'AC123',
            'token' => 'secret',
            'from' => '+15551234567',
        ], $client);

        $message = (new SmsMessage)->to('+15559876543')->message('Hello Twilio');
        $result = $driver->send($message);

        $this->assertTrue($result);
    }
}
