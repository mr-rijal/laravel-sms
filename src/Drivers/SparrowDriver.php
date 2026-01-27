<?php

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class SparrowDriver implements SmsProvider
{
    protected ?Client $client = null;

    /**
     * Create a SparrowDriver with the given configuration and optional HTTP client.
     *
     * @param array $config Configuration array that must include non-empty 'token' and 'from' keys.
     * @param \GuzzleHttp\Client|null $client Optional Guzzle HTTP client to use for requests; when omitted a client will be created when sending.
     * @throws \InvalidArgumentException If the 'token' or 'from' configuration is missing or empty.
     */
    public function __construct(protected array $config, ?Client $client = null)
    {
        if (empty($config['token']) || empty($config['from'])) {
            throw new \InvalidArgumentException('Sparrow configuration is incomplete');
        }
        $this->client = $client;
    }

    /**
     * Send an SMS to every recipient in the given SmsMessage via the Sparrow SMS API.
     *
     * @param SmsMessage $message Message containing recipients and content; must include text or a template ID.
     * @return bool `true` if all messages were sent successfully.
     * @throws \InvalidArgumentException If the message has neither text nor a template ID.
     * @throws \RuntimeException If an HTTP request fails or Sparrow returns a non-200 response.
     */
    public function send(SmsMessage $message): bool
    {
        if (empty($message->getText()) && empty($message->getTemplateId())) {
            throw new \InvalidArgumentException('Message text or template ID is required');
        }

        $client = $this->client ?? new Client(['timeout' => 30]);

        foreach ($message->getTo() as $to) {
            try {
                $response = $client->post('https://api.sparrowsms.com/v2/sms/', [
                    'form_params' => [
                        'token' => $this->config['token'],
                        'from' => $this->config['from'],
                        'to' => $to,
                        'text' => $message->getText() ?? '',
                    ],
                ]);

                if ($response->getStatusCode() !== 200) {
                    throw new \RuntimeException('Failed to send SMS via Sparrow: '.$response->getBody()->getContents());
                }
            } catch (GuzzleException $e) {
                throw new \RuntimeException(
                    "Failed to send SMS via Sparrow: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return true;
    }
}