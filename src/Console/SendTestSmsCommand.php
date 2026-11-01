<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Console;

use Illuminate\Console\Command;
use MrRijal\LaravelSms\Contracts\Factory;
use MrRijal\LaravelSms\SmsMessage;
use Throwable;

class SendTestSmsCommand extends Command
{
    protected $signature = 'sms:test
        {to : Recipient phone number in E.164 format}
        {--provider= : Provider to use (defaults to sms.default)}
        {--message=This is a test message from Laravel SMS. : Message text}';

    protected $description = 'Send a test SMS to check provider credentials';

    public function handle(Factory $sms): int
    {
        $to = $this->argument('to');
        $provider = $this->option('provider');
        $text = $this->option('message');

        try {
            $result = $sms->sendNow(
                (new SmsMessage)
                    ->to(is_string($to) ? $to : '')
                    ->message(is_string($text) ? $text : '')
                    ->provider(is_string($provider) && $provider !== '' ? $provider : null),
            );
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result === null) {
            $this->components->warn('The send was cancelled by an SmsSending listener.');

            return self::FAILURE;
        }

        $this->components->info("Sent via [{$result->provider}]".($result->firstId() !== null ? " (message ID {$result->firstId()})" : '').'.');

        return self::SUCCESS;
    }
}
