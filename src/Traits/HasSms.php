<?php

namespace MrRijal\LaravelSms\Traits;

use MrRijal\LaravelSms\Facades\Sms;

trait HasSms
{
    /**
     * Send plain text Sms
     */
    public function sms(string $message): bool
    {
        return Sms::to($this->routeNotificationForSms())
            ->message($message)
            ->sendNow();
    }

    /**
     * Alias (DX sugar)
     */
    public function sendSms(string $message): bool
    {
        return $this->sms($message);
    }

    /**
     * Send Sms using telecom template ID (DLT style)
     */
    public function smsTemplate(string $templateId, array $variables = []): bool
    {
        return Sms::to($this->routeNotificationForSms())
            ->template($templateId, $variables)
            ->sendNow();
    }

    public static function smsMany(iterable $users, string $message): void
    {
        foreach ($users as $user) {
            $user->sms($message);
        }
    }
}
