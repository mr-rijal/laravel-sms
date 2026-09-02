<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;
use RuntimeException;

class DiscordWebhookDriver implements SmsProvider
{
    protected Client $client;

    /** @param array<string, mixed> $config webhook_url */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        $url = $config['webhook_url'] ?? null;
        if (! is_string($url) || ! $this->isDiscordWebhookUrl($url)) {
            throw new InvalidArgumentException('Discord webhook_url must be an HTTPS Discord webhook URL.');
        }

        $this->client = $client ?? new Client(['timeout' => 30, 'connect_timeout' => 10, 'http_errors' => false]);
    }

    public function send(SmsMessage $message): bool
    {
        $message->validate();

        try {
            $response = $this->client->post((string) $this->config['webhook_url'], [
                'json' => ['content' => $this->formatMessage($message)],
            ]);

            if (! in_array($response->getStatusCode(), [200, 204], true)) {
                throw new RuntimeException("Failed to mirror SMS to Discord (HTTP {$response->getStatusCode()}).");
            }
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to mirror SMS to Discord.', 0, $e);
        }

        return true;
    }

    private function isDiscordWebhookUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        return ($parts['scheme'] ?? '') === 'https'
            && in_array($host, ['discord.com', 'canary.discord.com', 'ptb.discord.com', 'discordapp.com'], true)
            && str_starts_with($parts['path'] ?? '', '/api/webhooks/');
    }

    private function formatMessage(SmsMessage $message): string
    {
        return "**SMS mirror**\nRecipients: ".implode(', ', $message->getRedactedRecipientsForLogging())."\nMessage: ".($message->getText() ?? '[template: '.$message->getTemplateId().']');
    }
}
