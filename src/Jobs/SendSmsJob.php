<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use MrRijal\LaravelSms\Contracts\Factory;
use MrRijal\LaravelSms\SmsMessage;
use Throwable;

/**
 * Sends one queued message. Encrypted on the queue because it carries phone numbers and content.
 */
class SendSmsJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries;

    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff;

    public function __construct(
        public SmsMessage $message,
        ?string $provider = null,
    ) {
        if ($provider !== null) {
            $this->message->provider($provider);
        }

        $tries = config('sms.retry.tries', 3);
        $this->tries = is_numeric($tries) ? max(1, (int) $tries) : 3;

        $backoff = config('sms.retry.backoff', [10, 30, 60]);
        $this->backoff = is_array($backoff) ? array_values(array_map(fn (mixed $seconds): int => is_numeric($seconds) ? (int) $seconds : 0, $backoff)) : [10, 30, 60];
    }

    public function handle(Factory $sms): void
    {
        $sms->sendNow($this->message);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SMS job failed after all retries', [
            'provider' => $this->message->getProvider(),
            'recipients' => $this->message->getRedactedRecipientsForLogging(),
            'error' => $exception->getMessage(),
        ]);
    }
}
