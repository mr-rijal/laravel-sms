<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Records messages in memory without any network call. Prefer Sms::fake() in tests.
 */
class FakeDriver extends Driver
{
    protected string $label = 'Fake';

    /**
     * @var list<array{to: list<string>, message: ?string, template: ?string, vars: array<string, mixed>, from: ?string}>
     */
    public static array $messages = [];

    public function send(SmsMessage $message): SmsResult
    {
        $message->validate();

        self::$messages[] = [
            'to' => $message->getTo(),
            'message' => $message->getText(),
            'template' => $message->getTemplateId(),
            'vars' => $message->getVariables(),
            'from' => $message->getFrom(),
        ];

        return SmsResult::accepted($message->getTo(), 'fake-'.count(self::$messages), 'accepted');
    }

    public static function reset(): void
    {
        self::$messages = [];
    }
}
