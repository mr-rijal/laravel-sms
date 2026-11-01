<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MrRijal\LaravelSms\Exceptions\InvalidConfigurationException;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Telnyx Messaging. Config: api_key, and from or messaging_profile_id.
 */
class TelnyxDriver extends HttpDriver
{
    protected string $label = 'Telnyx';

    protected function validateConfig(): void
    {
        $this->require('api_key');

        if ($this->string('from') === null && $this->string('messaging_profile_id') === null) {
            throw new InvalidConfigurationException('Telnyx configuration is incomplete. Required: api_key and either from or messaging_profile_id');
        }
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);

        return $this->sendEach(
            $message,
            fn (PendingRequest $request, string $to): mixed => $request
                ->withToken($this->value('api_key'))
                ->post('https://api.telnyx.com/v2/messages', array_filter([
                    'to' => $to,
                    'text' => $text,
                    'type' => 'SMS',
                    'from' => $this->sender($message),
                    'messaging_profile_id' => $this->string('messaging_profile_id'),
                ])),
            function (Response $response, string $to): SentMessage {
                $id = $this->jsonString($response, 'data.id');

                if (! $response->successful() || $id === null) {
                    $this->fail($response, ['errors.0.detail', 'errors.0.title']);
                }

                return new SentMessage($to, $id, $this->jsonString($response, 'data.to.0.status'));
            },
        );
    }
}
