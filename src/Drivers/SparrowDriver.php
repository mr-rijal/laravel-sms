<?php

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class SparrowDriver implements SmsProvider
{
    protected ?Client $client = null;

    public function __construct(protected array $config, ?Client $client = null)
    {
        if (empty($config['token']) || empty($config['from'])) {
            throw new \InvalidArgumentException('Sparrow configuration is incomplete');
        }
        $this->client = $client;
    }

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
