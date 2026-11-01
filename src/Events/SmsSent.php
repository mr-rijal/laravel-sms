<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Events;

use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

readonly class SmsSent
{
    public function __construct(
        public SmsMessage $message,
        public string $provider,
        public SmsResult $result,
    ) {}
}
