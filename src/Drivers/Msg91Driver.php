<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;
use RuntimeException;

class Msg91Driver implements SmsProvider
{
    protected ?Client $client = null;

    /**
     * @param  array<string, mixed>  $config  authkey, sender
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['authkey']) || empty($config['sender'])) {
            throw new InvalidArgumentException('MSG91 configuration is incomplete');
        }
        $this->client = $client;
    }

    public function send(SmsMessage $message): bool
    {
        $client = $this->client ?? new Client(['timeout' => 30]);

        try {
            if ($message->getTemplateId() !== null) {
                $response = $client->post('https://api.msg91.com/api/v5/flow/', [
                    'headers' => [
                        'authkey' => (string) $this->config['authkey'],
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'sender' => (string) $this->config['sender'],
                        'template_id' => $message->getTemplateId(),
                        'recipients' => collect($message->getTo())->map(fn (string $n): array => [
                            'mobiles' => $n,
                            ...$message->getVariables(),
                        ])->values()->all(),
                    ],
                ]);

                if ($response->getStatusCode() !== 200) {
                    throw new RuntimeException('Failed to send SMS via MSG91: '.$response->getBody()->getContents());
                }
            } else {
                foreach ($message->getTo() as $to) {
                    $response = $client->post('https://api.msg91.com/api/v2/sendsms', [
                        'form_params' => [
                            'authkey' => (string) $this->config['authkey'],
                            'mobiles' => $to,
                            'message' => $message->getText() ?? '',
                            'sender' => (string) $this->config['sender'],
                            'route' => '4',
                        ],
                    ]);

                    if ($response->getStatusCode() !== 200) {
                        throw new RuntimeException('Failed to send SMS via MSG91: '.$response->getBody()->getContents());
                    }
                }
            }

            return true;
        } catch (GuzzleException $e) {
            throw new RuntimeException(
                "Failed to send SMS via MSG91: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
