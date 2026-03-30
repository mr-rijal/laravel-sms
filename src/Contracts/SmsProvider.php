<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Contracts;

use MrRijal\LaravelSms\SmsMessage;

interface SmsProvider
{
    public function send(SmsMessage $message): bool;
}
