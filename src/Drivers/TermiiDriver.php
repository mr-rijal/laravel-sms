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

class TermiiDriver implements SmsProvider
{
    protected Client $client;

    /**
     * @param  array<string, mixed>  $config  api_key, sender_id, channel
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['api_key']) || empty($config['sender_id'])) {
            throw new InvalidArgumentException('Termii configuration is incomplete. Required: api_key, sender_id');
        }

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.ng.termii.com/api/',
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
            throw new InvalidArgumentException('Termii SMS requires a non-empty message body.');
        }

        $apiKey = (string) $this->config['api_key'];
        $senderId = (string) $this->config['sender_id'];
        $channel = (string) ($this->config['channel'] ?? 'generic');

        foreach ($message->getTo() as $to) {
            try {
                $response = $this->client->post('sms/send', [
                    'json' => [
                        'to' => ltrim($to, '+'),
                        'from' => $senderId,
                        'sms' => $body,
                        'type' => 'plain',
                        'channel' => $channel,
                        'api_key' => $apiKey,
                    ],
                ]);

                $this->assertTermiiSuccess($response);
            } catch (GuzzleException $e) {
                $statusCode = 0;
                if ($e instanceof RequestException && $e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                }
                throw new RuntimeException(
                    "Failed to send SMS via Termii: {$e->getMessage()}",
                    $statusCode,
                    $e
                );
            }
        }

        return true;
    }

    public function handleWebhook(Request $request): Response
    {
        $webhookConfig = config('sms.webhooks.termii', []);
        if (is_array($webhookConfig) && ! empty($webhookConfig['secret']) && is_string($webhookConfig['secret'])) {
            $signature = (string) $request->header('X-Termii-Signature', '');
            if (! hash_equals(
                hash_hmac('sha512', $request->getContent(), $webhookConfig['secret']),
                $signature
            )) {
                Log::warning('Termii webhook verification failed', [
                    'ip' => $request->ip(),
                ]);

                return response('Unauthorized', 401);
            }
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        Event::dispatch(new SmsWebhookReceived(
            provider: 'termii',
            payload: $payload,
            messageId: isset($payload['message_id']) && is_string($payload['message_id']) ? $payload['message_id'] : null,
            status: isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : null,
            recipient: isset($payload['to']) && is_string($payload['to']) ? $payload['to'] : null,
        ));

        Log::info('Termii webhook received', [
            'message_id' => $payload['message_id'] ?? null,
            'status' => $payload['status'] ?? null,
        ]);

        return response('OK', 200);
    }

    private function assertTermiiSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $raw = $response->getBody()->getContents();
        /** @var mixed $body */
        $body = json_decode($raw, true);

        if ($statusCode !== 200 || (is_array($body) && isset($body['code']) && $body['code'] !== 'ok')) {
            $errorMessage = is_array($body) && isset($body['message']) && is_string($body['message'])
                ? $body['message']
                : $raw;
            throw new RuntimeException(
                "Failed to send SMS via Termii: {$errorMessage}",
                $statusCode
            );
        }
    }
}
