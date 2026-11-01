<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Brevo (formerly Sendinblue) transactional SMS. One request per recipient.
 * Config: api_key, sender, type (transactional|marketing). Brevo templates: Sms::template($brevoTemplateId).
 */
class BrevoDriver extends HttpDriver
{
    protected string $label = 'Brevo';

    protected function validateConfig(): void
    {
        $this->require('api_key', 'sender');
        $this->oneOf('type', ['transactional', 'marketing'], 'transactional');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $message->validate();
        $templateId = $message->getTemplateId();
        $content = $templateId === null
            ? ['content' => $this->text($message)]
            : ['templateId' => is_numeric($templateId) ? (int) $templateId : $templateId, 'params' => $message->getVariables() ?: null];

        return $this->sendEach(
            $message,
            fn (PendingRequest $request, string $to): mixed => $request
                ->withHeaders(['api-key' => $this->value('api_key')])
                ->post('https://api.brevo.com/v3/transactionalSMS/send', array_filter([
                    'sender' => $this->sender($message, 'sender'),
                    'recipient' => ltrim($to, '+'),
                    'type' => $this->oneOf('type', ['transactional', 'marketing'], 'transactional'),
                    ...$content,
                ], fn (mixed $value): bool => $value !== null)),
            function (Response $response, string $to): SentMessage {
                if (! in_array($response->status(), [200, 201], true)) {
                    $this->fail($response, 'message');
                }

                return new SentMessage($to, $this->jsonString($response, 'messageId'), 'accepted');
            },
        );
    }
}
