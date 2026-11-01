<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;
use RuntimeException;

class PlivoDriver implements SmsProvider
{
    protected Client $client;

    /**
     * @param  array<string, mixed>  $config  auth_id, auth_token, from, optional log
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['auth_id']) || empty($config['auth_token']) || empty($config['from'])) {
            throw new InvalidArgumentException('Plivo configuration is incomplete. auth_id, auth_token, and from are required.');
        }

        $authId = (string) $config['auth_id'];
        $this->client = $client ?? new Client([
            'base_uri' => "https://api.plivo.com/v1/Account/{$authId}/",
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
            throw new InvalidArgumentException('Plivo SMS requires message text; template-only messages are not supported.');
        }

        try {
            $response = $this->client->post('Message/', [
                'auth' => [(string) $this->config['auth_id'], (string) $this->config['auth_token']],
                'json' => [
                    'src' => (string) $this->config['from'],
                    'dst' => implode('<', $message->getTo()),
                    'text' => $text,
                    'type' => 'sms',
                    'log' => $this->config['log'] ?? false,
                ],
            ]);

            $this->assertSuccessfulResponse($response->getStatusCode(), (string) $response->getBody());
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to send SMS via Plivo.', 0, $e);
        }

        return true;
    }

    private function assertSuccessfulResponse(int $statusCode, string $raw): void
    {
        /** @var mixed $body */
        $body = json_decode($raw, true);

        if ($statusCode !== 202 || ! is_array($body) || ! isset($body['message_uuid']) || ! is_array($body['message_uuid'])) {
            throw new RuntimeException("Failed to send SMS via Plivo (HTTP {$statusCode}).");
        }
    }
}
