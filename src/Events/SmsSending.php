<?php

namespace MrRijal\LaravelSms\Events;

use MrRijal\LaravelSms\SmsMessage;

class SmsSending
{
    public function __construct(
        public SmsMessage $message,
        public string $provider
    ) {}
}
