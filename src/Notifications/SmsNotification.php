<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Notifications;

use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use MrRijal\LaravelSms\SmsMessage;

/**
 * Ready-made notification: $user->notify(new SmsNotification(message: 'Hello')).
 * Add ShouldQueue in a subclass (or use your own notification) to queue it.
 */
class SmsNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        protected ?string $message = null,
        protected ?string $templateId = null,
        protected array $variables = [],
        protected ?string $provider = null,
    ) {
        if ($message === null && $templateId === null) {
            throw new InvalidArgumentException('Either message or templateId must be provided');
        }
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        $sms = (new SmsMessage)->provider($this->provider);

        return $this->templateId !== null
            ? $sms->template($this->templateId, $this->variables)
            : $sms->message((string) $this->message);
    }
}
