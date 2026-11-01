<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Sinch SMS REST API (batches). One request for all recipients.
 * Config: service_plan_id, api_token, from, region (us|eu|au|br|ca).
 */
class SinchDriver extends HttpDriver
{
    protected string $label = 'Sinch';

    private const REGIONS = ['us', 'eu', 'au', 'br', 'ca'];

    protected function validateConfig(): void
    {
        $this->require('service_plan_id', 'api_token', 'from');
        $this->oneOf('region', self::REGIONS, 'us');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);
        $region = $this->oneOf('region', self::REGIONS, 'us');
        $plan = rawurlencode($this->value('service_plan_id'));

        return $this->sendOnce(
            $message,
            fn (PendingRequest $request): Response => $request
                ->withToken($this->value('api_token'))
                ->post("https://{$region}.sms.api.sinch.com/xms/v1/{$plan}/batches", [
                    'from' => $this->sender($message),
                    'to' => $message->getTo(),
                    'body' => $text,
                ]),
            function (Response $response) use ($message): SmsResult {
                if (! in_array($response->status(), [200, 201], true)) {
                    $this->fail($response, ['text', 'message']);
                }

                return SmsResult::accepted($message->getTo(), $this->jsonString($response, 'id'), 'queued');
            },
        );
    }
}
