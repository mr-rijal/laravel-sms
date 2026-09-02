<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;
use RuntimeException;

class SlackWebhookDriver implements SmsProvider
{
    protected Client $client;

    /** @param array<string, mixed> $config webhook_url */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?Client $client = null,
    ) {
        $url = $config['webhook_url'] ?? null;
        if (! is_string($url) || ! $this->isSlackWebhookUrl($url)) {
            throw new InvalidArgumentException('Slack webhook_url must be an HTTPS Slack incoming webhook URL.');
        }

        $this->client = $client ?? new Client(['timeout' => 30, 'connect_timeout' => 10, 'http_errors' => false]);
    }

    public function send(SmsMessage $message): bool
    {
        $message->validate();

        try {
            $response = $this->client->post((string) $this->config['webhook_url'], [
                'json' => ['text' => $this->formatMessage($message)],
            ]);

            if ($response->getStatusCode() !== 200 || trim((string) $response->getBody()) !== 'ok') {
                throw new RuntimeException("Failed to mirror SMS to Slack (HTTP {$response->getStatusCode()}).");
            }
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to mirror SMS to Slack.');
        }

        return true;
    }

    private function isSlackWebhookUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        return ($parts['scheme'] ?? '') === 'https'
            && in_array($host, ['hooks.slack.com', 'hooks.slack-gov.com'], true)
            && str_starts_with($parts['path'] ?? '', '/services/');
    }

    private function formatMessage(SmsMessage $message): string
    {
        return "*SMS mirror*\nRecipients: ".implode(', ', $message->getRedactedRecipientsForLogging())."\nMessage: ".($message->getText() ?? '[template: '.$message->getTemplateId().']');
    }
}
