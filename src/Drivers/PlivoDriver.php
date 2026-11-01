<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Plivo. One request for all recipients. Config: auth_id, auth_token, from, optional log.
 */
class PlivoDriver extends HttpDriver
{
    protected string $label = 'Plivo';

    protected function validateConfig(): void
    {
        $this->require('auth_id', 'auth_token', 'from');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);
        $authId = rawurlencode($this->value('auth_id'));

        return $this->sendOnce(
            $message,
            fn (PendingRequest $request): Response => $request
                ->withBasicAuth($this->value('auth_id'), $this->value('auth_token'))
                ->post("https://api.plivo.com/v1/Account/{$authId}/Message/", [
                    'src' => $this->sender($message),
                    'dst' => implode('<', $message->getTo()),
                    'text' => $text,
                    'type' => 'sms',
                    'log' => $this->bool('log'),
                ]),
            function (Response $response) use ($message): SmsResult {
                $ids = $response->json('message_uuid');

                if ($response->status() !== 202 || ! is_array($ids)) {
                    $this->fail($response, 'error');
                }

                return new SmsResult(array_map(
                    fn (string $to, int $i): SentMessage => new SentMessage($to, is_string($ids[$i] ?? null) ? $ids[$i] : null, 'queued'),
                    $message->getTo(),
                    array_keys($message->getTo()),
                ));
            },
        );
    }
}
