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

class AfricasTalkingDriver implements HandlesWebhooks, SmsProvider
{
    use VerifiesWebhooks;

    protected Client $client;

    /**
     * @param  array<string, mixed>  $config  api_key, username, from (optional), sandbox (optional)
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['api_key']) || empty($config['username'])) {
            throw new InvalidArgumentException("Africa's Talking configuration is incomplete. Required: api_key, username");
        }

        $sandbox = filter_var($config['sandbox'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $this->client = $client ?? new Client([
            'base_uri' => $sandbox
                ? 'https://api.sandbox.africastalking.com/version1/'
                : 'https://api.africastalking.com/version1/',
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        $body = trim((string) $message->getText());

        if ($body === '') {
            throw new InvalidArgumentException("Africa's Talking SMS requires a non-empty message body.");
        }

        $formParams = [
            'username' => (string) $this->config['username'],
            'to' => implode(',', $message->getTo()),
            'message' => $body,
        ];

        if (! empty($this->config['from'])) {
            $formParams['from'] = (string) $this->config['from'];
        }

        try {
            $response = $this->client->post('messaging', [
                'headers' => [
                    'apiKey' => (string) $this->config['api_key'],
                    'Accept' => 'application/json',
                ],
                'form_params' => $formParams,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException("Failed to send SMS via Africa's Talking (transport error).", 0, $e);
        }

        $this->assertAfricasTalkingSuccess($response);

        return true;
    }

    /**
     * Africa's Talking does not sign callbacks, so the callback URL must carry
     * a shared secret: https://example.com/laravel-sms/webhook/africastalking?token=<secret>
     */
    public function handleWebhook(Request $request): Response
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

    private function assertAfricasTalkingSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        /** @var mixed $body */
        $body = json_decode((string) $response->getBody(), true);

        if ($statusCode !== 201 && $statusCode !== 200) {
            $detail = is_array($body) && isset($body['SMSMessageData']['Message']) && is_string($body['SMSMessageData']['Message'])
                ? ': '.Str::limit($body['SMSMessageData']['Message'], 200)
                : '';

            throw new RuntimeException("Failed to send SMS via Africa's Talking (HTTP {$statusCode}){$detail}", $statusCode);
        }

        $recipients = is_array($body) ? ($body['SMSMessageData']['Recipients'] ?? null) : null;
        if (! is_array($recipients)) {
            return;
        }

        foreach ($recipients as $recipient) {
            if (is_array($recipient) && isset($recipient['statusCode']) && (int) $recipient['statusCode'] > 399) {
                $status = isset($recipient['status']) && is_string($recipient['status']) ? $recipient['status'] : 'Unknown error';

                throw new RuntimeException("Failed to send SMS via Africa's Talking: {$status}", (int) $recipient['statusCode']);
            }
        }
    }
}
