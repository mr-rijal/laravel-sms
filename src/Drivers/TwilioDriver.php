<?php

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class TwilioDriver implements SmsProvider
{
    protected Client $client;

    public function __construct(protected array $config)
    {
        $this->client = new Client([
            'base_uri' => 'https://api.twilio.com/2010-04-01/',
            'auth' => [$config['sid'], $config['token']],
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        foreach ($message->to as $to) {
            $this->client->post(
                "Accounts/{$this->config['sid']}/Messages.json",
                [
                    'form_params' => [
                        'From' => $this->config['from'],
                        'To'   => $to,
                        'Body' => $message->text,
                    ]
                ]
            );
        }

        return true;
    }
}
