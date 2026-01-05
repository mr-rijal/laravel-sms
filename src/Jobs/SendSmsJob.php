<?php

namespace MrRijal\LaravelSms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use MrRijal\LaravelSms\SmsManager;
use MrRijal\LaravelSms\SmsMessage;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out
     */
    public $timeout = 30;

    /**
     * Exponential backoff delays (in seconds)
     */
    public $backoff = [10, 30, 60];

    public function __construct(
        public SmsMessage $message,
        public string $provider
    ) {}

    /**
     * Execute the job
     */
    public function handle(SmsManager $smsManager): void
    {
        try {
            Log::info('Processing queued SMS', [
                'provider' => $this->provider,
                'recipients' => $this->message->getTo(),
                'attempt' => $this->attempts(),
            ]);

            $smsManager->sendMessage($this->message, $this->provider);
        } catch (\Exception $e) {
            Log::error('SMS job failed', [
                'provider' => $this->provider,
                'recipients' => $this->message->getTo(),
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SMS job failed after all retries', [
            'provider' => $this->provider,
            'recipients' => $this->message->getTo(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Optionally dispatch a failed event
        // Event::dispatch(new SmsFailed($this->message, $this->provider, $exception));
    }
}
