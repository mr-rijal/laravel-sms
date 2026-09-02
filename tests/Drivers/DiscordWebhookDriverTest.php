<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
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
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(204)]))]);
        $driver = new DiscordWebhookDriver(['webhook_url' => 'https://discord.com/api/webhooks/123/token'], $client);

        $this->assertTrue($driver->send((new SmsMessage)->to('+15559876543')->message('Debug message')));
    }
}
