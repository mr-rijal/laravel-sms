<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Exceptions\InvalidConfigurationException;
use MrRijal\LaravelSms\Exceptions\InvalidMessageException;
use MrRijal\LaravelSms\SmsMessage;

/**
 * Base driver with typed, validated access to the provider's config array.
 */
abstract class Driver implements SmsProvider
{
    /**
     * Human-readable provider name used in exception messages.
     */
    protected string $label = 'SMS provider';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config = [],
    ) {
        $this->validateConfig();
    }

    /**
     * Throw InvalidConfigurationException when required settings are missing or invalid.
     */
    protected function validateConfig(): void {}

    /**
     * @throws InvalidConfigurationException
     */
    protected function require(string ...$keys): void
    {
        $missing = array_values(array_filter($keys, fn (string $key): bool => $this->string($key) === null));

        if ($missing !== []) {
            throw new InvalidConfigurationException("{$this->label} configuration is incomplete. Required: ".implode(', ', $keys));
        }
    }

    /**
     * A non-empty, trimmed string config value, or the default.
     */
    protected function string(string $key, ?string $default = null): ?string
    {
        $value = $this->config[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    /**
     * A config value known to be present (validated in validateConfig()).
     */
    protected function value(string $key): string
    {
        return $this->string($key) ?? throw new InvalidConfigurationException("{$this->label} configuration is missing [{$key}].");
    }

    protected function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->config[$key] ?? $default, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  list<string>  $allowed
     *
     * @throws InvalidConfigurationException
     */
    protected function oneOf(string $key, array $allowed, string $default): string
    {
        $value = $this->string($key, $default) ?? $default;

        if (! in_array($value, $allowed, true)) {
            throw new InvalidConfigurationException("{$this->label} {$key} must be one of: ".implode(', ', $allowed).'.');
        }

        return $value;
    }

    /**
     * @throws InvalidConfigurationException
     */
    protected function httpsUrl(string $key, string $default): string
    {
        $url = $this->string($key, $default) ?? $default;

        if (! str_starts_with($url, 'https://')) {
            throw new InvalidConfigurationException("{$this->label} {$key} must use HTTPS.");
        }

        return rtrim($url, '/');
    }

    /**
     * The message text; for providers that cannot send template-only messages.
     *
     * @throws InvalidMessageException
     */
    protected function text(SmsMessage $message): string
    {
        $message->validate();

        return $message->getText()
            ?? throw new InvalidMessageException("{$this->label} requires message text; template-only messages are not supported.");
    }

    /**
     * The per-message sender override, falling back to the configured sender.
     */
    protected function sender(SmsMessage $message, string $key = 'from'): ?string
    {
        return $message->getFrom() ?? $this->string($key);
    }
}
