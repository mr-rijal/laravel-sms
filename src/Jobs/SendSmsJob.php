<?php

namespace MrRijal\LaravelSms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use MrRijal\LaravelSms\SmsManager;
use MrRijal\LaravelSms\SmsMessage;


class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public SmsMessage $message,
        public string $provider
    ) {}

    public function handle()
    {
        $driver = app(SmsManager::class)->provider($this->provider)->resolveDriver();
        $driver->send($this->message);
    }
}
