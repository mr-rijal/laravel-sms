<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use MrRijal\LaravelSms\Concerns\VerifiesWebhooks;
use MrRijal\LaravelSms\Contracts\HandlesWebhooks;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Events\SmsWebhookReceived;
use MrRijal\LaravelSms\SmsMessage;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class BulkSmsDriver implements HandlesWebhooks, SmsProvider
{
    use VerifiesWebhooks;

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

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.bulksms.com/v1/',
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        $body = trim((string) $message->getText());

        if ($body === '') {
            throw new InvalidArgumentException('BulkSMS requires a non-empty message body.');
        }

        $recipients = $message->getTo();
        if ($recipients === []) {
            throw new InvalidArgumentException('BulkSMS requires at least one recipient.');
        }

        $from = isset($this->config['from']) && $this->config['from'] !== '' ? (string) $this->config['from'] : null;

        foreach ($recipients as $to) {
            $payload = ['to' => $to, 'body' => $body];

            if ($from !== null) {
                $payload['from'] = $from;
            }

            try {
                $response = $this->client->post('messages', [
                    'auth' => [(string) $this->config['token_id'], (string) $this->config['token_secret']],
                    'headers' => ['Accept' => 'application/json'],
                    'json' => $payload,
                ]);
            } catch (GuzzleException $e) {
                throw new RuntimeException('Failed to send SMS via BulkSMS (transport error).', 0, $e);
            }

            $this->assertBulkSmsSuccess($response);
        }

        return true;
    }

    public function handleWebhook(Request $request): Response
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

    private function assertBulkSmsSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode <= 299) {
            return;
        }

        /** @var mixed $body */
        $body = json_decode((string) $response->getBody(), true);
        $detail = is_array($body) && isset($body['detail']) && is_string($body['detail'])
            ? ': '.Str::limit($body['detail'], 200)
            : '';

        throw new RuntimeException("Failed to send SMS via BulkSMS (HTTP {$statusCode}){$detail}", $statusCode);
    }
}
