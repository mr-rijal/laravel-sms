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

class TermiiDriver implements HandlesWebhooks, SmsProvider
{
    use VerifiesWebhooks;

    private const CHANNELS = ['generic', 'dnd', 'whatsapp'];

    protected Client $client;

    /**
     * @param  array<string, mixed>  $config  api_key, sender_id, channel (generic|dnd|whatsapp), optional base_url
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['api_key']) || empty($config['sender_id'])) {
            throw new InvalidArgumentException('Termii configuration is incomplete. Required: api_key, sender_id');
        }

        $channel = $config['channel'] ?? 'generic';
        if (! is_string($channel) || ! in_array($channel, self::CHANNELS, true)) {
            throw new InvalidArgumentException('Termii channel must be one of: '.implode(', ', self::CHANNELS).'.');
        }

        $baseUrl = $config['base_url'] ?? 'https://api.ng.termii.com/';
        if (! is_string($baseUrl) || ! str_starts_with($baseUrl, 'https://')) {
            throw new InvalidArgumentException('Termii base_url must use HTTPS.');
        }

        $this->client = $client ?? new Client([
            'base_uri' => rtrim($baseUrl, '/').'/api/',
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        $body = trim((string) $message->getText());

        if ($body === '') {
            throw new InvalidArgumentException('Termii SMS requires a non-empty message body.');
        }

        foreach ($message->getTo() as $to) {
            try {
                $response = $this->client->post('sms/send', [
                    'headers' => ['Accept' => 'application/json'],
                    'json' => [
                        'to' => ltrim($to, '+'),
                        'from' => (string) $this->config['sender_id'],
                        'sms' => $body,
                        'type' => 'plain',
                        'channel' => (string) ($this->config['channel'] ?? 'generic'),
                        // Termii authenticates with the API key in the request body.
                        'api_key' => (string) $this->config['api_key'],
                    ],
                ]);
            } catch (GuzzleException $e) {
                throw new RuntimeException('Failed to send SMS via Termii (transport error).', 0, $e);
            }

            $this->assertTermiiSuccess($response);
        }

        return true;
    }

    public function handleWebhook(Request $request): Response
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

    private function assertTermiiSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        /** @var mixed $body */
        $body = json_decode((string) $response->getBody(), true);

        if ($statusCode === 200 && ! (is_array($body) && isset($body['code']) && $body['code'] !== 'ok')) {
            return;
        }

        $detail = is_array($body) && isset($body['message']) && is_string($body['message'])
            ? ': '.Str::limit($body['message'], 200)
            : '';

        throw new RuntimeException("Failed to send SMS via Termii (HTTP {$statusCode}){$detail}", $statusCode);
    }
}
