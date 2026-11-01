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

        $context = [
            'provider' => 'log',
            'recipients' => $message->getRedactedRecipientsForLogging(),
            'message' => $message->getText(),
            'template_id' => $message->getTemplateId(),
            'variables' => $message->getVariables(),
        ];

        $channel = $this->config['channel'] ?? null;

        if (is_string($channel) && $channel !== '') {
            Log::channel($channel)->debug('SMS captured by log driver', $context);
        } else {
            Log::debug('SMS captured by log driver', $context);
        }

        return true;
    }
}
