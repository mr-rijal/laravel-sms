<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;
use RuntimeException;

class InfobipDriver implements SmsProvider
{
    protected Client $client;

    /**
     * @param  array<string, mixed>  $config  api_key, sender, optional base_url
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['api_key']) || empty($config['sender'])) {
            throw new InvalidArgumentException('Infobip configuration is incomplete. api_key and sender are required.');
        }

        $baseUrl = $config['base_url'] ?? 'https://api.infobip.com/';
        if (! is_string($baseUrl) || ! str_starts_with($baseUrl, 'https://')) {
            throw new InvalidArgumentException('Infobip base_url must use HTTPS.');
        }

        $this->client = $client ?? new Client([
            'base_uri' => rtrim($baseUrl, '/').'/',
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        $message->validate();
        $text = $message->getText();
        if ($text === null) {
            throw new InvalidArgumentException('Infobip SMS requires message text; template-only messages are not supported.');
        }

        try {
            $response = $this->client->post('sms/3/messages', [
                'headers' => [
                    'Authorization' => 'App '.(string) $this->config['api_key'],
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'messages' => [[
                        'sender' => (string) $this->config['sender'],
                        'destinations' => array_map(fn (string $to): array => ['to' => $to], $message->getTo()),
                        'content' => ['text' => $text],
                    ]],
                ],
            ]);

            $this->assertSuccessfulResponse($response->getStatusCode(), (string) $response->getBody());
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to send SMS via Infobip.', 0, $e);
        }

        return true;
    }

    private function assertSuccessfulResponse(int $statusCode, string $raw): void
    {
        /** @var mixed $body */
        $body = json_decode($raw, true);

        if ($statusCode !== 200 || ! is_array($body) || ! isset($body['messages'][0]['messageId']) || ! is_string($body['messages'][0]['messageId'])) {
            throw new RuntimeException("Failed to send SMS via Infobip (HTTP {$statusCode}).");
        }
    }
}
