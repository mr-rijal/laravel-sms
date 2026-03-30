<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms;

use DateTimeInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Events\SmsSending;
use MrRijal\LaravelSms\Events\SmsSent;
use MrRijal\LaravelSms\Jobs\SendSmsJob;
use Throwable;

class SmsManager
{
    use Macroable;

    /**
     * @var array<string, SmsProvider>
     */
    protected array $driverCache = [];

    protected SmsMessage $message;

    protected string $provider = '';

    public function __construct()
    {
        $this->reset();
    }

    protected function reset(): void
    {
        $this->message = new SmsMessage;
        $default = config('sms.default', 'fake');
        $this->provider = is_string($default) ? $default : 'fake';
    }

    public function provider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * @param  string|list<string>  $numbers
     */
    public function to(string|array $numbers): self
    {
        $this->message->to($numbers);

        return $this;
    }

    public function message(string $text): self
    {
        $this->message->message($text);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    public function template(string $templateId, array $vars = []): self
    {
        $this->message->template($templateId, $vars);

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function sendNow(): bool
    {
        $this->message->validate();

        Log::info('Sending SMS', [
            'provider' => $this->provider,
            'recipients' => $this->message->getTo(),
            'has_template' => $this->message->getTemplateId() !== null,
        ]);

        Event::dispatch(new SmsSending($this->message, $this->provider));

        try {
            $driver = $this->resolveDriver();
            $result = $driver->send($this->message);

            Event::dispatch(new SmsSent($this->message, $this->provider, true));

            Log::info('SMS sent successfully', [
                'provider' => $this->provider,
                'recipients' => $this->message->getTo(),
            ]);

            return $result;
        } catch (Throwable $e) {
            Log::error('SMS sending failed', [
                'provider' => $this->provider,
                'recipients' => $this->message->getTo(),
                'error' => $e->getMessage(),
            ]);

            Event::dispatch(new SmsSent($this->message, $this->provider, false, $e->getMessage()));

            throw $e;
        } finally {
            $this->reset();
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function sendLater(): void
    {
        $this->message->validate();

        $message = clone $this->message;
        $provider = $this->provider;

        dispatch(new SendSmsJob($message, $provider));

        Log::info('SMS queued', [
            'provider' => $provider,
            'recipients' => $message->getTo(),
        ]);

        $this->reset();
    }

    /**
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function sendMessage(SmsMessage $message, string $provider): void
    {
        $this->message = $message;
        $this->provider = $provider;
        $this->sendNow();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function sendLaterAt(DateTimeInterface $datetime): void
    {
        $this->message->validate();

        $message = clone $this->message;
        $provider = $this->provider;

        dispatch(new SendSmsJob($message, $provider))->delay($datetime);

        Log::info('SMS scheduled', [
            'provider' => $provider,
            'recipients' => $message->getTo(),
            'scheduled_at' => $datetime->format('Y-m-d H:i:s'),
        ]);

        $this->reset();
    }

    /**
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function send(): bool
    {
        $queue = config('sms.queue', false);

        if ($queue === true) {
            $this->sendLater();

            return true;
        }

        return $this->sendNow();
    }

    /**
     * @return list<string>
     */
    private function randomDriverNames(): array
    {
        $raw = config('sms.random_drivers', []);
        if (! is_array($raw)) {
            throw new InvalidArgumentException('sms.random_drivers must be an array.');
        }

        $names = [];
        foreach ($raw as $name) {
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return array<string, array<string, mixed>|class-string>
     */
    private function driverMap(): array
    {
        $drivers = config('sms.drivers', []);
        if (! is_array($drivers)) {
            throw new InvalidArgumentException('sms.drivers must be an array.');
        }

        /** @var array<string, array<string, mixed>|class-string> $drivers */
        return $drivers;
    }

    /**
     * @return array<string, mixed>
     */
    private function providerConfig(string $name): array
    {
        $config = config("sms.providers.{$name}", []);

        return is_array($config) ? $config : [];
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function pickDriver(): string
    {
        if ($this->provider === 'random') {
            $drivers = $this->randomDriverNames();
            if ($drivers === []) {
                throw new InvalidArgumentException('No drivers configured for random selection');
            }

            $key = array_rand($drivers);

            return $drivers[$key];
        }

        return $this->provider;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function resolveDriver(): SmsProvider
    {
        $actualProvider = $this->pickDriver();

        if (isset($this->driverCache[$actualProvider])) {
            return $this->driverCache[$actualProvider];
        }

        $drivers = $this->driverMap();

        if (! isset($drivers[$actualProvider])) {
            throw new InvalidArgumentException("SMS driver [{$actualProvider}] not configured.");
        }

        $driverConfig = $drivers[$actualProvider];

        if (is_string($driverConfig)) {
            if (! is_subclass_of($driverConfig, SmsProvider::class)) {
                throw new InvalidArgumentException("Driver class [{$driverConfig}] must implement ".SmsProvider::class.'.');
            }

            $driver = new $driverConfig($this->providerConfig($actualProvider));
        } elseif (isset($driverConfig['class']) && is_string($driverConfig['class'])) {
            $class = $driverConfig['class'];
            if (! is_subclass_of($class, SmsProvider::class)) {
                throw new InvalidArgumentException("Driver class [{$class}] must implement ".SmsProvider::class.'.');
            }

            $driver = new $class($driverConfig);
        } else {
            throw new InvalidArgumentException("Driver {$actualProvider} not implemented");
        }

        $this->driverCache[$actualProvider] = $driver;

        return $driver;
    }
}
