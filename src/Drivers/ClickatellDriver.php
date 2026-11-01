<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use MrRijal\LaravelSms\Exceptions\DeliveryException;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Clickatell One API. Up to 100 recipients per request, with per-recipient acceptance. Config: api_key, optional from.
 */
class ClickatellDriver extends HttpDriver
{
    protected string $label = 'Clickatell';

    protected function validateConfig(): void
    {
        $this->require('api_key');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $result = new SmsResult;

        foreach (array_chunk($message->getTo(), 100) as $chunk) {
            try {
                $result = $result->merge($this->sendChunk($message->forRecipients($chunk)));
            } catch (DeliveryException $e) {
                $sentSoFar = $e->partial !== null ? $result->merge($e->partial) : $result;
                $unsent = array_values(array_diff($message->getTo(), $sentSoFar->recipients()));

                throw new DeliveryException($e->getMessage(), $this->label, $unsent, $sentSoFar, $e->getCode(), $e);
            }
        }

        return $result;
    }

    private function sendChunk(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);
        $from = $this->sender($message);

        return $this->sendOnce(
            $message,
            fn (PendingRequest $request): Response => $request
                ->withHeaders(['Authorization' => $this->value('api_key')])
                ->post('https://platform.clickatell.com/v1/message', [
                    'messages' => array_map(
                        fn (string $to): array => array_filter(['channel' => 'sms', 'to' => ltrim($to, '+'), 'content' => $text, 'from' => $from]),
                        $message->getTo(),
                    ),
                ]),
            function (Response $response) use ($message): SmsResult {
                $items = $response->json('messages');

                if (! in_array($response->status(), [200, 202], true) || ! is_array($items)) {
                    $this->fail($response, ['error.description', 'messages.0.error.description']);
                }

                $sent = [];
                $failed = [];
                $error = null;

                foreach ($message->getTo() as $i => $to) {
                    $item = is_array($items[$i] ?? null) ? $items[$i] : [];

                    if (($item['accepted'] ?? false) !== true) {
                        $failed[] = $to;
                        $description = data_get($item, 'error.description');
                        $error ??= is_string($description) ? Str::limit($description, 200) : 'rejected';

                        continue;
                    }

                    $sent[] = new SentMessage($to, is_string($item['apiMessageId'] ?? null) ? $item['apiMessageId'] : null, 'accepted');
                }

                if ($error !== null) {
                    throw new DeliveryException("Failed to send SMS via Clickatell: {$error}", $this->label, $failed, new SmsResult($sent));
                }

                return new SmsResult($sent);
            },
        );
    }
}
