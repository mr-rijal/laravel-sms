<?php

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class WhatsAppDriver implements SmsProvider
{
    protected Client $client;

    public function __construct(protected array $config)
    {
        // TODO: Initialize WhatsApp API client
    }

    public function send(SmsMessage $message): bool
    {
        // TODO: Implement WhatsApp message sending logic
        return true;
    }
}

