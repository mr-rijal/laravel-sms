<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class TwilioDriver implements SmsProvider
{
    protected Client $client;

    /**
     * @param  array<string, mixed>  $config  sid, token, from
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['sid']) || empty($config['token']) || empty($config['from'])) {
            throw new InvalidArgumentException('Twilio configuration is incomplete');
        }

        $sid = (string) $config['sid'];
        $token = (string) $config['token'];

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.twilio.com/2010-04-01/',
            'auth' => [$sid, $token],
            'timeout' => 30,
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        if ($message->getText() === null && $message->getTemplateId() === null) {
            throw new InvalidArgumentException('Message text or template ID is required');
        }

        $accountSid = (string) $this->config['sid'];

        foreach ($message->getTo() as $to) {
            try {
                $response = $this->client->post(
                    "Accounts/{$accountSid}/Messages.json",
                    [
                        'form_params' => [
                            'From' => (string) $this->config['from'],
                            'To' => $to,
                            'Body' => $message->getText() ?? '',
                        ],
                    ]
                );

                $this->assertTwilioSuccess($response);
            } catch (GuzzleException $e) {
                $statusCode = 0;
                if ($e instanceof RequestException && $e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                }
                throw new RuntimeException(
                    "Failed to send SMS via Twilio: {$e->getMessage()}",
                    $statusCode,
                    $e
                );
            }
        }

        return true;
    }

    private function assertTwilioSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $raw = $response->getBody()->getContents();
        /** @var mixed $body */
        $body = json_decode($raw, true);

        if ($statusCode !== 201) {
            $errorMessage = is_array($body) && isset($body['message']) && is_string($body['message'])
                ? $body['message']
                : $raw;
            throw new RuntimeException(
                "Failed to send SMS via Twilio: {$errorMessage}",
                $statusCode
            );
        }
    }
}
