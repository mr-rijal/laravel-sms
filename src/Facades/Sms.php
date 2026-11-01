<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Facades;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use MrRijal\LaravelSms\SmsManager;
use MrRijal\LaravelSms\Testing\SmsFake;

/**
 * @method static \MrRijal\LaravelSms\PendingSms to(string|iterable<string> $numbers)
 * @method static \MrRijal\LaravelSms\PendingSms provider(string $provider)
 * @method static \MrRijal\LaravelSms\PendingSms message(string $text)
 * @method static \MrRijal\LaravelSms\PendingSms template(string $templateId, array<string, mixed> $vars = [])
 * @method static \MrRijal\LaravelSms\PendingSms compose(\MrRijal\LaravelSms\SmsMessage|null $message = null)
 * @method static \MrRijal\LaravelSms\SmsResult|null send(\MrRijal\LaravelSms\SmsMessage $message)
 * @method static \MrRijal\LaravelSms\SmsResult|null sendNow(\MrRijal\LaravelSms\SmsMessage $message)
 * @method static void queue(\MrRijal\LaravelSms\SmsMessage $message, \DateTimeInterface|\DateInterval|int|null $delay = null)
 * @method static \MrRijal\LaravelSms\Contracts\SmsProvider driver(string|null $name = null)
 * @method static \MrRijal\LaravelSms\SmsManager extend(string $name, \Closure $callback)
 * @method static string getDefaultDriver()
 * @method static \MrRijal\LaravelSms\SmsManager forgetDrivers()
 * @method static void macro(string $name, object|callable $macro)
 * @method static bool hasMacro(string $name)
 * @method static void assertSent(\Closure|int|null $callback = null)
 * @method static void assertSentTo(string $number, \Closure|null $callback = null)
 * @method static void assertNotSent(\Closure|null $callback = null)
 * @method static void assertNothingSent()
 * @method static void assertSentCount(int $count)
 * @method static void assertQueued(\Closure|int|null $callback = null)
 * @method static void assertNothingQueued()
 *
 * @see SmsManager
 * @see SmsFake
 */
class Sms extends Facade
{
    /**
     * Replace the SMS manager with a fake that records messages instead of sending them.
     */
    public static function fake(): SmsFake
    {
        $app = static::getFacadeApplication();
        $fake = new SmsFake($app instanceof \Illuminate\Contracts\Container\Container ? $app : Container::getInstance());

        $current = static::getFacadeRoot();
        if ($current instanceof SmsManager) {
            $fake->inheritCustomCreators($current);
        }

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'laravel-sms';
    }
}
