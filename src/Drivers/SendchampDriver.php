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

class SendchampDriver implements SmsProvider
{
    protected Client $client;

    /**
     * @param  array<string, mixed>  $config  api_key, sender_name, route
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['api_key']) || empty($config['sender_name'])) {
            throw new InvalidArgumentException('Sendchamp configuration is incomplete. Required: api_key, sender_name');
        }

        $apiKey = (string) $config['api_key'];

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.sendchamp.com/api/v1/',
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
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
            throw new InvalidArgumentException('Sendchamp SMS requires a non-empty message body.');
        }

        $route = (string) ($this->config['route'] ?? 'dnd');
        $senderName = (string) $this->config['sender_name'];
        $recipients = $message->getTo();

        try {
            $response = $this->client->post('sms/send', [
                'json' => [
                    'to' => array_map(fn (string $number) => ltrim($number, '+'), $recipients),
                    'message' => $body,
                    'sender_name' => $senderName,
                    'route' => $route,
                ],
            ]);

            $this->assertSendchampSuccess($response);
        } catch (GuzzleException $e) {
            $statusCode = 0;
            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
            }
            throw new RuntimeException(
                "Failed to send SMS via Sendchamp: {$e->getMessage()}",
                $statusCode,
                $e
            );
        }

        return true;
    }

    public function handleWebhook(Request $request): Response
    {
        $webhookConfig = config('sms.webhooks.sendchamp', []);
        if (is_array($webhookConfig) && ! empty($webhookConfig['secret']) && is_string($webhookConfig['secret'])) {
            $signature = (string) $request->header('X-Sendchamp-Signature', '');
            if (! hash_equals(
                hash_hmac('sha256', $request->getContent(), $webhookConfig['secret']),
                $signature
            )) {
                Log::warning('Sendchamp webhook verification failed', [
                    'ip' => $request->ip(),
                ]);

                return response('Unauthorized', 401);
            }
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

    private function assertSendchampSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $raw = $response->getBody()->getContents();
        /** @var mixed $body */
        $body = json_decode($raw, true);

        if ($statusCode !== 200 || (is_array($body) && ($body['status'] ?? '') === 'failed')) {
            $errorMessage = is_array($body) && isset($body['message']) && is_string($body['message'])
                ? $body['message']
                : $raw;
            throw new RuntimeException(
                "Failed to send SMS via Sendchamp: {$errorMessage}",
                $statusCode
            );
        }
    }
}
