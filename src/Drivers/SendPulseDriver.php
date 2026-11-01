<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * SendPulse SMS. One request for all recipients. Config: client_id, client_secret (OAuth 2.0), sender.
 */
class SendPulseDriver extends HttpDriver
{
    protected string $label = 'SendPulse';

    protected function validateConfig(): void
    {
        $this->require('client_id', 'client_secret', 'sender');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);

        return $this->sendOnce(
            $message,
            fn (PendingRequest $request): Response => $request
                ->withToken($this->token())
                ->post('https://api.sendpulse.com/sms/send', [
                    'sender' => $this->sender($message, 'sender'),
                    'phones' => array_map(fn (string $to): string => ltrim($to, '+'), $message->getTo()),
                    'body' => $text,
                ]),
            function (Response $response) use ($message): SmsResult {
                if ($response->status() === 401) {
                    $this->forgetToken($this->value('client_id'));
                }

                if (! $response->successful() || $response->json('result') !== true) {
                    $this->fail($response, ['message', 'error']);
                }

                return SmsResult::accepted($message->getTo(), $this->jsonString($response, 'campaign_id'), 'accepted');
            },
        );
    }

    private function token(): string
    {
        return $this->cachedToken($this->value('client_id'), fn (): Response => $this->configure(Http::createPendingRequest())
            ->post('https://api.sendpulse.com/oauth/access_token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->value('client_id'),
                'client_secret' => $this->value('client_secret'),
            ]));
    }
}
