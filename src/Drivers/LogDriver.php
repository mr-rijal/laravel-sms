<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Support\Facades\Log;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class LogDriver implements SmsProvider
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config = []) {}

    public function send(SmsMessage $message): bool
    {
        $message->validate();

        Log::debug('SMS captured by log driver', [
            'provider' => 'log',
            'recipients' => $message->getRedactedRecipientsForLogging(),
            'message' => $message->getText(),
            'template_id' => $message->getTemplateId(),
            'variables' => $message->getVariables(),
        ]);

        return true;
    }
}
