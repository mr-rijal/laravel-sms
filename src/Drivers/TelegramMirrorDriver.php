<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;
use RuntimeException;

class TelegramMirrorDriver implements SmsProvider
{
    protected Client $client;

    /**
     * @param  array<string, mixed>  $config  bot_token, chat_id
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        if (empty($config['bot_token']) || empty($config['chat_id'])) {
            throw new InvalidArgumentException('Telegram mirror configuration is incomplete. bot_token and chat_id are required.');
        }

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.telegram.org/bot'.(string) $config['bot_token'].'/',
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        $message->validate();

        try {
            $response = $this->client->post('sendMessage', [
                'json' => [
                    'chat_id' => $this->config['chat_id'],
                    'text' => $this->formatMessage($message),
                ],
            ]);

            /** @var mixed $body */
            $body = json_decode((string) $response->getBody(), true);
            if ($response->getStatusCode() !== 200 || ! is_array($body) || ($body['ok'] ?? false) !== true) {
                throw new RuntimeException("Failed to mirror SMS to Telegram (HTTP {$response->getStatusCode()}).");
            }
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to mirror SMS to Telegram.');
        }

        return true;
    }

    private function formatMessage(SmsMessage $message): string
    {
        return "SMS mirror\nRecipients: ".implode(', ', $message->getRedactedRecipientsForLogging())."\nMessage: ".($message->getText() ?? '[template: '.$message->getTemplateId().']');
    }
}
