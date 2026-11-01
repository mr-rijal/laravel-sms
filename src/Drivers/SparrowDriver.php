<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Sparrow SMS (Nepal). One request for all recipients. Config: token, from.
 */
class SparrowDriver extends HttpDriver
{
    protected string $label = 'Sparrow';

    protected function validateConfig(): void
    {
        $this->require('token', 'from');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);

        return $this->sendOnce(
            $message,
            fn (PendingRequest $request): Response => $request->asForm()->post('https://api.sparrowsms.com/v2/sms/', [
                'token' => $this->value('token'),
                'from' => $this->sender($message),
                'to' => implode(',', array_map(fn (string $to): string => ltrim($to, '+'), $message->getTo())),
                'text' => $text,
            ]),
            function (Response $response) use ($message): SmsResult {
                if (! $response->successful() || ($this->jsonString($response, 'response_code') ?? '200') !== '200') {
                    $this->fail($response, 'response');
                }

                return SmsResult::accepted($message->getTo(), $this->jsonString($response, 'message_id'), 'accepted');
            },
        );
    }
}
