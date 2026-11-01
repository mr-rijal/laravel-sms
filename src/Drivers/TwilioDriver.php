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
 * Twilio Programmable Messaging. Config: sid, token, and from or messaging_service_sid.
 */
class TwilioDriver extends HttpDriver
{
    protected string $label = 'Twilio';

    protected function validateConfig(): void
    {
        $this->require('sid', 'token');

        if ($this->string('from') === null && $this->string('messaging_service_sid') === null) {
            throw new InvalidConfigurationException('Twilio configuration is incomplete. Required: from or messaging_service_sid');
        }
    }

    public function send(SmsMessage $message): SmsResult
    {
        $body = $this->text($message);
        $sid = rawurlencode($this->value('sid'));

        return $this->sendEach(
            $message,
            fn (PendingRequest $request, string $to): mixed => $request
                ->withBasicAuth($this->value('sid'), $this->value('token'))
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", array_filter([
                    'To' => $to,
                    'Body' => $body,
                    'From' => $this->sender($message),
                    'MessagingServiceSid' => $message->getFrom() === null ? $this->string('messaging_service_sid') : null,
                ])),
            function (Response $response, string $to): SentMessage {
                if ($response->status() !== 201) {
                    $this->fail($response, 'message');
                }

                return new SentMessage($to, $this->jsonString($response, 'sid'), $this->jsonString($response, 'status'));
            },
        );
    }
}
