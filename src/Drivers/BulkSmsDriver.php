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
 * BulkSMS.com. One request for all recipients. Config: token_id, token_secret, optional from.
 */
class BulkSmsDriver extends HttpDriver implements HandlesWebhooks
{
    use VerifiesWebhooks;

    protected string $label = 'BulkSMS';

    protected function validateConfig(): void
    {
        $this->require('token_id', 'token_secret');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);
        $from = $this->sender($message);

        return $this->sendOnce(
            $message,
            fn (PendingRequest $request): Response => $request
                ->withBasicAuth($this->value('token_id'), $this->value('token_secret'))
                ->post('https://api.bulksms.com/v1/messages', array_map(
                    fn (string $to): array => array_filter(['to' => $to, 'body' => $text, 'from' => $from]),
                    $message->getTo(),
                )),
            function (Response $response) use ($message): SmsResult {
                if (! $response->successful()) {
                    $this->fail($response, 'detail');
                }

                $items = $response->json();

                return new SmsResult(array_map(function (string $to, int $i) use ($items): SentMessage {
                    $item = is_array($items) && is_array($items[$i] ?? null) ? $items[$i] : [];

                    return new SentMessage(
                        $to,
                        is_string($item['id'] ?? null) ? $item['id'] : null,
                        is_string(data_get($item, 'status.type')) ? data_get($item, 'status.type') : 'accepted',
                    );
                }, $message->getTo(), array_keys($message->getTo())));
            },
        );
    }

    public function handleWebhook(Request $request): HttpResponse
    {
        if (! $this->hasValidHmacSignature($request, 'bulksms', 'X-BulkSMS-Signature')) {
            $this->logRejectedWebhook($request, 'bulksms');

            return response('Unauthorized', 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $status = is_array($payload['status'] ?? null) && isset($payload['status']['type']) && is_string($payload['status']['type'])
            ? $payload['status']['type']
            : null;

        Event::dispatch(new SmsWebhookReceived(
            provider: 'bulksms',
            payload: $payload,
            messageId: isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : null,
            status: $status,
            recipient: isset($payload['to']) && is_string($payload['to']) ? $payload['to'] : null,
        ));

        Log::info('BulkSMS webhook received', [
            'id' => $payload['id'] ?? null,
            'status' => $status,
        ]);

        return response('OK', 200);
    }
}
