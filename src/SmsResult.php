<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms;

use Countable;

/**
 * The outcome of a send: which recipients the provider accepted, with their message IDs.
 */
final class SmsResult implements Countable
{
    /**
     * @param  list<SentMessage>  $messages
     */
    public function __construct(
        public readonly array $messages = [],
        public readonly string $provider = '',
    ) {}

    /**
     * @param  list<string>  $recipients
     */
    public static function accepted(array $recipients, ?string $id = null, ?string $status = null): self
    {
        return new self(array_map(fn (string $to): SentMessage => new SentMessage($to, $id, $status), $recipients));
    }

    public function withProvider(string $provider): self
    {
        return new self($this->messages, $provider);
    }

    public function merge(self $other): self
    {
        return new self([...$this->messages, ...$other->messages], $this->provider ?: $other->provider);
    }

    /**
     * @return list<string>
     */
    public function recipients(): array
    {
        return array_map(fn (SentMessage $message): string => $message->to, $this->messages);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (SentMessage $message): ?string => $message->id, $this->messages),
            fn (?string $id): bool => $id !== null,
        )));
    }

    public function firstId(): ?string
    {
        return $this->ids()[0] ?? null;
    }

    public function count(): int
    {
        return count($this->messages);
    }
}
