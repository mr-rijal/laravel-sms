<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;
use RuntimeException;

class SparrowDriver implements SmsProvider
{
    protected ?Client $client = null;

    /**
     * @param  array<string, mixed>  $config  token, from
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['token']) || empty($config['from'])) {
            throw new InvalidArgumentException('Sparrow configuration is incomplete');
        }
        $this->client = $client;
    }

    public function send(SmsMessage $message): bool
    {
        if ($message->getText() === null && $message->getTemplateId() === null) {
            throw new InvalidArgumentException('Message text or template ID is required');
        }

        $client = $this->client ?? new Client(['timeout' => 30]);

        foreach ($message->getTo() as $to) {
            try {
                $response = $client->post('https://api.sparrowsms.com/v2/sms/', [
                    'form_params' => [
                        'token' => (string) $this->config['token'],
                        'from' => (string) $this->config['from'],
                        'to' => $to,
                        'text' => $message->getText() ?? '',
                    ],
                ]);

                if ($response->getStatusCode() !== 200) {
                    throw new RuntimeException('Failed to send SMS via Sparrow: '.$response->getBody()->getContents());
                }
            } catch (GuzzleException $e) {
                throw new RuntimeException(
                    "Failed to send SMS via Sparrow: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return true;
    }
}
