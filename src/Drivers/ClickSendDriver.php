<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MrRijal\LaravelSms\Exceptions\DeliveryException;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * ClickSend v3. One request for all recipients, with per-recipient status. Config: username, api_key, optional from.
 */
class ClickSendDriver extends HttpDriver
{
    protected string $label = 'ClickSend';

    protected function validateConfig(): void
    {
        $this->require('username', 'api_key');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);
        $from = $this->sender($message);

        return $this->sendOnce(
            $message,
            fn (PendingRequest $request): Response => $request
                ->withBasicAuth($this->value('username'), $this->value('api_key'))
                ->post('https://rest.clicksend.com/v3/sms/send', [
                    'messages' => array_map(
                        fn (string $to): array => array_filter(['source' => 'laravel-sms', 'to' => $to, 'body' => $text, 'from' => $from]),
                        $message->getTo(),
                    ),
                ]),
            function (Response $response) use ($message): SmsResult {
                $items = $response->json('data.messages');

                if (! $response->successful() || $response->json('response_code') !== 'SUCCESS' || ! is_array($items)) {
                    $this->fail($response, 'response_msg');
                }

                $sent = [];
                $failed = [];
                $error = null;

                foreach ($message->getTo() as $i => $to) {
                    $item = is_array($items[$i] ?? null) ? $items[$i] : [];
                    $status = is_string($item['status'] ?? null) ? $item['status'] : null;

                    if ($status !== 'SUCCESS') {
                        $failed[] = $to;
                        $error ??= $status ?? 'Unknown error';

                        continue;
                    }

                    $sent[] = new SentMessage($to, is_string($item['message_id'] ?? null) ? $item['message_id'] : null, $status);
                }

                if ($error !== null) {
                    throw new DeliveryException("Failed to send SMS via ClickSend: {$error}", $this->label, $failed, new SmsResult($sent));
                }

                return new SmsResult($sent);
            },
        );
    }
}
