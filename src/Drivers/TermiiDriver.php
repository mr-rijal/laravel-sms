<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use MrRijal\LaravelSms\Concerns\VerifiesWebhooks;
use MrRijal\LaravelSms\Contracts\HandlesWebhooks;
use MrRijal\LaravelSms\Events\SmsWebhookReceived;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Termii (Nigeria). Config: api_key, sender_id, channel (generic|dnd|whatsapp), optional base_url.
 */
class TermiiDriver extends HttpDriver implements HandlesWebhooks
{
    use VerifiesWebhooks;

    protected string $label = 'Termii';

    protected function validateConfig(): void
    {
        $this->require('api_key', 'sender_id');
        $this->oneOf('channel', ['generic', 'dnd', 'whatsapp'], 'generic');
        $this->httpsUrl('base_url', 'https://api.ng.termii.com');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);

        return $this->sendEach(
            $message,
            fn (PendingRequest $request, string $to): mixed => $request->post($this->httpsUrl('base_url', 'https://api.ng.termii.com').'/api/sms/send', [
                'to' => ltrim($to, '+'),
                'from' => $this->sender($message, 'sender_id'),
                'sms' => $text,
                'type' => 'plain',
                'channel' => $this->oneOf('channel', ['generic', 'dnd', 'whatsapp'], 'generic'),
                // Termii authenticates with the API key in the request body.
                'api_key' => $this->value('api_key'),
            ]),
            function (Response $response, string $to): SentMessage {
                $code = $response->json('code');

                if ($response->status() !== 200 || ($code !== null && $code !== 'ok')) {
                    $this->fail($response, 'message');
                }

                return new SentMessage($to, $this->jsonString($response, 'message_id'), 'accepted');
            },
        );
    }

    public function handleWebhook(Request $request): HttpResponse
    {
        if (! $this->hasValidHmacSignature($request, 'termii', 'X-Termii-Signature', 'sha512')) {
            $this->logRejectedWebhook($request, 'termii');

            return response('Unauthorized', 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        Event::dispatch(new SmsWebhookReceived(
            provider: 'termii',
            payload: $payload,
            messageId: isset($payload['message_id']) && is_string($payload['message_id']) ? $payload['message_id'] : null,
            status: isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : null,
            recipient: isset($payload['receiver']) && is_string($payload['receiver'])
                ? $payload['receiver']
                : (isset($payload['to']) && is_string($payload['to']) ? $payload['to'] : null),
        ));

        Log::info('Termii webhook received', [
            'message_id' => $payload['message_id'] ?? null,
            'status' => $payload['status'] ?? null,
        ]);

        return response('OK', 200);
    }
}
