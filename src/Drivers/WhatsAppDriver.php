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

class WhatsAppDriver implements SmsProvider
{
    protected Client $client;

    protected string $apiVersion;

    protected string $phoneNumberId;

    protected string $accessToken;

    protected string $businessAccountId;

    /**
     * @param  array<string, mixed>  $config  phone_number_id, access_token, optional api_version, business_account_id, template_language, preview_url, default_country_code
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['phone_number_id']) || empty($config['access_token'])) {
            throw new InvalidArgumentException('WhatsApp Business API configuration is incomplete. phone_number_id and access_token are required.');
        }

        $this->phoneNumberId = (string) $config['phone_number_id'];
        $this->accessToken = (string) $config['access_token'];
        $this->apiVersion = isset($config['api_version']) && is_string($config['api_version']) ? $config['api_version'] : 'v21.0';
        $this->businessAccountId = isset($config['business_account_id']) && is_string($config['business_account_id'])
            ? $config['business_account_id']
            : '';

        $this->client = $client ?? new Client([
            'base_uri' => "https://graph.facebook.com/{$this->apiVersion}/",
            'timeout' => 30,
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        $trimmedText = trim((string) $message->getText());
        $trimmedTemplateId = trim((string) $message->getTemplateId());

        if ($trimmedText === '' && $trimmedTemplateId === '') {
            throw new InvalidArgumentException('Message text or template ID is required');
        }

        foreach ($message->getTo() as $to) {
            try {
                if ($trimmedTemplateId !== '') {
                    $this->sendTemplateMessage($to, $message);
                } else {
                    $this->sendTextMessage($to, $message);
                }
            } catch (GuzzleException $e) {
                $statusCode = 0;
                if ($e instanceof RequestException && $e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                }
                throw new RuntimeException(
                    "Failed to send WhatsApp message: {$e->getMessage()}",
                    $statusCode,
                    $e
                );
            }
        }

        return true;
    }

    protected function sendTextMessage(string $to, SmsMessage $message): void
    {
        $phoneNumber = $this->formatPhoneNumber($to);
        $body = trim((string) $message->getText());
        if ($body === '') {
            throw new InvalidArgumentException('Message text is required for WhatsApp text messages.');
        }

        $preview = $this->config['preview_url'] ?? false;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phoneNumber,
            'type' => 'text',
            'text' => [
                'preview_url' => is_bool($preview) ? $preview : false,
                'body' => $body,
            ],
        ];

        $response = $this->client->post(
            "{$this->phoneNumberId}/messages",
            [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]
        );

        $this->handleResponse($response, 'text message');
    }

    protected function sendTemplateMessage(string $to, SmsMessage $message): void
    {
        $phoneNumber = $this->formatPhoneNumber($to);
        $templateId = trim((string) $message->getTemplateId());
        if ($templateId === '') {
            throw new InvalidArgumentException('Template ID is required.');
        }

        $variables = $message->getVariables();
        $components = [];

        if ($variables !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => $this->formatTemplateParameters($variables),
            ];
        }

        $lang = $this->config['template_language'] ?? 'en';
        $languageCode = is_string($lang) ? $lang : 'en';

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $templateId,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        $response = $this->client->post(
            "{$this->phoneNumberId}/messages",
            [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]
        );

        $this->handleResponse($response, 'template message');
    }

    protected function formatPhoneNumber(string $phoneNumber): string
    {
        $normalized = preg_replace('/[^0-9+]/', '', $phoneNumber);
        if ($normalized === null) {
            throw new RuntimeException('Invalid phone number.');
        }

        if ($normalized !== '' && ! str_starts_with($normalized, '+')) {
            $defaultCountryCode = $this->config['default_country_code'] ?? '';
            if (is_string($defaultCountryCode) && $defaultCountryCode !== '') {
                $normalized = $defaultCountryCode.$normalized;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string|int, mixed>  $variables
     * @return list<array{type: 'text', text: string}>
     */
    protected function formatTemplateParameters(array $variables): array
    {
        $parameters = [];

        foreach ($variables as $value) {
            $parameters[] = [
                'type' => 'text',
                'text' => (string) $value,
            ];
        }

        return $parameters;
    }

    protected function handleResponse(ResponseInterface $response, string $messageType): void
    {
        $statusCode = $response->getStatusCode();
        $raw = $response->getBody()->getContents();
        /** @var mixed $body */
        $body = json_decode($raw, true);

        if ($statusCode !== 200) {
            $errorMessage = 'Unknown error';
            $errorCode = $statusCode;
            if (is_array($body) && isset($body['error']) && is_array($body['error'])) {
                $err = $body['error'];
                if (isset($err['message']) && is_string($err['message'])) {
                    $errorMessage = $err['message'];
                } elseif (isset($err['error_user_msg']) && is_string($err['error_user_msg'])) {
                    $errorMessage = $err['error_user_msg'];
                }
                if (isset($err['code'])) {
                    $errorCode = is_int($err['code']) ? $err['code'] : $statusCode;
                }
            }
            throw new RuntimeException(
                "Failed to send WhatsApp {$messageType}: {$errorMessage} (Code: {$errorCode})",
                $statusCode
            );
        }
    }

    public function handleWebhook(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->handleVerification($request);
        }

        $webhookConfig = config('sms.webhooks.whatsapp', []);
        if (is_array($webhookConfig) && ! empty($webhookConfig['secret']) && is_string($webhookConfig['secret'])) {
            if (! $this->verifyWebhook($request, $webhookConfig['secret'])) {
                Log::warning('WhatsApp webhook verification failed', [
                    'ip' => $request->ip(),
                ]);

                return response('Unauthorized', 401);
            }
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $parsedData = $this->parseWebhook($payload);

        Event::dispatch(new SmsWebhookReceived(
            provider: 'whatsapp',
            payload: $payload,
            messageId: $parsedData['message_id'] ?? null,
            status: $parsedData['status'] ?? null,
            recipient: $parsedData['recipient'] ?? null
        ));

        Log::info('WhatsApp webhook received', [
            'message_id' => $parsedData['message_id'] ?? null,
            'status' => $parsedData['status'] ?? null,
        ]);

        return response('OK', 200);
    }

    protected function handleVerification(Request $request): Response
    {
        $webhookConfig = config('sms.webhooks.whatsapp', []);
        $verifyToken = '';
        if (is_array($webhookConfig) && isset($webhookConfig['verify_token']) && is_string($webhookConfig['verify_token'])) {
            $verifyToken = trim($webhookConfig['verify_token']);
        }

        if ($verifyToken === '') {
            Log::warning('WhatsApp webhook verification rejected: verify_token is not configured or is empty', [
                'ip' => $request->ip(),
            ]);

            return response('Forbidden', 403);
        }

        // Facebook sends hub.mode etc.; PHP converts dots to underscores in parsed query keys.
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode !== 'subscribe' || $challenge === null || ! is_string($token)) {
            return response('Forbidden', 403);
        }

        if (! hash_equals($verifyToken, $token)) {
            return response('Forbidden', 403);
        }

        Log::info('WhatsApp webhook verified', [
            'challenge' => $challenge,
        ]);

        return response((string) $challenge, 200);
    }

    protected function verifyWebhook(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Hub-Signature-256');
        if ($signature === null || $signature === '') {
            return false;
        }

        $payload = $request->getContent();
        $computed = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($signature, $computed);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message_id: ?string, status: ?string, recipient: ?string}
     */
    protected function parseWebhook(array $payload): array
    {
        $data = [
            'message_id' => null,
            'status' => null,
            'recipient' => null,
        ];

        if (! isset($payload['entry'][0]['changes'][0]['value']) || ! is_array($payload['entry'][0]['changes'][0]['value'])) {
            return $data;
        }

        /** @var array<string, mixed> $value */
        $value = $payload['entry'][0]['changes'][0]['value'];

        if (isset($value['statuses'][0]) && is_array($value['statuses'][0])) {
            $status = $value['statuses'][0];
            $data['message_id'] = isset($status['id']) && is_string($status['id']) ? $status['id'] : null;
            $data['status'] = isset($status['status']) && is_string($status['status']) ? $status['status'] : null;
            $data['recipient'] = isset($status['recipient_id']) && is_string($status['recipient_id']) ? $status['recipient_id'] : null;
        }

        if (isset($value['messages'][0]) && is_array($value['messages'][0])) {
            $msg = $value['messages'][0];
            $data['message_id'] = isset($msg['id']) && is_string($msg['id']) ? $msg['id'] : null;
            $data['status'] = 'received';
            $data['recipient'] = isset($msg['from']) && is_string($msg['from']) ? $msg['from'] : null;
        }

        return $data;
    }
}
