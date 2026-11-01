<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Traits\Macroable;
use MrRijal\LaravelSms\Contracts\Factory;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Events\SmsFailed;
use MrRijal\LaravelSms\Events\SmsSending;
use MrRijal\LaravelSms\Events\SmsSent;
use MrRijal\LaravelSms\Exceptions\InvalidConfigurationException;
use MrRijal\LaravelSms\Jobs\SendSmsJob;
use Throwable;

class SmsManager implements Factory
{
    use Macroable;

    /**
     * @var array<string, SmsProvider>
     */
    protected array $drivers = [];

    /**
     * @var array<string, Closure(Container, array<string, mixed>): SmsProvider>
     */
    protected array $customCreators = [];

    public function __construct(
        protected Container $container,
    ) {}

    /**
     * @param  string|iterable<string>  $numbers
     */
    public function to(string|iterable $numbers): PendingSms
    {
        return $this->compose()->to($numbers);
    }

    public function provider(string $provider): PendingSms
    {
        return $this->compose()->provider($provider);
    }

    public function message(string $text): PendingSms
    {
        return $this->compose()->message($text);
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    public function template(string $templateId, array $vars = []): PendingSms
    {
        return $this->compose()->template($templateId, $vars);
    }

    public function compose(?SmsMessage $message = null): PendingSms
    {
        return new PendingSms($this, $message);
    }

    /**
     * Send immediately, or queue when `sms.queue` is enabled (then returns null).
     */
    public function send(SmsMessage $message): ?SmsResult
    {
        if (filter_var(config('sms.queue', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->queue($message);

            return null;
        }

        return $this->sendNow($message);
    }

    public function sendNow(SmsMessage $message): ?SmsResult
    {
        $message->validate();

        $provider = $this->resolveProviderName($message->getProvider());

        if ($this->events()->until(new SmsSending($message, $provider)) === false) {
            return null;
        }

        try {
            $result = $this->driver($provider)->send($message)->withProvider($provider);
        } catch (Throwable $e) {
            Log::error('SMS sending failed', [
                'provider' => $provider,
                'recipients' => $message->getRedactedRecipientsForLogging(),
                'error' => $e->getMessage(),
            ]);

            $this->events()->dispatch(new SmsFailed($message, $provider, $e));

            throw $e;
        }

        // The provider has accepted the message: a failing listener must not make the caller
        // (or a queued job) think the send failed and send it again.
        try {
            $this->events()->dispatch(new SmsSent($message, $provider, $result));
        } catch (Throwable $e) {
            report($e);
        }

        return $result;
    }

    /**
     * Queue one job per recipient, so a retry only resends to the recipient that failed.
     */
    public function queue(SmsMessage $message, DateTimeInterface|DateInterval|int|null $delay = null): void
    {
        $message->validate();

        foreach ($message->getTo() as $recipient) {
            $job = new SendSmsJob($message->forRecipient($recipient));

            $connection = config('sms.queue_connection');
            if (is_string($connection) && $connection !== '') {
                $job->onConnection($connection);
            }

            $queue = config('sms.queue_name');
            if (is_string($queue) && $queue !== '') {
                $job->onQueue($queue);
            }

            if ($delay !== null) {
                $job->delay($delay);
            }

            dispatch($job);
        }
    }

    /**
     * Resolve a driver instance by name (the default provider when null).
     *
     * @throws InvalidConfigurationException
     */
    public function driver(?string $name = null): SmsProvider
    {
        $name = $this->resolveProviderName($name);

        return $this->drivers[$name] ??= $this->createDriver($name);
    }

    /**
     * Register a custom driver: Sms::extend('acme', fn ($app, array $config) => new AcmeDriver($config)).
     *
     * @param  Closure(Container, array<string, mixed>): SmsProvider  $callback
     */
    public function extend(string $name, Closure $callback): static
    {
        $this->customCreators[$name] = $callback;
        unset($this->drivers[$name]);

        return $this;
    }

    /**
     * Copy custom driver registrations from another manager (used by Sms::fake()).
     */
    public function inheritCustomCreators(self $manager): static
    {
        $this->customCreators = [...$manager->customCreators, ...$this->customCreators];

        return $this;
    }

    public function getDefaultDriver(): string
    {
        $default = config('sms.default', 'log');

        return is_string($default) && $default !== '' ? $default : 'log';
    }

    /**
     * Drop cached driver instances (e.g. after changing config at runtime).
     */
    public function forgetDrivers(): static
    {
        $this->drivers = [];

        return $this;
    }

    /**
     * @throws InvalidConfigurationException
     */
    protected function resolveProviderName(?string $name): string
    {
        $name ??= $this->getDefaultDriver();

        if ($name !== 'random') {
            return $name;
        }

        $pool = array_values(array_filter((array) config('sms.random_drivers', []), fn (mixed $driver): bool => is_string($driver) && $driver !== ''));

        if ($pool === []) {
            throw new InvalidConfigurationException('No drivers configured for random selection');
        }

        /** @var list<string> $pool */
        return $pool[array_rand($pool)];
    }

    /**
     * @throws InvalidConfigurationException
     */
    protected function createDriver(string $name): SmsProvider
    {
        $config = config("sms.providers.{$name}", []);
        /** @var array<string, mixed> $config */
        $config = is_array($config) ? $config : [];

        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->container, $config);
        }

        $definition = config("sms.drivers.{$name}");

        if (is_array($definition) && isset($definition['class'])) {
            /** @var array<string, mixed> $definition */
            $config = array_merge($config, $definition);
            $definition = $definition['class'];
        }

        if (! is_string($definition)) {
            throw new InvalidConfigurationException("SMS driver [{$name}] not configured.");
        }

        if (! is_subclass_of($definition, SmsProvider::class)) {
            throw new InvalidConfigurationException("Driver class [{$definition}] must implement ".SmsProvider::class.'.');
        }

        return new $definition($config);
    }

    protected function events(): Dispatcher
    {
        return $this->container->make(Dispatcher::class);
    }
}
