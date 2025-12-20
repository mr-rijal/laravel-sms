<?php

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class Msg91Driver implements SmsProvider
{
    public function __construct(protected array $config) {}

    public function send(SmsMessage $message): bool
    {
        $client = new Client();

        $client->post('https://api.msg91.com/api/v5/flow/', [
            'headers' => [
                'authkey' => $this->config['authkey'],
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'sender' => $this->config['sender'],
                'template_id' => $message->templateId,
                'recipients' => collect($message->to)->map(fn($n) => [
                    'mobiles' => $n,
                    ...$message->variables,
                ])->values()->all(),
            ]
        ]);

        return true;
    }
}
