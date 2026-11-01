<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Drivers\TelegramMirrorDriver;
use MrRijal\LaravelSms\SmsMessage;
use PHPUnit\Framework\TestCase;

class TelegramMirrorDriverTest extends TestCase
{
    public function test_requires_a_bot_token_and_chat_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TelegramMirrorDriver(['bot_token' => 'token']);
    }

    public function test_mirrors_a_message(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['ok' => true, 'result' => ['message_id' => 1]])),
        ]))]);
        $driver = new TelegramMirrorDriver(['bot_token' => 'token', 'chat_id' => '@developers'], $client);

        $this->assertTrue($driver->send((new SmsMessage)->to('+15559876543')->message('Debug message')));
    }

    public function test_does_not_expose_bot_credentials_when_the_request_fails(): void
    {
        $sentinel = 'telegram-secret-token';
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([
            new ConnectException('Connection failed', new Request('POST', "https://api.telegram.org/bot{$sentinel}/sendMessage")),
        ]))]);
        $driver = new TelegramMirrorDriver(['bot_token' => $sentinel, 'chat_id' => '@developers'], $client);

        try {
            $driver->send((new SmsMessage)->to('+15559876543')->message('Debug message'));
            $this->fail('Expected the request to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString($sentinel, $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }
}
