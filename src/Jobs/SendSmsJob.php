<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use MrRijal\LaravelSms\SmsManager;
use MrRijal\LaravelSms\SmsMessage;
use Throwable;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public SmsMessage $message,
        public string $provider,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(SmsManager $smsManager): void
    {
        try {
            Log::info('Processing queued SMS', [
                'provider' => $this->provider,
                'recipients' => $this->message->getRedactedRecipientsForLogging(),
                'template_id' => $this->message->getTemplateId(),
                'attempt' => $this->attempts(),
            ]);

            $smsManager->sendMessage($this->message, $this->provider);
        } catch (Throwable $e) {
            Log::error('SMS job failed', [
                'provider' => $this->provider,
                'recipients' => $this->message->getRedactedRecipientsForLogging(),
                'template_id' => $this->message->getTemplateId(),
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SMS job failed after all retries', [
            'provider' => $this->provider,
            'recipients' => $this->message->getRedactedRecipientsForLogging(),
            'template_id' => $this->message->getTemplateId(),
            'error' => $exception->getMessage(),
        ]);
    }
}
