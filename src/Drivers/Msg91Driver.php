<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * MSG91 (India). Template messages use the Flow API (DLT-compliant); plain text uses the v2 SMS API.
 * Config: authkey, sender.
 */
class Msg91Driver extends HttpDriver
{
    protected string $label = 'MSG91';

    protected function validateConfig(): void
    {
        $this->require('authkey', 'sender');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $message->validate();
        $templateId = $message->getTemplateId();

        if ($templateId !== null) {
            return $this->sendOnce(
                $message,
                fn (PendingRequest $request): Response => $request
                    ->withHeaders(['authkey' => $this->value('authkey')])
                    ->post('https://control.msg91.com/api/v5/flow', [
                        'template_id' => $templateId,
                        'sender' => $this->sender($message, 'sender'),
                        'short_url' => '0',
                        'recipients' => array_map(
                            fn (string $to): array => ['mobiles' => ltrim($to, '+'), ...$message->getVariables()],
                            $message->getTo(),
                        ),
                    ]),
                fn (Response $response): SmsResult => $this->parse($response, $message->getTo()),
            );
        }

        $text = $this->text($message);

        return $this->sendEach(
            $message,
            fn (PendingRequest $request, string $to): mixed => $request->asForm()->post('https://api.msg91.com/api/v2/sendsms', [
                'authkey' => $this->value('authkey'),
                'mobiles' => ltrim($to, '+'),
                'message' => $text,
                'sender' => $this->sender($message, 'sender'),
                'route' => '4',
            ]),
            fn (Response $response, string $to): SentMessage => $this->parse($response, [$to])->messages[0],
        );
    }

    /**
     * @param  list<string>  $recipients
     */
    private function parse(Response $response, array $recipients): SmsResult
    {
        if (! $response->successful() || $response->json('type') === 'error') {
            $this->fail($response, 'message');
        }

        return SmsResult::accepted($recipients, $this->jsonString($response, 'request_id') ?? $this->jsonString($response, 'message'), 'accepted');
    }
}
