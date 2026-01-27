<?php

namespace MrRijal\LaravelSms\Drivers;

use Aws\Exception\AwsException;
use Aws\Sns\SnsClient;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class AwsSnsDriver implements SmsProvider
{
    protected SnsClient $client;

    /**
     * Create a new AwsSnsDriver and initialize its SNS client.
     *
     * The $config array must contain the keys 'key', 'secret', and 'region'.
     *
     * @param array $config AWS configuration with required keys: 'key', 'secret', and 'region'.
     * @param SnsClient|null $client Optional preconfigured SNS client to use; if omitted a new client is created from $config.
     * @throws \InvalidArgumentException If any of 'key', 'secret', or 'region' are missing from $config.
     */
    public function __construct(protected array $config, ?SnsClient $client = null)
    {
        if (empty($config['key']) || empty($config['secret']) || empty($config['region'])) {
            throw new \InvalidArgumentException('AWS SNS configuration is incomplete. key, secret, and region are required.');
        }

        $this->client = $client ?? new SnsClient([
            'version' => 'latest',
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        if (empty($message->getText()) && empty($message->getTemplateId())) {
            throw new \InvalidArgumentException('Message text or template ID is required');
        }

        // AWS SNS doesn't support templates in the same way, so we'll use the message text
        // If template ID is provided, we'll log a warning and use the text if available
        $messageText = $message->getText();
        if (empty($messageText) && ! empty($message->getTemplateId())) {
            throw new \InvalidArgumentException('AWS SNS requires message text. Template-based messaging is not directly supported.');
        }

        foreach ($message->getTo() as $to) {
            try {
                $params = [
                    'PhoneNumber' => $to,
                    'Message' => $messageText,
                ];

                // Add message attributes if configured
                $messageAttributes = [];

                // Add sender ID if configured (optional, AWS SNS may use default)
                if (! empty($this->config['sender_id'])) {
                    $messageAttributes['AWS.SNS.SMS.SenderID'] = [
                        'DataType' => 'String',
                        'StringValue' => $this->config['sender_id'],
                    ];
                }

                // Add SMS type if configured (Promotional or Transactional)
                if (! empty($this->config['sms_type'])) {
                    $messageAttributes['AWS.SNS.SMS.SMSType'] = [
                        'DataType' => 'String',
                        'StringValue' => $this->config['sms_type'],
                    ];
                }

                // Only add MessageAttributes if we have any attributes
                if (! empty($messageAttributes)) {
                    $params['MessageAttributes'] = $messageAttributes;
                }

                $result = $this->client->publish($params);

                // Check if the publish was successful
                if (! isset($result['MessageId'])) {
                    throw new \RuntimeException('Failed to send SMS via AWS SNS: No message ID returned');
                }
            } catch (AwsException $e) {
                $errorMessage = method_exists($e, 'getAwsErrorMessage')
                    ? $e->getAwsErrorMessage()
                    : $e->getMessage();
                throw new \RuntimeException(
                    "Failed to send SMS via AWS SNS: {$errorMessage}",
                    $e->getCode(),
                    $e
                );
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    "Failed to send SMS via AWS SNS: {$e->getMessage()}",
                    $e->getCode(),
                    $e
                );
            }
        }

        return true;
    }
}