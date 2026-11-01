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

class SendchampDriver implements HandlesWebhooks, SmsProvider
{
    use VerifiesWebhooks;

    private const ROUTES = ['dnd', 'non_dnd', 'international'];

    protected Client $client;

    /**
     * @param  array<string, mixed>  $config  api_key, sender_name, route (dnd|non_dnd|international)
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['api_key']) || empty($config['sender_name'])) {
            throw new InvalidArgumentException('Sendchamp configuration is incomplete. Required: api_key, sender_name');
        }

        $route = $config['route'] ?? 'dnd';
        if (! is_string($route) || ! in_array($route, self::ROUTES, true)) {
            throw new InvalidArgumentException('Sendchamp route must be one of: '.implode(', ', self::ROUTES).'.');
        }

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.sendchamp.com/api/v1/',
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        $body = trim((string) $message->getText());

        if ($body === '') {
            throw new InvalidArgumentException('Sendchamp SMS requires a non-empty message body.');
        }

        try {
            $response = $this->client->post('sms/send', [
                'headers' => [
                    'Authorization' => 'Bearer '.(string) $this->config['api_key'],
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'to' => array_map(fn (string $number): string => ltrim($number, '+'), $message->getTo()),
                    'message' => $body,
                    'sender_name' => (string) $this->config['sender_name'],
                    'route' => (string) ($this->config['route'] ?? 'dnd'),
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to send SMS via Sendchamp (transport error).', 0, $e);
        }

        $this->assertSendchampSuccess($response);

        return true;
    }

    public function handleWebhook(Request $request): Response
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

    private function assertSendchampSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        /** @var mixed $body */
        $body = json_decode((string) $response->getBody(), true);

        if ($statusCode === 200 && ! (is_array($body) && ($body['status'] ?? '') === 'failed')) {
            return;
        }

        $detail = is_array($body) && isset($body['message']) && is_string($body['message'])
            ? ': '.Str::limit($body['message'], 200)
            : '';

        throw new RuntimeException("Failed to send SMS via Sendchamp (HTTP {$statusCode}){$detail}", $statusCode);
    }
}
