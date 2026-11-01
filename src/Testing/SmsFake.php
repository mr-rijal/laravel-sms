<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Testing;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Collection;
use MrRijal\LaravelSms\SmsManager;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Records messages instead of sending them. Enable with Sms::fake().
 */
class SmsFake extends SmsManager
{
    /**
     * @var list<SmsMessage>
     */
    protected array $sent = [];

    /**
     * @var list<array{message: SmsMessage, delay: DateTimeInterface|DateInterval|int|null}>
     */
    protected array $queued = [];

    public function sendNow(SmsMessage $message): ?SmsResult
    {
        $message->validate();

        $this->sent[] = $message;

        return SmsResult::accepted($message->getTo(), 'fake-'.count($this->sent), 'accepted')
            ->withProvider($message->getProvider() ?? $this->getDefaultDriver());
    }

    public function queue(SmsMessage $message, DateTimeInterface|DateInterval|int|null $delay = null): void
    {
        $message->validate();

        foreach ($message->getTo() as $recipient) {
            $this->queued[] = ['message' => $message->forRecipient($recipient), 'delay' => $delay];
        }
    }

    /**
     * @param  (Closure(SmsMessage): bool)|int|null  $callback  a filter, or the expected count
     */
    public function assertSent(Closure|int|null $callback = null): void
    {
        if (is_int($callback)) {
            $this->assertSentCount($callback);

            return;
        }

        PHPUnit::assertTrue($this->sent($callback)->isNotEmpty(), 'The expected SMS was not sent.');
    }

    /**
     * @param  (Closure(SmsMessage): bool)|null  $callback
     */
    public function assertSentTo(string $number, ?Closure $callback = null): void
    {
        $normalized = (new SmsMessage)->to($number)->getTo()[0];

        PHPUnit::assertTrue(
            $this->sent($callback)->contains(fn (SmsMessage $message): bool => in_array($normalized, $message->getTo(), true)),
            "No SMS was sent to [{$number}].",
        );
    }

    /**
     * @param  (Closure(SmsMessage): bool)|null  $callback
     */
    public function assertNotSent(?Closure $callback = null): void
    {
        PHPUnit::assertCount(0, $this->sent($callback), 'An unexpected SMS was sent.');
    }

    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->sent, 'SMS messages were sent unexpectedly.');
    }

    public function assertSentCount(int $count): void
    {
        PHPUnit::assertCount($count, $this->sent, "Expected {$count} SMS sent, got ".count($this->sent).'.');
    }

    /**
     * @param  (Closure(SmsMessage): bool)|int|null  $callback  a filter, or the expected count (one per recipient)
     */
    public function assertQueued(Closure|int|null $callback = null): void
    {
        if (is_int($callback)) {
            PHPUnit::assertCount($callback, $this->queued, "Expected {$callback} SMS queued, got ".count($this->queued).'.');

            return;
        }

        PHPUnit::assertTrue($this->queued($callback)->isNotEmpty(), 'The expected SMS was not queued.');
    }

    public function assertNothingQueued(): void
    {
        PHPUnit::assertEmpty($this->queued, 'SMS messages were queued unexpectedly.');
    }

    /**
     * @param  (Closure(SmsMessage): bool)|null  $callback
     * @return Collection<int, SmsMessage>
     */
    public function sent(?Closure $callback = null): Collection
    {
        return collect($this->sent)->filter($callback ?? fn (): bool => true)->values();
    }

    /**
     * @param  (Closure(SmsMessage): bool)|null  $callback
     * @return Collection<int, SmsMessage>
     */
    public function queued(?Closure $callback = null): Collection
    {
        return collect(array_column($this->queued, 'message'))->filter($callback ?? fn (): bool => true)->values();
    }
}
