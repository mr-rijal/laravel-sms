<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Facades;

use DateTimeInterface;
use Illuminate\Support\Facades\Facade;
use MrRijal\LaravelSms\SmsManager;

/**
 * @method static \MrRijal\LaravelSms\SmsManager provider(string $provider)
 * @method static \MrRijal\LaravelSms\SmsManager to(string|list<string> $numbers)
 * @method static \MrRijal\LaravelSms\SmsManager message(string $text)
 * @method static \MrRijal\LaravelSms\SmsManager template(string $templateId, array<string, mixed> $vars = [])
 * @method static bool sendNow()
 * @method static void sendLater()
 * @method static void sendLaterAt(DateTimeInterface $datetime)
 * @method static bool send()
 * @method static \MrRijal\LaravelSms\Contracts\SmsProvider driver(?string $name = null)
 * @method static void macro(string $name, object|callable $macro)
 * @method static bool hasMacro(string $name)
 *
 * @see SmsManager
 */
class Sms extends Facade
{
    /**
     * The manager is scoped (it holds per-message state), so never cache the resolved instance.
     */
    protected static $cached = false;

    protected static function getFacadeAccessor(): string
    {
        return 'laravel-sms';
    }
}
