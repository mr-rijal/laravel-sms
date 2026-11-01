<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Bird (formerly MessageBird) SMS API. One request per recipient.
 * Config: api_key (bk_...), from, region (us1|eu1), category.
 */
class BirdDriver extends HttpDriver
{
    protected string $label = 'Bird';

    private const CATEGORIES = ['transactional', 'marketing', 'authentication', 'service'];

    protected function validateConfig(): void
    {
        $this->require('api_key', 'from');
        $this->oneOf('region', ['us1', 'eu1'], 'us1');
        $this->oneOf('category', self::CATEGORIES, 'transactional');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);
        $region = $this->oneOf('region', ['us1', 'eu1'], 'us1');

        return $this->sendEach(
            $message,
            fn (PendingRequest $request, string $to): mixed => $request
                ->withToken($this->value('api_key'))
                ->post("https://{$region}.platform.bird.com/v1/sms/messages", [
                    'from' => $this->sender($message),
                    'to' => $to,
                    'text' => $text,
                    'category' => $this->oneOf('category', self::CATEGORIES, 'transactional'),
                ]),
            function (Response $response, string $to): SentMessage {
                $id = $this->jsonString($response, 'id');

                if (! in_array($response->status(), [200, 201, 202], true) || $id === null) {
                    $this->fail($response, ['message', 'error.message', 'detail']);
                }

                return new SentMessage($to, $id, $this->jsonString($response, 'status'));
            },
        );
    }
}
