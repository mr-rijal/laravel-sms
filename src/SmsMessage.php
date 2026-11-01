<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms;

use MrRijal\LaravelSms\Exceptions\InvalidMessageException;

class SmsMessage
{
    public const MAX_LENGTH = 1600;

    /**
     * @var list<string>
     */
    private array $to = [];

    private ?string $text = null;

    private ?string $templateId = null;

    /**
     * @var array<string, mixed>
     */
    private array $variables = [];

    private ?string $from = null;

    private ?string $provider = null;

    public static function create(?string $text = null): self
    {
        $message = new self;

        return $text === null ? $message : $message->message($text);
    }

    /**
     * Add recipient(s). Spaces, dashes, dots and parentheses are stripped before validation.
     *
     * @param  string|iterable<string>  $numbers
     *
     * @throws InvalidMessageException
     */
    public function to(string|iterable $numbers): self
    {
        foreach (is_string($numbers) ? [$numbers] : $numbers as $number) {
            $normalized = self::normalize((string) $number);

            if ($normalized === '') {
                continue;
            }

            if (! preg_match('/^\+?\d{7,15}$/', $normalized)) {
                throw new InvalidMessageException("Invalid phone number format: {$number}");
            }

            if (! in_array($normalized, $this->to, true)) {
                $this->to[] = $normalized;
            }
        }

        return $this;
    }

    /**
     * @throws InvalidMessageException
     */
    public function message(string $text): self
    {
        $text = trim($text);

        if ($text === '') {
            throw new InvalidMessageException('Message text cannot be empty');
        }

        if (mb_strlen($text) > self::MAX_LENGTH) {
            throw new InvalidMessageException('SMS message cannot exceed '.self::MAX_LENGTH.' characters');
        }

        $this->text = $text;

        return $this;
    }

    /**
     * Alias of message(), matching Laravel's notification message style.
     */
    public function content(string $text): self
    {
        return $this->message($text);
    }

    /**
     * @param  array<string, mixed>  $vars
     *
     * @throws InvalidMessageException
     */
    public function template(string $templateId, array $vars = []): self
    {
        if (trim($templateId) === '') {
            throw new InvalidMessageException('Template ID cannot be empty');
        }

        $this->templateId = trim($templateId);
        $this->variables = $vars;

        return $this;
    }

    /**
     * Override the configured sender for this message (drivers that support it).
     */
    public function from(?string $sender): self
    {
        $this->from = $sender === null || trim($sender) === '' ? null : trim($sender);

        return $this;
    }

    /**
     * Send this message through a specific provider instead of the default.
     */
    public function provider(?string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Copy of this message addressed to a single recipient.
     */
    public function forRecipient(string $number): self
    {
        $copy = clone $this;
        $copy->to = [];

        return $copy->to($number);
    }

    /**
     * Copy of this message addressed to the given recipients only.
     *
     * @param  list<string>  $numbers
     */
    public function forRecipients(array $numbers): self
    {
        $copy = clone $this;
        $copy->to = [];

        return $copy->to($numbers);
    }

    /**
     * @return list<string>
     */
    public function getTo(): array
    {
        return $this->to;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getTemplateId(): ?string
    {
        return $this->templateId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function hasRecipients(): bool
    {
        return $this->to !== [];
    }

    /**
     * Recipients with all but the last four digits masked, for logs.
     *
     * @return list<string>
     */
    public function getRedactedRecipientsForLogging(): array
    {
        return array_map(self::redact(...), $this->to);
    }

    public static function redact(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';

        return strlen($digits) < 4 ? '***' : '***'.substr($digits, -4);
    }

    /**
     * @throws InvalidMessageException
     */
    public function validate(): void
    {
        if ($this->to === []) {
            throw new InvalidMessageException('At least one recipient is required');
        }

        if ($this->text === null && $this->templateId === null) {
            throw new InvalidMessageException('Message text or template ID is required');
        }
    }

    private static function normalize(string $number): string
    {
        return preg_replace('/[\s\-.()]/', '', trim($number)) ?? '';
    }
}
