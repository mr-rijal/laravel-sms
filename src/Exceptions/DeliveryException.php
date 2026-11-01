<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Exceptions;

use MrRijal\LaravelSms\SmsResult;
use RuntimeException;
use Throwable;

/**
 * A provider rejected or could not be reached for one or more recipients.
 *
 * Recipients the provider did accept are kept in $partial, so callers (and the
 * failover driver) can retry only the recipients in $failedRecipients.
 */
class DeliveryException extends RuntimeException implements SmsException
{
    /**
     * @param  list<string>  $failedRecipients
     */
    public function __construct(
        string $message,
        public readonly string $provider = '',
        public readonly array $failedRecipients = [],
        public readonly ?SmsResult $partial = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function hasPartialDelivery(): bool
    {
        return $this->partial !== null && count($this->partial) > 0;
    }
}
