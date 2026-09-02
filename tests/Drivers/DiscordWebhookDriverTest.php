<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Drivers\DiscordWebhookDriver;
use MrRijal\LaravelSms\SmsMessage;
use PHPUnit\Framework\TestCase;

class DiscordWebhookDriverTest extends TestCase
{
    public function test_rejects_non_discord_webhook_urls(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DiscordWebhookDriver(['webhook_url' => 'https://example.test/webhook']);
    }

    public function test_mirrors_a_message(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(204)]));
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);
        $driver = new DiscordWebhookDriver(['webhook_url' => 'https://discord.com/api/webhooks/123/token'], $client);

        $this->assertTrue($driver->send((new SmsMessage)->to('+15559876543')->message('Debug message')));
        $payload = json_decode((string) $history[0]['request']->getBody(), true);

        $this->assertSame(['parse' => []], $payload['allowed_mentions']);
    }

    public function test_does_not_expose_webhook_credentials_when_the_request_fails(): void
    {
        $sentinel = 'discord-secret-token';
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([
            new ConnectException('Connection failed', new Request('POST', "https://discord.com/api/webhooks/123/{$sentinel}")),
        ]))]);
        $driver = new DiscordWebhookDriver(['webhook_url' => "https://discord.com/api/webhooks/123/{$sentinel}"], $client);

        try {
            $driver->send((new SmsMessage)->to('+15559876543')->message('Debug message'));
            $this->fail('Expected the request to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString($sentinel, $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }
}
