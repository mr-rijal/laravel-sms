<?php

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class Msg91Driver implements SmsProvider
{
    protected ?Client $client = null;

    /**
     * Construct the MSG91 driver and validate required configuration.
     *
     * @param array $config Configuration array that must contain the keys 'authkey' (API key) and 'sender' (sender ID).
     * @param Client|null $client Optional Guzzle HTTP client to use for requests; when omitted a client will be created at send time.
     * @throws \InvalidArgumentException If 'authkey' or 'sender' is missing or empty in the provided configuration.
     */
    public function __construct(protected array $config, ?Client $client = null)
    {
        if (empty($config['authkey']) || empty($config['sender'])) {
            throw new \InvalidArgumentException('MSG91 configuration is incomplete');
        }
        $this->client = $client;
    }

    /**
     * Send an SMS message via MSG91 using either the template flow API or the plain-text API.
     *
     * @param SmsMessage $message The message to send; may include recipients, text, template ID, and template variables.
     * @return bool `true` on successful delivery request.
     * @throws \RuntimeException If the provider returns a non-200 response or the HTTP client fails.
    public function send(SmsMessage $message): bool
    {
        $client = $this->client ?? new Client(['timeout' => 30]);

        try {
            // If template is provided, use flow API
            if ($message->getTemplateId()) {
                $response = $client->post('https://api.msg91.com/api/v5/flow/', [
                    'headers' => [
                        'authkey' => $this->config['authkey'],
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'sender' => $this->config['sender'],
                        'template_id' => $message->getTemplateId(),
                        'recipients' => collect($message->getTo())->map(fn ($n) => [
                            'mobiles' => $n,
                            ...$message->getVariables(),
                        ])->values()->all(),
                    ],
                ]);

                if ($response->getStatusCode() !== 200) {
                    throw new \RuntimeException('Failed to send SMS via MSG91: '.$response->getBody()->getContents());
                }
            } else {
                // Use plain text SMS API
                foreach ($message->getTo() as $to) {
                    $response = $client->post('https://api.msg91.com/api/v2/sendsms', [
                        'form_params' => [
                            'authkey' => $this->config['authkey'],
                            'mobiles' => $to,
                            'message' => $message->getText(),
                            'sender' => $this->config['sender'],
                            'route' => '4', // Transactional route
                        ],
                    ]);

                    if ($response->getStatusCode() !== 200) {
                        throw new \RuntimeException('Failed to send SMS via MSG91: '.$response->getBody()->getContents());
                    }
                }
            }

            return true;
        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                "Failed to send SMS via MSG91: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}