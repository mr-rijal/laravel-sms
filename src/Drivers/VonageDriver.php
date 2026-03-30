<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;
use RuntimeException;

class VonageDriver implements SmsProvider
{
    protected Client $client;

    /**
     * @param  array<string, mixed>  $config  key, secret, from
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['key']) || empty($config['secret']) || empty($config['from'])) {
            throw new InvalidArgumentException('Vonage configuration is incomplete');
        }

        $this->client = $client ?? new Client([
            'base_uri' => 'https://rest.nexmo.com/',
            'timeout' => 30,
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        if ($message->getText() === null && $message->getTemplateId() === null) {
            throw new InvalidArgumentException('Message text or template ID is required');
        }

        foreach ($message->getTo() as $to) {
            try {
                $response = $this->client->post('sms/json', [
                    'form_params' => [
                        'api_key' => (string) $this->config['key'],
                        'api_secret' => (string) $this->config['secret'],
                        'from' => (string) $this->config['from'],
                        'to' => $to,
                        'text' => $message->getText() ?? '',
                    ],
                ]);

                $raw = $response->getBody()->getContents();
                /** @var mixed $result */
                $result = json_decode($raw, true);

                if (! is_array($result) || ! isset($result['messages']) || ! is_array($result['messages'])) {
                    throw new RuntimeException('Failed to send SMS via Vonage: Invalid API response');
                }

                $first = $result['messages'][0] ?? null;
                if (! is_array($first) || ($first['status'] ?? null) !== '0') {
                    $errorText = is_array($first) && isset($first['error-text']) && is_string($first['error-text'])
                        ? $first['error-text']
                        : 'Unknown error';
                    throw new RuntimeException("Failed to send SMS via Vonage: {$errorText}");
                }
            } catch (GuzzleException $e) {
                throw new RuntimeException(
                    "Failed to send SMS via Vonage: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return true;
    }
}
