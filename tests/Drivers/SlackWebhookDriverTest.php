<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Drivers\SlackWebhookDriver;
use MrRijal\LaravelSms\SmsMessage;
use PHPUnit\Framework\TestCase;

class SlackWebhookDriverTest extends TestCase
{
    public function test_rejects_non_slack_webhook_urls(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SlackWebhookDriver(['webhook_url' => 'https://example.test/webhook']);
    }

    public function test_mirrors_a_message(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], 'ok')]))]);
        $driver = new SlackWebhookDriver(['webhook_url' => 'https://hooks.slack.com/services/team/channel/token'], $client);

        $this->assertTrue($driver->send((new SmsMessage)->to('+15559876543')->message('Debug message')));
    }

    public function test_does_not_expose_webhook_credentials_when_the_request_fails(): void
    {
        $sentinel = 'slack-secret-token';
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([
            new ConnectException('Connection failed', new Request('POST', "https://hooks.slack.com/services/team/channel/{$sentinel}")),
        ]))]);
        $driver = new SlackWebhookDriver(['webhook_url' => "https://hooks.slack.com/services/team/channel/{$sentinel}"], $client);

        try {
            $driver->send((new SmsMessage)->to('+15559876543')->message('Debug message'));
            $this->fail('Expected the request to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString($sentinel, $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }
}
