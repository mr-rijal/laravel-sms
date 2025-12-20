<?php

namespace MrRijal\LaravelSms;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use MrRijal\LaravelSms\Events\SmsSending;
use MrRijal\LaravelSms\Events\SmsSent;
use MrRijal\LaravelSms\Jobs\SendSmsJob;

class SmsManager
{
    use Macroable;

    protected SmsMessage $message;
    protected string $provider;

    public function __construct()
    {
        $this->message = new SmsMessage();
        $this->provider = config('sms.default');
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

    public function sendNow(): bool
    {
        Event::dispatch(new SmsSending($this->message, $this->provider));

        $driver = $this->resolveDriver();
        $driver->send($this->message);

        Event::dispatch(new SmsSent($this->message, $this->provider));

        return true;
    }

    public function sendLater(): void
    {
        dispatch(new SendSmsJob($this->message, $this->provider));
    }

    public function sendMessage(SmsMessage $message, string $provider): void
    {
        $this->message = $message;
        $this->provider = $provider;
        $this->sendNow();
    }
    public function sendLaterAt(\DateTimeInterface $datetime): void
    {
        dispatch(new SendSmsJob($this->message, $this->provider))
            ->delay($datetime);
    }

    protected function pickDriver(): string
    {
        if ($this->provider === 'random') {
            $drivers = config('sms.random_drivers', []);
            if (empty($drivers)) {
                throw new \InvalidArgumentException("No drivers configured for random selection");
            }
            return $drivers[array_rand($drivers)];
        }

        return $this->provider;
    }

    protected function resolveDriver()
    {
        $drivers = config('sms.drivers', []);

        if (!isset($drivers[$this->provider])) {
            throw new \InvalidArgumentException("SMS driver [{$this->provider}] not configured.");
        }

        $driverConfig = $drivers[$this->provider];

        // if driverConfig is a class name string
        if (is_string($driverConfig)) {
            return new $driverConfig(config("sms.providers.{$this->provider}", []));
        }

        // if driverConfig is array with 'class' key
        if (is_array($driverConfig) && isset($driverConfig['class'])) {
            return new $driverConfig['class']($driverConfig);
        }

        throw new \InvalidArgumentException("Driver {$this->provider} not implemented");
    }
}
