<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Notifications;

use Illuminate\Notifications\Notification;
use RuntimeException;

class SmsChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            throw new RuntimeException('Notification is missing toSms method');
        }

        $notification->toSms($notifiable);
    }
}
