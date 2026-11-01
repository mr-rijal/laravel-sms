<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Bandwidth Messaging API v2. One request for all recipients (group message when more than one).
 * Config: account_id, application_id, client_id, client_secret (OAuth 2.0), from.
 */
class BandwidthDriver extends HttpDriver
{
    protected string $label = 'Bandwidth';

    protected function validateConfig(): void
    {
        $this->require('account_id', 'application_id', 'client_id', 'client_secret', 'from');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);
        $account = rawurlencode($this->value('account_id'));

        // Bandwidth treats multiple "to" numbers as one group thread, so send individually.
        return $this->sendEach(
            $message,
            fn (PendingRequest $request, string $to): mixed => $request
                ->withToken($this->token())
                ->post("https://messaging.bandwidth.com/api/v2/users/{$account}/messages", [
                    'applicationId' => $this->value('application_id'),
                    'to' => [$to],
                    'from' => $this->sender($message),
                    'text' => $text,
                ]),
            function (Response $response, string $to): SentMessage {
                if ($response->status() === 401) {
                    $this->forgetToken($this->value('client_id'));
                }

                if ($response->status() !== 202) {
                    $this->fail($response, ['description', 'message']);
                }

                return new SentMessage($to, $this->jsonString($response, 'id'), 'accepted');
            },
        );
    }

    private function token(): string
    {
        return $this->cachedToken($this->value('client_id'), fn (): Response => $this->configure(Http::createPendingRequest())
            ->withBasicAuth($this->value('client_id'), $this->value('client_secret'))
            ->asForm()
            ->post('https://api.bandwidth.com/api/v1/oauth2/token', ['grant_type' => 'client_credentials']));
    }
}
