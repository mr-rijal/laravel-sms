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
            'to' => $message->to,
            'message' => $message->text,
            'template' => $message->templateId ?? null,
            'vars' => $message->variables ?? null,
        ];
        return true;
    }

    public static function reset()
    {
        self::$messages = [];
    }
}
