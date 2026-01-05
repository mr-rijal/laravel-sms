<?php

namespace MrRijal\LaravelSms\Notifications;

use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            throw new \RuntimeException('Notification is missing toSms method');
        }

        // The toSms method handles sending internally
        $notification->toSms($notifiable);
    }
}
