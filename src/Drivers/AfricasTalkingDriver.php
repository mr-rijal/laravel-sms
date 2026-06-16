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

class AfricasTalkingDriver implements SmsProvider
{
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

        $apiKey = (string) $config['api_key'];
        $sandbox = ! empty($config['sandbox']);
        $baseUri = $sandbox
            ? 'https://api.sandbox.africastalking.com/version1/'
            : 'https://api.africastalking.com/version1/';

        $this->client = $client ?? new Client([
            'base_uri' => $baseUri,
            'headers' => [
                'apiKey' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
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
            throw new InvalidArgumentException("Africa's Talking SMS requires a non-empty message body.");
        }

        $username = (string) $this->config['username'];
        $recipients = implode(',', $message->getTo());

        $formParams = [
            'username' => $username,
            'to' => $recipients,
            'message' => $body,
        ];

        if (! empty($this->config['from'])) {
            $formParams['from'] = (string) $this->config['from'];
        }

        try {
            $response = $this->client->post('messaging', [
                'form_params' => $formParams,
            ]);

            $this->assertAfricasTalkingSuccess($response);
        } catch (GuzzleException $e) {
            $statusCode = 0;
            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
            }
            throw new RuntimeException(
                "Failed to send SMS via Africa's Talking: {$e->getMessage()}",
                $statusCode,
                $e
            );
        }

        return true;
    }

    public function handleWebhook(Request $request): Response
    {
        $userAgent = (string) $request->header('User-Agent', '');
        if (stripos($userAgent, 'AfricasTalking') === false) {
            Log::warning("Africa's Talking webhook verification failed: invalid User-Agent", [
                'ip' => $request->ip(),
                'user_agent' => $userAgent,
            ]);

            return response('Unauthorized', 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();

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
        $raw = $response->getBody()->getContents();
        /** @var mixed $body */
        $body = json_decode($raw, true);

        if ($statusCode !== 201 && $statusCode !== 200) {
            $errorMessage = is_array($body) && isset($body['SMSMessageData']['Message']) && is_string($body['SMSMessageData']['Message'])
                ? $body['SMSMessageData']['Message']
                : $raw;
            throw new RuntimeException(
                "Failed to send SMS via Africa's Talking: {$errorMessage}",
                $statusCode
            );
        }

        if (is_array($body) && isset($body['SMSMessageData']['Recipients'])) {
            $recipients = $body['SMSMessageData']['Recipients'];
            if (is_array($recipients)) {
                foreach ($recipients as $recipient) {
                    if (is_array($recipient) && isset($recipient['statusCode']) && (int) $recipient['statusCode'] > 399) {
                        $msg = $recipient['status'] ?? 'Unknown error';
                        throw new RuntimeException(
                            "Failed to send SMS via Africa's Talking: {$msg}",
                            (int) $recipient['statusCode']
                        );
                    }
                }
            }
        }
    }
}
