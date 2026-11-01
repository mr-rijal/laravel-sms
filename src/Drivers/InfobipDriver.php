<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Infobip SMS API v3. One request for all recipients. Config: api_key, sender, base_url (your account's HTTPS base URL).
 */
class InfobipDriver extends HttpDriver
{
    protected string $label = 'Infobip';

    protected function validateConfig(): void
    {
        $this->require('api_key', 'sender');
        $this->httpsUrl('base_url', 'https://api.infobip.com');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);

        return $this->sendOnce(
            $message,
            fn (PendingRequest $request): Response => $request
                ->withHeaders(['Authorization' => 'App '.$this->value('api_key')])
                ->post($this->httpsUrl('base_url', 'https://api.infobip.com').'/sms/3/messages', [
                    'messages' => [[
                        'sender' => $this->sender($message, 'sender'),
                        'destinations' => array_map(fn (string $to): array => ['to' => ltrim($to, '+')], $message->getTo()),
                        'content' => ['text' => $text],
                    ]],
                ]),
            function (Response $response) use ($message): SmsResult {
                $messages = $response->json('messages');

                if (! $response->successful() || ! is_array($messages) || $messages === []) {
                    $this->fail($response, ['description', 'requestError.serviceException.text']);
                }

                return new SmsResult(array_map(function (string $to, int $i) use ($messages): SentMessage {
                    $item = is_array($messages[$i] ?? null) ? $messages[$i] : [];

                    return new SentMessage(
                        $to,
                        is_string($item['messageId'] ?? null) ? $item['messageId'] : null,
                        is_string(data_get($item, 'status.name')) ? data_get($item, 'status.name') : null,
                    );
                }, $message->getTo(), array_keys($message->getTo())));
            },
        );
    }
}
