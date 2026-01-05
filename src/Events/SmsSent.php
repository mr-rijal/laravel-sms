<?php

namespace MrRijal\LaravelSms\Events;

use MrRijal\LaravelSms\SmsMessage;

class SmsSent
{
    public function __construct(
        public SmsMessage $message,
        public string $provider,
        public bool $success = true,
        public ?string $error = null
    ) {}
}
