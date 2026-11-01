<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use MrRijal\LaravelSms\Exceptions\DeliveryException;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Vonage (Nexmo) SMS API. Config: key, secret, from.
 */
class VonageDriver extends HttpDriver
{
    protected string $label = 'Vonage';

    protected function validateConfig(): void
    {
        $this->require('key', 'secret', 'from');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);
        $unicode = ! self::isGsm($text);

        return $this->sendEach(
            $message,
            fn (PendingRequest $request, string $to): mixed => $request->asForm()->post('https://rest.nexmo.com/sms/json', array_filter([
                'api_key' => $this->value('key'),
                'api_secret' => $this->value('secret'),
                'from' => $this->sender($message),
                'to' => ltrim($to, '+'),
                'text' => $text,
                'type' => $unicode ? 'unicode' : null,
            ])),
            function (Response $response, string $to): SentMessage {
                $first = $response->json('messages.0');

                if (! $response->successful() || ! is_array($first) || ($first['status'] ?? null) !== '0') {
                    $error = is_array($first) && is_string($first['error-text'] ?? null) ? ': '.Str::limit($first['error-text'], 200) : '';

                    throw new DeliveryException("Failed to send SMS via Vonage (HTTP {$response->status()}){$error}", 'Vonage', code: $response->status());
                }

                return new SentMessage($to, is_string($first['message-id'] ?? null) ? $first['message-id'] : null, 'accepted');
            },
        );
    }

    /**
     * Whether the text fits the GSM 03.38 basic character set (otherwise send as Unicode).
     */
    private static function isGsm(string $text): bool
    {
        return preg_match('/[^\x00-\x7F£¥èéùìòÇØøÅåΔΦΓΛΩΠΨΣΘΞÆæßÉ¤¡ÄÖÑÜ§¿äöñüà€]/u', $text) === 0;
    }
}
