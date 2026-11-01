<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Drivers\PlivoDriver;
use MrRijal\LaravelSms\SmsMessage;
use PHPUnit\Framework\TestCase;

class PlivoDriverTest extends TestCase
{
    public function test_requires_complete_configuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PlivoDriver(['auth_id' => 'id', 'auth_token' => 'token']);
    }

    public function test_sends_a_text_message(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(202, [], json_encode(['message_uuid' => ['msg-123']])),
        ]))]);
        $driver = new PlivoDriver(['auth_id' => 'id', 'auth_token' => 'token', 'from' => '+15551234567'], $client);

        $this->assertTrue($driver->send((new SmsMessage)->to('+15559876543')->message('Hello Plivo')));
    }

    public function test_failure_includes_bounded_provider_error_detail(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(400, [], json_encode(['api_id' => 'x', 'error' => 'src parameter not present'])),
        ])), 'http_errors' => false]);
        $driver = new PlivoDriver(['auth_id' => 'id', 'auth_token' => 'token', 'from' => '+15550000000'], $client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to send SMS via Plivo (HTTP 400): src parameter not present.');

        $driver->send((new SmsMessage)->to('+15551234567')->message('Hello'));
    }
}
