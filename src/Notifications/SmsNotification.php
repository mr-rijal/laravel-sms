<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Notifications;

use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use MrRijal\LaravelSms\Facades\Sms;

class SmsNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        protected ?string $message = null,
        protected ?string $templateId = null,
        protected array $variables = [],
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

    public function toSms(mixed $notifiable): void
    {
        if (! is_object($notifiable) || ! method_exists($notifiable, 'routeNotificationForSms')) {
            throw new InvalidArgumentException('Notifiable must implement routeNotificationForSms().');
        }

        $phoneNumber = $notifiable->routeNotificationForSms();

        if (! is_string($phoneNumber) || $phoneNumber === '') {
            throw new InvalidArgumentException(
                'No phone number found for notifiable. Implement routeNotificationForSms() method.'
            );
        }

        $sms = Sms::to($phoneNumber);

        if ($this->templateId !== null) {
            $sms->template($this->templateId, $this->variables);
        } else {
            $sms->message((string) $this->message);
        }

        if (config('sms.queue', false) === true) {
            $sms->sendLater();
        } else {
            $sms->sendNow();
        }
    }
}
