<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Traits;

use MrRijal\LaravelSms\Facades\Sms;

trait HasSms
{
    abstract public function routeNotificationForSms(): string;

    public function sms(string $message): bool
    {
        return Sms::to($this->routeNotificationForSms())
            ->message($message)
            ->sendNow();
    }

    public function sendSms(string $message): bool
    {
        return $this->sms($message);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function smsTemplate(string $templateId, array $variables = []): bool
    {
        return Sms::to($this->routeNotificationForSms())
            ->template($templateId, $variables)
            ->sendNow();
    }

    /**
     * @param  iterable<int, object&static>  $users
     */
    public static function smsMany(iterable $users, string $message): void
    {
        foreach ($users as $user) {
            $user->sms($message);
        }
    }
}
