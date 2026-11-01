<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Events;

use MrRijal\LaravelSms\SmsMessage;

/**
 * Dispatched before a send. Return false from a listener to cancel it.
 */
readonly class SmsSending
{
    public function __construct(
        public SmsMessage $message,
        public string $provider,
    ) {}
}
