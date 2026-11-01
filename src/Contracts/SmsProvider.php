<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Contracts;

use MrRijal\LaravelSms\Exceptions\DeliveryException;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

interface SmsProvider
{
    /**
     * Send the message to every recipient.
     *
     * @throws DeliveryException when any recipient is rejected or unreachable
     */
    public function send(SmsMessage $message): SmsResult;
}
