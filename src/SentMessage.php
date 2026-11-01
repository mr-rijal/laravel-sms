<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms;

/**
 * One recipient accepted by a provider.
 */
final class SentMessage
{
    public function __construct(
        public readonly string $to,
        public readonly ?string $id = null,
        public readonly ?string $status = null,
    ) {}
}
