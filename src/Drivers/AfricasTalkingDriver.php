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
use MrRijal\LaravelSms\Exceptions\DeliveryException;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Africa's Talking. One request for all recipients, with per-recipient status. Config: api_key, username, from, sandbox.
 */
class AfricasTalkingDriver extends HttpDriver implements HandlesWebhooks
{
    use VerifiesWebhooks;

    protected string $label = "Africa's Talking";

    protected function validateConfig(): void
    {
        $this->require('api_key', 'username');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);
        $host = $this->bool('sandbox') ? 'https://api.sandbox.africastalking.com' : 'https://api.africastalking.com';

        return $this->sendOnce(
            $message,
            fn (PendingRequest $request): Response => $request
                ->withHeaders(['apiKey' => $this->value('api_key')])
                ->asForm()
                ->post("{$host}/version1/messaging", array_filter([
                    'username' => $this->value('username'),
                    'to' => implode(',', $message->getTo()),
                    'message' => $text,
                    'from' => $this->sender($message),
                ])),
            function (Response $response) use ($message): SmsResult {
                if (! in_array($response->status(), [200, 201], true)) {
                    $this->fail($response, 'SMSMessageData.Message');
                }

                $sent = [];
                $failed = [];
                $error = null;
                $recipients = $response->json('SMSMessageData.Recipients');

                foreach (is_array($recipients) ? $recipients : [] as $recipient) {
                    if (! is_array($recipient)) {
                        continue;
                    }

                    $number = is_string($recipient['number'] ?? null) ? $recipient['number'] : null;
                    $status = is_string($recipient['status'] ?? null) ? $recipient['status'] : null;

                    if (is_numeric($recipient['statusCode'] ?? null) && (int) $recipient['statusCode'] > 399) {
                        $failed[] = $number ?? '';
                        $error ??= $status ?? 'Unknown error';

                        continue;
                    }

                    $sent[] = new SentMessage($number ?? '', is_string($recipient['messageId'] ?? null) ? $recipient['messageId'] : null, $status);
                }

                if ($error !== null) {
                    throw new DeliveryException(
                        "Failed to send SMS via Africa's Talking: {$error}",
                        $this->label,
                        array_values(array_filter($failed)) ?: $message->getTo(),
                        new SmsResult($sent),
                    );
                }

                return $sent === [] ? SmsResult::accepted($message->getTo(), status: 'accepted') : new SmsResult($sent);
            },
        );
    }

    public function handleWebhook(Request $request): HttpResponse
    {
        if (! $this->hasValidSecretToken($request, 'africastalking')) {
            $this->logRejectedWebhook($request, 'africastalking');

            return response('Unauthorized', 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->except('token');

        Event::dispatch(new SmsWebhookReceived(
            provider: 'africastalking',
            payload: $payload,
            messageId: isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : null,
            status: isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : null,
            recipient: isset($payload['phoneNumber']) && is_string($payload['phoneNumber']) ? $payload['phoneNumber'] : null,
        ));

        Log::info("Africa's Talking webhook received", [
            'id' => $payload['id'] ?? null,
            'status' => $payload['status'] ?? null,
        ]);

        return response('OK', 200);
    }
}
