<?php

namespace MrRijal\LaravelSms\Notifications;

use Illuminate\Notifications\Notification;
use MrRijal\LaravelSms\Facades\Sms;

class SmsNotification extends Notification
{
    public function __construct(
        protected string $templateId,
        protected array $variables = []
    ) {}

    public function via($notifiable): array
    {
        return ['sms'];
    }

    public function toSms($notifiable): void
    {
        Sms::to($notifiable->routeNotificationForSms())
            ->template($this->templateId, $this->variables)
            ->sendLater();
    }
}
