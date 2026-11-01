<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Contracts;

use DateInterval;
use DateTimeInterface;
use MrRijal\LaravelSms\PendingSms;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

interface Factory
{
    /**
     * @param  string|iterable<string>  $numbers
     */
    public function to(string|iterable $numbers): PendingSms;

    public function provider(string $provider): PendingSms;

    /**
     * Send now. Returns null when an SmsSending listener cancels the send.
     */
    public function sendNow(SmsMessage $message): ?SmsResult;

    /**
     * Queue one job per recipient, optionally delayed.
     */
    public function queue(SmsMessage $message, DateTimeInterface|DateInterval|int|null $delay = null): void;
}
