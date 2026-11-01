<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Notifications;

use Illuminate\Notifications\Notification;
use MrRijal\LaravelSms\Contracts\Factory;
use MrRijal\LaravelSms\Exceptions\InvalidMessageException;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;
use RuntimeException;

/**
 * The `sms` notification channel. Notifications implement toSms($notifiable) returning an
 * SmsMessage (or a string); notifiables route via routeNotificationForSms().
 */
class SmsChannel
{
    public function __construct(
        protected Factory $sms,
    ) {}

    public function send(object $notifiable, Notification $notification): ?SmsResult
    {
        if (! method_exists($notification, 'toSms')) {
            throw new RuntimeException('Notification is missing toSms method');
        }

        $message = $notification->toSms($notifiable);

        if (is_string($message)) {
            $message = SmsMessage::create($message);
        }

        if (! $message instanceof SmsMessage) {
            throw new InvalidMessageException('toSms() must return an '.SmsMessage::class.' or a string.');
        }

        if (! $message->hasRecipients()) {
            $route = method_exists($notifiable, 'routeNotificationFor')
                ? $notifiable->routeNotificationFor('sms', $notification)
                : null;

            if (! is_string($route) && ! is_array($route)) {
                return null;
            }

            /** @var string|list<string> $route */
            // Clone, so a message object reused across notifiables never accumulates their numbers.
            $message = (clone $message)->to($route);

            if ($message->getTo() === []) {
                return null;
            }
        }

        return $this->sms->sendNow($message);
    }
}
