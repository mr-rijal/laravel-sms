<?php

namespace MrRijal\LaravelSms\Drivers;

use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class VonageDriver implements SmsProvider
{
    public function __construct(protected array $config) {}

    public function send(SmsMessage $message): bool
    {
        // Implement Vonage API
        return true;
    }
}
