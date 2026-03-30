<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class FakeDriver implements SmsProvider
{
    /**
     * @var list<array{to: list<string>, message: ?string, template: ?string, vars: array<string, mixed>}>
     */
    public static array $messages = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        // Intentionally unused — matches driver constructor signature used by SmsManager.
        unset($config);
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

    public static function reset(): void
    {
        self::$messages = [];
    }
}
