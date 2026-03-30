<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Aws\Exception\AwsException;
use Aws\Sns\SnsClient;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;
use RuntimeException;
use Throwable;

class AwsSnsDriver implements SmsProvider
{
    protected SnsClient $client;

    /**
     * @param  array<string, mixed>  $config  key, secret, region, optional sender_id, sms_type
     */
    public function __construct(
        #[\SensitiveParameter]
        protected array $config,
        ?SnsClient $client = null,
    ) {
        if (empty($config['key']) || empty($config['secret']) || empty($config['region'])) {
            throw new InvalidArgumentException('AWS SNS configuration is incomplete. key, secret, and region are required.');
        }

        $this->client = $client ?? new SnsClient([
            'version' => 'latest',
            'region' => (string) $config['region'],
            'credentials' => [
                'key' => (string) $config['key'],
                'secret' => (string) $config['secret'],
            ],
        ]);
    }

    public function send(SmsMessage $message): bool
    {
        if ($message->getText() === null && $message->getTemplateId() === null) {
            throw new InvalidArgumentException('Message text or template ID is required');
        }

        $messageText = $message->getText();
        if ($messageText === null && $message->getTemplateId() !== null) {
            throw new InvalidArgumentException('AWS SNS requires message text. Template-based messaging is not directly supported.');
        }

        foreach ($message->getTo() as $to) {
            try {
                $params = [
                    'PhoneNumber' => $to,
                    'Message' => $messageText,
                ];

                $messageAttributes = [];

                if (! empty($this->config['sender_id'])) {
                    $messageAttributes['AWS.SNS.SMS.SenderID'] = [
                        'DataType' => 'String',
                        'StringValue' => (string) $this->config['sender_id'],
                    ];
                }

                if (! empty($this->config['sms_type'])) {
                    $messageAttributes['AWS.SNS.SMS.SMSType'] = [
                        'DataType' => 'String',
                        'StringValue' => (string) $this->config['sms_type'],
                    ];
                }

                if ($messageAttributes !== []) {
                    $params['MessageAttributes'] = $messageAttributes;
                }

                $result = $this->client->publish($params);

                if (! isset($result['MessageId'])) {
                    throw new RuntimeException('Failed to send SMS via AWS SNS: No message ID returned');
                }
            } catch (AwsException $e) {
                throw new RuntimeException(
                    "Failed to send SMS via AWS SNS: {$e->getAwsErrorMessage()}",
                    $e->getCode(),
                    $e
                );
            } catch (Throwable $e) {
                throw new RuntimeException(
                    "Failed to send SMS via AWS SNS: {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e
                );
            }
        }

        return true;
    }
}
