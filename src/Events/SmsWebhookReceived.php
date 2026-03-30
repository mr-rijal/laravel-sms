<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Events;

/**
 * @phpstan-type WebhookPayload = array<string, mixed>
 */
readonly class SmsWebhookReceived
{
    /**
     * @param  WebhookPayload  $payload
     */
    public function __construct(
        public string $provider,
        public array $payload,
        public ?string $messageId = null,
        public ?string $status = null,
        public ?string $recipient = null,
    ) {}
}
