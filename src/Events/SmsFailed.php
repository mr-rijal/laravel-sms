<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Events;

use MrRijal\LaravelSms\SmsMessage;
use Throwable;

readonly class SmsFailed
{
    public function __construct(
        public SmsMessage $message,
        public string $provider,
        public Throwable $exception,
    ) {}
}
