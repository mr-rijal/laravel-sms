<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Container\Container;
use MrRijal\LaravelSms\Exceptions\DeliveryException;
use MrRijal\LaravelSms\Exceptions\InvalidConfigurationException;
use MrRijal\LaravelSms\Exceptions\InvalidMessageException;
use MrRijal\LaravelSms\SmsManager;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Tries each configured provider in order. Recipients accepted by an earlier provider are
 * never sent again; only the recipients that failed move on to the next provider.
 */
class FailoverDriver extends Driver
{
    protected string $label = 'Failover';

    protected function validateConfig(): void
    {
        if ($this->drivers() === []) {
            throw new InvalidConfigurationException('Failover configuration is incomplete. Required: drivers');
        }

        if (in_array('failover', $this->drivers(), true)) {
            throw new InvalidConfigurationException('Failover drivers cannot include [failover].');
        }
    }

    public function send(SmsMessage $message): SmsResult
    {
        $message->validate();

        $manager = Container::getInstance()->make(SmsManager::class);
        $result = new SmsResult;
        $pending = $message;
        $lastError = null;

        foreach ($this->drivers() as $name) {
            try {
                return $result->merge($manager->driver($name)->send($pending));
            } catch (DeliveryException $e) {
                if ($e->partial !== null) {
                    $result = $result->merge($e->partial);
                }

                $remaining = $e->failedRecipients !== [] ? $e->failedRecipients : $pending->getTo();
                $pending = $message->forRecipients($remaining);
                $lastError = $e;
            } catch (InvalidConfigurationException|InvalidMessageException $e) {
                // Misconfigured, or cannot send this message type (e.g. template-only): try the next provider.
                $lastError = $e;
            }
        }

        throw new DeliveryException(
            'All failover providers failed'.($lastError !== null ? ': '.$lastError->getMessage() : '.'),
            $this->label,
            $pending->getTo(),
            $result,
            0,
            $lastError,
        );
    }

    /**
     * @return list<string>
     */
    private function drivers(): array
    {
        $drivers = $this->config['drivers'] ?? [];

        if (! is_array($drivers)) {
            return [];
        }

        $names = array_map(fn (mixed $driver): string => is_string($driver) ? trim($driver) : '', $drivers);

        return array_values(array_filter($names, fn (string $driver): bool => $driver !== ''));
    }
}
