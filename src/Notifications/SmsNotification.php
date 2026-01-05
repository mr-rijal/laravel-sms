<?php

namespace MrRijal\LaravelSms\Notifications;

use Illuminate\Notifications\Notification;
use MrRijal\LaravelSms\Facades\Sms;

class SmsNotification extends Notification
{
    public function __construct(
        protected ?string $message = null,
        protected ?string $templateId = null,
        protected array $variables = []
    ) {
        if (! $message && ! $templateId) {
            throw new \InvalidArgumentException('Either message or templateId must be provided');
        }
    }

    public function via($notifiable): array
    {
        return ['sms'];
    }

    public function toSms($notifiable): void
    {
        $phoneNumber = $notifiable->routeNotificationForSms();

        if (empty($phoneNumber)) {
            throw new \InvalidArgumentException(
                'No phone number found for notifiable. Implement routeNotificationForSms() method.'
            );
        }

        $sms = Sms::to($phoneNumber);

        if ($this->templateId) {
            $sms->template($this->templateId, $this->variables);
        } else {
            $sms->message($this->message);
        }

        // Respect queue configuration
        if (config('sms.queue', false)) {
            $sms->sendLater();
        } else {
            $sms->sendNow();
        }
    }
}
