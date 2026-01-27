<?php

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class VonageDriver implements SmsProvider
{
    protected Client $client;

    public function __construct(protected array $config, ?Client $client = null)
    {
        if (empty($config['key']) || empty($config['secret']) || empty($config['from'])) {
            throw new \InvalidArgumentException('Vonage configuration is incomplete');
        }

        $this->client = $client ?? new Client([
            'base_uri' => 'https://rest.nexmo.com/',
            'timeout' => 30,
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        if (empty($message->getText()) && empty($message->getTemplateId())) {
            throw new \InvalidArgumentException('Message text or template ID is required');
        }

        foreach ($message->getTo() as $to) {
            try {
                $response = $this->client->post('sms/json', [
                    'form_params' => [
                        'api_key' => $this->config['key'],
                        'api_secret' => $this->config['secret'],
                        'from' => $this->config['from'],
                        'to' => $to,
                        'text' => $message->getText() ?? '',
                    ],
                ]);

                $result = json_decode($response->getBody()->getContents(), true);

                if (! isset($result['messages'][0]['status']) || $result['messages'][0]['status'] !== '0') {
                    $errorText = $result['messages'][0]['error-text'] ?? 'Unknown error';
                    throw new \RuntimeException("Failed to send SMS via Vonage: {$errorText}");
                }
            } catch (GuzzleException $e) {
                throw new \RuntimeException(
                    "Failed to send SMS via Vonage: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return true;
    }
}
