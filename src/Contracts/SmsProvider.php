<?php

namespace MrRijal\LaravelSms\Contracts;

use MrRijal\LaravelSms\SmsMessage;

interface SmsProvider
{
    public function send(SmsMessage $message): bool;
}
