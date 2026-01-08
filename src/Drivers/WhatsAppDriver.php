<?php

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Events\SmsWebhookReceived;
use MrRijal\LaravelSms\SmsMessage;

class WhatsAppDriver implements SmsProvider
{
    protected Client $client;
    protected string $apiVersion;
    protected string $phoneNumberId;
    protected string $accessToken;
    protected string $businessAccountId;

    public function __construct(protected array $config)
    {
        if (empty($config['phone_number_id']) || empty($config['access_token'])) {
            throw new \InvalidArgumentException('WhatsApp Business API configuration is incomplete. phone_number_id and access_token are required.');
        }

        $this->phoneNumberId = $config['phone_number_id'];
        $this->accessToken = $config['access_token'];
        $this->apiVersion = $config['api_version'] ?? 'v21.0';
        $this->businessAccountId = $config['business_account_id'] ?? '';

        $this->client = new Client([
            'base_uri' => "https://graph.facebook.com/{$this->apiVersion}/",
            'timeout' => 30,
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        if (empty($message->getText()) && empty($message->getTemplateId())) {
            throw new \InvalidArgumentException('Message text or template ID is required');
        }

        foreach ($message->getTo() as $to) {
            try {
                if ($message->getTemplateId()) {
                    $this->sendTemplateMessage($to, $message);
                } else {
                    $this->sendTextMessage($to, $message);
                }
            } catch (GuzzleException $e) {
                $statusCode = 0;
                if ($e instanceof RequestException && $e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                }
                throw new \RuntimeException(
                    "Failed to send WhatsApp message: {$e->getMessage()}",
                    $statusCode,
                    $e
                );
            }
        }

        return true;
    }

    /**
     * Send a text message via WhatsApp Business API
     */
    protected function sendTextMessage(string $to, SmsMessage $message): void
    {
        $phoneNumber = $this->formatPhoneNumber($to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phoneNumber,
            'type' => 'text',
            'text' => [
                'preview_url' => $this->config['preview_url'] ?? false,
                'body' => $message->getText(),
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

    /**
     * Send a template message via WhatsApp Business API
     */
    protected function sendTemplateMessage(string $to, SmsMessage $message): void
    {
        $phoneNumber = $this->formatPhoneNumber($to);
        $templateId = $message->getTemplateId();
        $variables = $message->getVariables();

        $components = [];

        // Add body parameters if variables are provided
        if (! empty($variables)) {
            $components[] = [
                'type' => 'body',
                'parameters' => $this->formatTemplateParameters($variables),
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $templateId,
                'language' => [
                    'code' => $this->config['template_language'] ?? 'en',
                ],
            ],
        ];

        if (! empty($components)) {
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

    /**
     * Format phone number to E.164 format (required by WhatsApp Business API)
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove any non-numeric characters except +
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);

        // If it doesn't start with +, assume it needs country code
        if (! str_starts_with($phoneNumber, '+')) {
            // If no country code is provided, you might want to throw an error
            // or use a default. For now, we'll assume it's already in correct format
            // or add a default country code from config
            $defaultCountryCode = $this->config['default_country_code'] ?? '';
            if ($defaultCountryCode) {
                $phoneNumber = $defaultCountryCode.$phoneNumber;
            }
        }

        return $phoneNumber;
    }

    /**
     * Format template parameters for WhatsApp API
     */
    protected function formatTemplateParameters(array $variables): array
    {
        $parameters = [];

        foreach ($variables as $key => $value) {
            // Skip non-numeric keys as WhatsApp uses indexed parameters
            if (is_numeric($key)) {
                $parameters[] = [
                    'type' => 'text',
                    'text' => (string) $value,
                ];
            } else {
                // If using named keys, convert to indexed array
                $parameters[] = [
                    'type' => 'text',
                    'text' => (string) $value,
                ];
            }
        }

        return $parameters;
    }

    /**
     * Handle API response
     */
    protected function handleResponse($response, string $messageType): void
    {
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true);

        if ($statusCode !== 200) {
            $errorMessage = $body['error']['message'] ?? $body['error']['error_user_msg'] ?? 'Unknown error';
            $errorCode = $body['error']['code'] ?? $statusCode;
            throw new \RuntimeException(
                "Failed to send WhatsApp {$messageType}: {$errorMessage} (Code: {$errorCode})",
                $statusCode
            );
        }
    }

    /**
     * Handle incoming webhook from WhatsApp Business API
     */
    public function handleWebhook(Request $request): Response
    {
        // Handle webhook verification (GET request)
        if ($request->isMethod('GET')) {
            return $this->handleVerification($request);
        }

        // Verify webhook signature
        $webhookConfig = config('sms.webhooks.whatsapp', []);
        if (! empty($webhookConfig['secret'])) {
            if (! $this->verifyWebhook($request, $webhookConfig['secret'])) {
                Log::warning('WhatsApp webhook verification failed', [
                    'ip' => $request->ip(),
                ]);

                return response('Unauthorized', 401);
            }
        }

        // Parse and dispatch webhook event
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

    /**
     * Handle WhatsApp webhook verification
     */
    protected function handleVerification(Request $request): Response
    {
        $webhookConfig = config('sms.webhooks.whatsapp', []);
        $verifyToken = $webhookConfig['verify_token'] ?? '';

        $mode = $request->query('hub.mode');
        $token = $request->query('hub.verify_token');
        $challenge = $request->query('hub.challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verified', [
                'challenge' => $challenge,
            ]);

            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Verify WhatsApp webhook signature
     */
    protected function verifyWebhook(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Hub-Signature-256');
        if (empty($signature)) {
            return false;
        }

        $payload = $request->getContent();
        $computed = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($signature, $computed);
    }

    /**
     * Parse WhatsApp webhook payload
     */
    protected function parseWebhook(array $payload): array
    {
        $data = [
            'message_id' => null,
            'status' => null,
            'recipient' => null,
        ];

        // Handle WhatsApp Business API webhook structure
        if (isset($payload['entry'][0]['changes'][0]['value'])) {
            $value = $payload['entry'][0]['changes'][0]['value'];

            // Message status update
            if (isset($value['statuses'][0])) {
                $status = $value['statuses'][0];
                $data['message_id'] = $status['id'] ?? null;
                $data['status'] = $status['status'] ?? null;
                $data['recipient'] = $status['recipient_id'] ?? null;
            }

            // Incoming message
            if (isset($value['messages'][0])) {
                $message = $value['messages'][0];
                $data['message_id'] = $message['id'] ?? null;
                $data['status'] = 'received';
                $data['recipient'] = $message['from'] ?? null;
            }
        }

        return $data;
    }
}
