<?php

namespace MrRijal\LaravelSms;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Events\SmsSending;
use MrRijal\LaravelSms\Events\SmsSent;
use MrRijal\LaravelSms\Jobs\SendSmsJob;

class SmsManager
{
    use Macroable;

    protected SmsMessage $message;
    protected string $provider;
    protected array $driverCache = [];

    public function __construct()
    {
        $this->reset();
    }

    /**
     * Reset message state
     */
    protected function reset(): void
    {
        $this->message = new SmsMessage;
        $this->provider = config('sms.default', 'fake');
    }

    public function provider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

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

    public function template(string $templateId, array $vars = []): self
    {
        $this->message->template($templateId, $vars);

        return $this;
    }

    /**
     * Send SMS immediately
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function sendNow(): bool
    {
        $this->message->validate();

        Log::info('Sending SMS', [
            'provider' => $this->provider,
            'recipients' => $this->message->getTo(),
            'has_template' => ! empty($this->message->getTemplateId()),
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
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'provider' => $this->provider,
                'recipients' => $this->message->getTo(),
                'error' => $e->getMessage(),
            ]);

            Event::dispatch(new SmsSent($this->message, $this->provider, false, $e->getMessage()));

            throw $e;
        } finally {
            // Always reset state after sending
            $this->reset();
        }
    }

    /**
     * Queue SMS for later sending
     */
    public function sendLater(): void
    {
        $this->message->validate();

        // Clone message to avoid state issues
        $message = clone $this->message;
        $provider = $this->provider;

        dispatch(new SendSmsJob($message, $provider));

        Log::info('SMS queued', [
            'provider' => $provider,
            'recipients' => $message->getTo(),
        ]);

        // Reset state
        $this->reset();
    }

    public function sendMessage(SmsMessage $message, string $provider): void
    {
        $this->message = $message;
        $this->provider = $provider;
        $this->sendNow();
    }

    /**
     * Schedule SMS for later sending
     */
    public function sendLaterAt(\DateTimeInterface $datetime): void
    {
        $this->message->validate();

        // Clone message to avoid state issues
        $message = clone $this->message;
        $provider = $this->provider;

        dispatch(new SendSmsJob($message, $provider))->delay($datetime);

        Log::info('SMS scheduled', [
            'provider' => $provider,
            'recipients' => $message->getTo(),
            'scheduled_at' => $datetime->format('Y-m-d H:i:s'),
        ]);

        // Reset state
        $this->reset();
    }

    public function send(): bool
    {
        if (config('sms.queue', false)) {
            $this->sendLater();

            return true;
        }

        return $this->sendNow();
    }

    protected function pickDriver(): string
    {
        if ($this->provider === 'random') {
            $drivers = config('sms.random_drivers', []);
            if (empty($drivers)) {
                throw new \InvalidArgumentException('No drivers configured for random selection');
            }

            return $drivers[array_rand($drivers)];
        }

        return $this->provider;
    }

    /**
     * Resolve and cache driver instance
     *
     * @throws InvalidArgumentException
     */
    protected function resolveDriver(): SmsProvider
    {
        $actualProvider = $this->pickDriver();

        // Return cached driver if available
        if (isset($this->driverCache[$actualProvider])) {
            return $this->driverCache[$actualProvider];
        }

        $drivers = config('sms.drivers', []);

        if (! isset($drivers[$actualProvider])) {
            throw new InvalidArgumentException("SMS driver [{$actualProvider}] not configured.");
        }

        $driverConfig = $drivers[$actualProvider];

        // if driverConfig is a class name string
        if (is_string($driverConfig)) {
            $driver = new $driverConfig(config("sms.providers.{$actualProvider}", []));
        }
        // if driverConfig is array with 'class' key
        elseif (is_array($driverConfig) && isset($driverConfig['class'])) {
            $driver = new $driverConfig['class']($driverConfig);
        } else {
            throw new InvalidArgumentException("Driver {$actualProvider} not implemented");
        }

        // Cache the driver instance
        $this->driverCache[$actualProvider] = $driver;

        return $driver;
    }
}
