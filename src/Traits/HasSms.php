<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Traits;

use MrRijal\LaravelSms\Facades\Sms;
use MrRijal\LaravelSms\SmsResult;

/**
 * Convenience helpers for notifiable models that implement routeNotificationForSms().
 */
trait HasSms
{
    abstract public function routeNotificationForSms(): string;

    public function sms(string $message): ?SmsResult
    {
        return Sms::to($this->smsRoute())->message($message)->sendNow();
    }

    public function sendSms(string $message): ?SmsResult
    {
        return $this->sms($message);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function smsTemplate(string $templateId, array $variables = []): ?SmsResult
    {
        return Sms::to($this->smsRoute())->template($templateId, $variables)->sendNow();
    }

    protected function smsRoute(): string
    {
        return $this->routeNotificationForSms();
    }
}
