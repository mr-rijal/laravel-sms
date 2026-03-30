<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Facades;

use DateTimeInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \MrRijal\LaravelSms\SmsManager provider(string $provider)
 * @method static \MrRijal\LaravelSms\SmsManager to(string|array $numbers)
 * @method static \MrRijal\LaravelSms\SmsManager message(string $text)
 * @method static \MrRijal\LaravelSms\SmsManager template(string $templateId, array $vars = [])
 * @method static bool sendNow()
 * @method static void sendLater()
 * @method static void sendLaterAt(DateTimeInterface $datetime)
 * @method static bool send()
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-sms';
    }
}
