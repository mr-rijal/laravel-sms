<?php

namespace MrRijal\LaravelSms\Events;

class SmsWebhookReceived
{
    public function __construct(
        public string $provider,
        public array $payload,
        public ?string $messageId = null,
        public ?string $status = null,
        public ?string $recipient = null
    ) {}
}
