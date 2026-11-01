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
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Sendchamp (Nigeria). One request for all recipients. Config: api_key, sender_name, route.
 */
class SendchampDriver extends HttpDriver implements HandlesWebhooks
{
    use VerifiesWebhooks;

    protected string $label = 'Sendchamp';

    protected function validateConfig(): void
    {
        $this->require('api_key', 'sender_name');
        $this->oneOf('route', ['dnd', 'non_dnd', 'international'], 'dnd');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);

        return $this->sendOnce(
            $message,
            fn (PendingRequest $request): Response => $request
                ->withToken($this->value('api_key'))
                ->post('https://api.sendchamp.com/api/v1/sms/send', [
                    'to' => array_map(fn (string $to): string => ltrim($to, '+'), $message->getTo()),
                    'message' => $text,
                    'sender_name' => $this->sender($message, 'sender_name'),
                    'route' => $this->oneOf('route', ['dnd', 'non_dnd', 'international'], 'dnd'),
                ]),
            function (Response $response) use ($message): SmsResult {
                if ($response->status() !== 200 || $response->json('status') === 'failed') {
                    $this->fail($response, 'message');
                }

                return SmsResult::accepted($message->getTo(), $this->jsonString($response, 'data.business_uid') ?? $this->jsonString($response, 'data.id'), 'accepted');
            },
        );
    }

    public function handleWebhook(Request $request): HttpResponse
    {
        if (! $this->hasValidHmacSignature($request, 'sendchamp', 'X-Sendchamp-Signature')) {
            $this->logRejectedWebhook($request, 'sendchamp');

            return response('Unauthorized', 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        Event::dispatch(new SmsWebhookReceived(
            provider: 'sendchamp',
            payload: $payload,
            messageId: isset($payload['sms_uid']) && is_string($payload['sms_uid']) ? $payload['sms_uid'] : null,
            status: isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : null,
            recipient: isset($payload['phone_number']) && is_string($payload['phone_number']) ? $payload['phone_number'] : null,
        ));

        Log::info('Sendchamp webhook received', [
            'sms_uid' => $payload['sms_uid'] ?? null,
            'status' => $payload['status'] ?? null,
        ]);

        return response('OK', 200);
    }
}
