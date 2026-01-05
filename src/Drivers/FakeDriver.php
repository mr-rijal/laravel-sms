<?php

namespace MrRijal\LaravelSms\Drivers;

use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class FakeDriver implements SmsProvider
{
    public static array $messages = [];

    public function __construct(array $config = [])
    {
        // optional config
    }

    public function send(SmsMessage $message): bool
    {
        self::$messages[] = [
            'to' => $message->getTo(),
            'message' => $message->getText(),
            'template' => $message->getTemplateId(),
            'vars' => $message->getVariables(),
        ];

        return true;
    }

    public static function reset()
    {
        self::$messages = [];
    }
}
