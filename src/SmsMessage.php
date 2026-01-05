<?php

namespace MrRijal\LaravelSms;

class SmsMessage
{
    private array $to = [];
    private ?string $text = null;
    private ?string $templateId = null;
    private array $variables = [];

    /**
     * Add recipient(s)
     *
     * @param  string|array  $numbers  Phone number(s)
     *
     * @throws \InvalidArgumentException
     */
    public function to(string|array $numbers): self
    {
        $numbers = (array) $numbers;

        foreach ($numbers as $number) {
            $number = trim((string) $number);
            if (empty($number)) {
                continue;
            }

            // Basic phone validation (E.164 format - more lenient to allow various formats)
            // Allows: +1234567890, 1234567890, +0987654321, etc.
            if (! preg_match('/^\+?\d{7,15}$/', $number)) {
                throw new \InvalidArgumentException("Invalid phone number format: {$number}");
            }
        }

        $this->to = array_unique(array_merge($this->to, $numbers));

        return $this;
    }

    /**
     * Set message text
     *
     * @param  string  $text  Message content
     *
     * @throws \InvalidArgumentException
     */
    public function message(string $text): self
    {
        $text = trim($text);

        if (empty($text)) {
            throw new \InvalidArgumentException('Message text cannot be empty');
        }

        // SMS typically has a limit (1600 chars for concatenated SMS)
        if (strlen($text) > 1600) {
            throw new \InvalidArgumentException('SMS message cannot exceed 1600 characters');
        }

        $this->text = $text;

        return $this;
    }

    /**
     * Set template ID and variables
     *
     * @param  string  $templateId  Template identifier
     * @param  array  $vars  Template variables
     *
     * @throws \InvalidArgumentException
     */
    public function template(string $templateId, array $vars = []): self
    {
        if (empty($templateId)) {
            throw new \InvalidArgumentException('Template ID cannot be empty');
        }

        $this->templateId = $templateId;
        $this->variables = $vars;

        return $this;
    }

    /**
     * Get recipients
     */
    public function getTo(): array
    {
        return $this->to;
    }

    /**
     * Get message text
     */
    public function getText(): ?string
    {
        return $this->text;
    }

    /**
     * Get template ID
     */
    public function getTemplateId(): ?string
    {
        return $this->templateId;
    }

    /**
     * Get template variables
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Validate message is ready to send
     *
     * @throws \InvalidArgumentException
     */
    public function validate(): void
    {
        if (empty($this->to)) {
            throw new \InvalidArgumentException('At least one recipient is required');
        }

        if (empty($this->text) && empty($this->templateId)) {
            throw new \InvalidArgumentException('Message text or template ID is required');
        }
    }
}
