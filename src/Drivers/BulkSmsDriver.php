<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Events\SmsWebhookReceived;
use MrRijal\LaravelSms\SmsMessage;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class BulkSmsDriver implements SmsProvider
{
    protected Client $client;

    /**
     * @param  array<string, mixed>  $config  token_id, token_secret, from (optional)
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['token_id']) || empty($config['token_secret'])) {
            throw new InvalidArgumentException('BulkSMS configuration is incomplete. Required: token_id, token_secret');
        }

        $tokenId = (string) $config['token_id'];
        $tokenSecret = (string) $config['token_secret'];

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.bulksms.com/v1/',
            'auth' => [$tokenId, $tokenSecret],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        $rawText = $message->getText();
        $body = $rawText !== null ? trim($rawText) : '';

        if ($body === '') {
            throw new InvalidArgumentException('BulkSMS requires a non-empty message body.');
        }

        $from = isset($this->config['from']) && $this->config['from'] !== '' ? (string) $this->config['from'] : null;
        $recipients = $message->getTo();

        if ($recipients === []) {
            throw new InvalidArgumentException('BulkSMS requires at least one recipient.');
        }

        foreach ($recipients as $to) {
            $payload = [
                'to' => $to,
                'body' => $body,
            ];

            if ($from !== null) {
                $payload['from'] = $from;
            }

            try {
                $response = $this->client->post('messages', [
                    'json' => $payload,
                ]);

                $this->assertBulkSmsSuccess($response);
            } catch (GuzzleException $e) {
                $statusCode = 0;
                if ($e instanceof RequestException && $e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                }
                throw new RuntimeException(
                    "Failed to send SMS via BulkSMS: {$e->getMessage()}",
                    $statusCode,
                    $e
                );
            }
        }

        return true;
    }

    public function handleWebhook(Request $request): Response
    {
        $webhookConfig = config('sms.webhooks.bulksms', []);
        if (is_array($webhookConfig) && ! empty($webhookConfig['secret']) && is_string($webhookConfig['secret'])) {
            $signature = (string) $request->header('X-BulkSMS-Signature', '');
            if (! hash_equals(
                hash_hmac('sha256', $request->getContent(), $webhookConfig['secret']),
                $signature
            )) {
                Log::warning('BulkSMS webhook verification failed', [
                    'ip' => $request->ip(),
                ]);

                return response('Unauthorized', 401);
            }
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        Event::dispatch(new SmsWebhookReceived(
            provider: 'bulksms',
            payload: $payload,
            messageId: isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : null,
            status: is_array($payload['status'] ?? null) && isset($payload['status']['type']) && is_string($payload['status']['type']) ? $payload['status']['type'] : null,
            recipient: isset($payload['to']) && is_string($payload['to']) ? $payload['to'] : null,
        ));

        Log::info('BulkSMS webhook received', [
            'id' => $payload['id'] ?? null,
            'status' => is_array($payload['status'] ?? null) ? ($payload['status']['type'] ?? null) : null,
        ]);

        return response('OK', 200);
    }

    private function assertBulkSmsSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $raw = $response->getBody()->getContents();
        /** @var mixed $body */
        $body = json_decode($raw, true);

        if ($statusCode < 200 || $statusCode > 299) {
            $errorMessage = is_array($body) && isset($body['detail']) && is_string($body['detail'])
                ? $body['detail']
                : $raw;
            throw new RuntimeException(
                "Failed to send SMS via BulkSMS: {$errorMessage}",
                $statusCode
            );
        }
    }
}
