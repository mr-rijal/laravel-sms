<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms;

class SmsMessage
{
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

    /**
     * Add recipient(s).
     *
     * @param  string|list<string>  $numbers  Phone number(s)
     *
     * @throws \InvalidArgumentException
     */
    public function to(string|array $numbers): self
    {
        /** @var list<string> $raw */
        $raw = is_array($numbers) ? $numbers : [$numbers];

        $validated = [];
        foreach ($raw as $number) {
            $number = trim((string) $number);
            if ($number === '') {
                continue;
            }

            // Basic phone validation (E.164-ish — allows various formats).
            if (! preg_match('/^\+?\d{7,15}$/', $number)) {
                throw new \InvalidArgumentException("Invalid phone number format: {$number}");
            }

            $validated[] = $number;
        }

        /** @var list<string> $unique */
        $unique = array_values(array_unique(array_merge($this->to, $validated)));
        $this->to = $unique;

        return $this;
    }

    /**
     * Set message text.
     *
     * @throws \InvalidArgumentException
     */
    public function message(string $text): self
    {
        $text = trim($text);

        if ($text === '') {
            throw new \InvalidArgumentException('Message text cannot be empty');
        }

        if (strlen($text) > 1600) {
            throw new \InvalidArgumentException('SMS message cannot exceed 1600 characters');
        }

        $this->text = $text;

        return $this;
    }

    /**
     * Set template ID and variables.
     *
     * @param  array<string, mixed>  $vars
     *
     * @throws \InvalidArgumentException
     */
    public function template(string $templateId, array $vars = []): self
    {
        if ($templateId === '') {
            throw new \InvalidArgumentException('Template ID cannot be empty');
        }

        $this->templateId = $templateId;
        $this->variables = $vars;

        return $this;
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

    /**
     * Recipient numbers redacted for logging (PII-safe; last 4 digits visible when possible).
     *
     * @return list<string>
     */
    public function getRedactedRecipientsForLogging(): array
    {
        return array_map(fn (string $number): string => self::redactPhoneNumber($number), $this->getTo());
    }

    /**
     * Redact a phone number for logs: masks all but the last four digits.
     */
    private static function redactPhoneNumber(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);
        if ($digits === null || $digits === '' || strlen($digits) < 4) {
            return '***';
        }

        return '***'.substr($digits, -4);
    }

    /**
     * Validate message is ready to send.
     *
     * @throws \InvalidArgumentException
     */
    public function validate(): void
    {
        if ($this->to === []) {
            throw new \InvalidArgumentException('At least one recipient is required');
        }

        if ($this->text === null && $this->templateId === null) {
            throw new \InvalidArgumentException('Message text or template ID is required');
        }
    }
}
