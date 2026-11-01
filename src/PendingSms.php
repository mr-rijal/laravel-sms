<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms;

use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Traits\Conditionable;
use MrRijal\LaravelSms\Contracts\Factory;

/**
 * Fluent builder returned by Sms::to(), Sms::provider(), etc. Each call starts a fresh message,
 * so no state is shared between sends.
 */
class PendingSms
{
    use Conditionable;

    protected SmsMessage $message;

    public function __construct(
        protected Factory $factory,
        ?SmsMessage $message = null,
    ) {
        $this->message = $message ?? new SmsMessage;
    }

    public function provider(string $provider): static
    {
        $this->message->provider($provider);

        return $this;
    }

    /**
     * @param  string|iterable<string>  $numbers
     */
    public function to(string|iterable $numbers): static
    {
        $this->message->to($numbers);

        return $this;
    }

    public function message(string $text): static
    {
        $this->message->message($text);

        return $this;
    }

    public function content(string $text): static
    {
        return $this->message($text);
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    public function template(string $templateId, array $vars = []): static
    {
        $this->message->template($templateId, $vars);

        return $this;
    }

    public function from(?string $sender): static
    {
        $this->message->from($sender);

        return $this;
    }

    public function getMessage(): SmsMessage
    {
        return $this->message;
    }

    /**
     * Send now, or queue when `sms.queue` is enabled (then returns null).
     */
    public function send(): ?SmsResult
    {
        if (filter_var(config('sms.queue', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->sendLater();

            return null;
        }

        return $this->sendNow();
    }

    public function sendNow(): ?SmsResult
    {
        return $this->factory->sendNow(clone $this->message);
    }

    public function sendLater(): void
    {
        $this->factory->queue(clone $this->message);
    }

    public function sendLaterAt(DateTimeInterface|DateInterval|int $delay): void
    {
        $this->factory->queue(clone $this->message, $delay);
    }

    public function later(DateTimeInterface|DateInterval|int $delay): void
    {
        $this->sendLaterAt($delay);
    }
}
