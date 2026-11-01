<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Drivers\InfobipDriver;
use MrRijal\LaravelSms\SmsMessage;
use PHPUnit\Framework\TestCase;

class InfobipDriverTest extends TestCase
{
    public function test_requires_complete_configuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new InfobipDriver(['api_key' => 'key']);
    }

    public function test_rejects_a_non_https_base_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new InfobipDriver(['api_key' => 'key', 'sender' => 'SENDER', 'base_url' => 'http://example.test']);
    }

    public function test_sends_a_text_message(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['messages' => [['messageId' => 'msg-123']]])),
        ]))]);
        $driver = new InfobipDriver(['api_key' => 'key', 'sender' => 'SENDER'], $client);

        $this->assertTrue($driver->send((new SmsMessage)->to('+15559876543')->message('Hello Infobip')));
    }

    public function test_failure_includes_bounded_provider_error_detail(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(401, [], json_encode(['requestError' => ['serviceException' => ['messageId' => 'UNAUTHORIZED', 'text' => 'Invalid login details']]])),
        ])), 'http_errors' => false]);
        $driver = new InfobipDriver(['api_key' => 'key', 'sender' => 'Sender'], $client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to send SMS via Infobip (HTTP 401): Invalid login details.');

        $driver->send((new SmsMessage)->to('+15551234567')->message('Hello'));
    }
}
