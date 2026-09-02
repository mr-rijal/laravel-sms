<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;
use RuntimeException;

class TelnyxDriver implements SmsProvider
{
    protected Client $client;

    /**
     * @param  array<string, mixed>  $config  api_key and either from or messaging_profile_id
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['api_key']) || (empty($config['from']) && empty($config['messaging_profile_id']))) {
            throw new InvalidArgumentException('Telnyx configuration is incomplete. api_key and either from or messaging_profile_id are required.');
        }

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.telnyx.com/v2/',
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
            throw new InvalidArgumentException('Telnyx SMS requires message text; template-only messages are not supported.');
        }

        try {
            foreach ($message->getTo() as $to) {
                $payload = ['to' => $to, 'text' => $text, 'type' => 'SMS'];

                if (! empty($this->config['from'])) {
                    $payload['from'] = (string) $this->config['from'];
                }
                if (! empty($this->config['messaging_profile_id'])) {
                    $payload['messaging_profile_id'] = (string) $this->config['messaging_profile_id'];
                }

                $response = $this->client->post('messages', [
                    'headers' => [
                        'Authorization' => 'Bearer '.(string) $this->config['api_key'],
                        'Accept' => 'application/json',
                    ],
                    'json' => $payload,
                ]);

                $this->assertSuccessfulResponse($response->getStatusCode(), (string) $response->getBody());
            }
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to send SMS via Telnyx.', 0, $e);
        }

        return true;
    }

    private function assertSuccessfulResponse(int $statusCode, string $raw): void
    {
        /** @var mixed $body */
        $body = json_decode($raw, true);

        if ($statusCode !== 200 || ! is_array($body) || ! isset($body['data']['id']) || ! is_string($body['data']['id'])) {
            throw new RuntimeException("Failed to send SMS via Telnyx (HTTP {$statusCode}).");
        }
    }
}
