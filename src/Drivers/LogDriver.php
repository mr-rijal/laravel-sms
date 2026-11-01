<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Writes messages to the log instead of sending them. Development only: content may be sensitive.
 */
class LogDriver extends Driver
{
    protected string $label = 'Log';

    public function send(SmsMessage $message): SmsResult
    {
        $message->validate();

        $context = [
            'provider' => 'log',
            'recipients' => $message->getRedactedRecipientsForLogging(),
            'message' => $message->getText(),
            'template_id' => $message->getTemplateId(),
            'variables' => $message->getVariables(),
        ];

        $channel = $this->string('channel');

        if ($channel !== null) {
            Log::channel($channel)->debug('SMS captured by log driver', $context);
        } else {
            Log::debug('SMS captured by log driver', $context);
        }

        return SmsResult::accepted($message->getTo(), 'log-'.Str::uuid()->toString(), 'logged');
    }
}
