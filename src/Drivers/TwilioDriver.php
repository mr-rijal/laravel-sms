<?php

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class TwilioDriver implements SmsProvider
{
    protected Client $client;

    public function __construct(protected array $config, ?Client $client = null)
    {
        if (empty($config['sid']) || empty($config['token']) || empty($config['from'])) {
            throw new \InvalidArgumentException('Twilio configuration is incomplete');
        }

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.twilio.com/2010-04-01/',
            'auth' => [$config['sid'], $config['token']],
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
                $response = $this->client->post(
                    "Accounts/{$this->config['sid']}/Messages.json",
                    [
                        'form_params' => [
                            'From' => $this->config['from'],
                            'To' => $to,
                            'Body' => $message->getText() ?? '',
                        ],
                    ]
                );

                $statusCode = $response->getStatusCode();
                $body = json_decode($response->getBody()->getContents(), true);

                if ($statusCode !== 201) {
                    $errorMessage = $body['message'] ?? $response->getBody()->getContents();
                    throw new \RuntimeException(
                        "Failed to send SMS via Twilio: {$errorMessage}",
                        $statusCode
                    );
                }
            } catch (GuzzleException $e) {
                $statusCode = 0;
                if ($e instanceof RequestException && $e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                }
                throw new \RuntimeException(
                    "Failed to send SMS via Twilio: {$e->getMessage()}",
                    $statusCode,
                    $e
                );
            }
        }

        return true;
    }
}
