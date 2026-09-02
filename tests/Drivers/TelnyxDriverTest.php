<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Drivers\TelnyxDriver;
use MrRijal\LaravelSms\SmsMessage;
use PHPUnit\Framework\TestCase;

class TelnyxDriverTest extends TestCase
{
    public function test_requires_an_api_key_and_sending_identity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TelnyxDriver(['api_key' => 'key']);
    }

    public function test_sends_a_text_message(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['data' => ['id' => 'msg-123']])),
        ]))]);
        $driver = new TelnyxDriver(['api_key' => 'key', 'from' => '+15551234567'], $client);

        $this->assertTrue($driver->send((new SmsMessage)->to('+15559876543')->message('Hello Telnyx')));
    }
}
