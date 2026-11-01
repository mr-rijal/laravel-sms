<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use MrRijal\LaravelSms\Concerns\VerifiesWebhooks;
use MrRijal\LaravelSms\Contracts\HandlesWebhooks;
use MrRijal\LaravelSms\Events\SmsWebhookReceived;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * WhatsApp Business Cloud API. Text or approved template messages.
 * Config: phone_number_id, access_token, api_version, template_language, preview_url, default_country_code.
 */
class WhatsAppDriver extends HttpDriver implements HandlesWebhooks
{
    use VerifiesWebhooks;

    protected string $label = 'WhatsApp';

    protected function validateConfig(): void
    {
        $this->require('phone_number_id', 'access_token');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $message->validate();
        $version = rawurlencode($this->string('api_version', 'v21.0') ?? 'v21.0');
        $phoneNumberId = rawurlencode($this->value('phone_number_id'));

        return $this->sendEach(
            $message,
            fn (PendingRequest $request, string $to): mixed => $request
                ->withToken($this->value('access_token'))
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", $this->payload($message, $to)),
            function (Response $response, string $to): SentMessage {
                $id = $this->jsonString($response, 'messages.0.id');

                if ($response->status() !== 200 || $id === null) {
                    $this->fail($response, ['error.message', 'error.error_user_msg']);
                }

                return new SentMessage($to, $id, $this->jsonString($response, 'messages.0.message_status') ?? 'accepted');
            },
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(SmsMessage $message, string $to): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($to),
        ];

        $templateId = $message->getTemplateId();

        if ($templateId === null) {
            return $payload + [
                'type' => 'text',
                'text' => ['preview_url' => $this->bool('preview_url'), 'body' => (string) $message->getText()],
            ];
        }

        $template = [
            'name' => $templateId,
            'language' => ['code' => $this->string('template_language', 'en')],
        ];

        if ($message->getVariables() !== []) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_values(array_map(
                    fn (mixed $value): array => ['type' => 'text', 'text' => is_scalar($value) ? (string) $value : ''],
                    $message->getVariables(),
                )),
            ]];
        }

        return $payload + ['type' => 'template', 'template' => $template];
    }

    protected function formatPhoneNumber(string $number): string
    {
        if (! str_starts_with($number, '+') && ($code = $this->string('default_country_code')) !== null) {
            return ltrim($code, '+').$number;
        }

        return ltrim($number, '+');
    }

    public function handleWebhook(Request $request): HttpResponse
    {
        if ($request->isMethod('GET')) {
            return $this->handleVerification($request);
        }

        if (! $this->verifyWebhook($request)) {
            $this->logRejectedWebhook($request, 'whatsapp');

            return response('Unauthorized', 401);
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

    protected function handleVerification(Request $request): HttpResponse
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

        if ($mode !== 'subscribe' || ! is_string($challenge) || ! is_string($token)) {
            return response('Forbidden', 403);
        }

        if (! hash_equals($verifyToken, $token)) {
            return response('Forbidden', 403);
        }

        Log::info('WhatsApp webhook verified', [
            'challenge' => $challenge,
        ]);

        return response($challenge, 200);
    }

    /**
     * Meta signs every webhook with the app secret: X-Hub-Signature-256: sha256=<hmac>.
     */
    protected function verifyWebhook(Request $request): bool
    {
        return $this->hasValidHmacSignature($request, 'whatsapp', 'X-Hub-Signature-256', 'sha256', 'sha256=');
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

        $value = data_get($payload, 'entry.0.changes.0.value');

        if (! is_array($value)) {
            return $data;
        }

        $status = data_get($value, 'statuses.0');

        if (is_array($status)) {
            $data['message_id'] = isset($status['id']) && is_string($status['id']) ? $status['id'] : null;
            $data['status'] = isset($status['status']) && is_string($status['status']) ? $status['status'] : null;
            $data['recipient'] = isset($status['recipient_id']) && is_string($status['recipient_id']) ? $status['recipient_id'] : null;
        }

        $msg = data_get($value, 'messages.0');

        if (is_array($msg)) {
            $data['message_id'] = isset($msg['id']) && is_string($msg['id']) ? $msg['id'] : null;
            $data['status'] = 'received';
            $data['recipient'] = isset($msg['from']) && is_string($msg['from']) ? $msg['from'] : null;
        }

        return $data;
    }
}
