<?php

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class SparrowDriver implements SmsProvider
{
    public function __construct(protected array $config) {}

    public function send(SmsMessage $message): bool
    {
        $client = new Client();

        foreach ($message->to as $to) {
            $client->post('https://api.sparrowsms.com/v2/sms/', [
                'form_params' => [
                    'token' => $this->config['token'],
                    'from'  => $this->config['from'],
                    'to'    => $to,
                    'text'  => $message->text ?? '',
                ],
            ]);
        }

        return true;
    }
}
