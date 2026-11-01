<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Aws\Exception\AwsException;
use Aws\Sns\SnsClient;
use MrRijal\LaravelSms\Exceptions\DeliveryException;
use MrRijal\LaravelSms\Exceptions\InvalidConfigurationException;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

/**
 * Amazon SNS SMS. Requires aws/aws-sdk-php. Config: region, optional key/secret (omit them to use
 * the default AWS credential chain, e.g. IAM roles), sender_id, sms_type (Transactional|Promotional).
 */
class AwsSnsDriver extends Driver
{
    protected string $label = 'AWS SNS';

    protected ?SnsClient $client = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        #[\SensitiveParameter]
        array $config = [],
        ?SnsClient $client = null,
    ) {
        parent::__construct($config);
        $this->client = $client;
    }

    protected function validateConfig(): void
    {
        if (! class_exists(SnsClient::class)) {
            throw new InvalidConfigurationException('The AWS SNS driver requires aws/aws-sdk-php. Run: composer require aws/aws-sdk-php');
        }

        $this->require('region');
        $this->oneOf('sms_type', ['Transactional', 'Promotional'], 'Transactional');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);
        $client = $this->client();
        $sent = [];
        $failed = [];
        $error = null;

        foreach ($message->getTo() as $to) {
            try {
                $result = $client->publish([
                    'PhoneNumber' => $to,
                    'Message' => $text,
                    'MessageAttributes' => array_filter([
                        'AWS.SNS.SMS.SenderID' => ($sender = $this->sender($message, 'sender_id')) !== null
                            ? ['DataType' => 'String', 'StringValue' => $sender]
                            : null,
                        'AWS.SNS.SMS.SMSType' => [
                            'DataType' => 'String',
                            'StringValue' => $this->oneOf('sms_type', ['Transactional', 'Promotional'], 'Transactional'),
                        ],
                    ]),
                ]);

                $id = $result['MessageId'] ?? null;
                $sent[] = new SentMessage($to, is_string($id) ? $id : null, 'accepted');
            } catch (AwsException $e) {
                $failed[] = $to;
                $error ??= new DeliveryException(
                    'Failed to send SMS via AWS SNS: '.($e->getAwsErrorMessage() ?? $e->getAwsErrorCode() ?? 'unknown error'),
                    $this->label,
                    previous: $e,
                );
            }
        }

        if ($error !== null) {
            throw new DeliveryException($error->getMessage(), $this->label, $failed, new SmsResult($sent), 0, $error->getPrevious());
        }

        return new SmsResult($sent);
    }

    protected function client(): SnsClient
    {
        $options = ['version' => '2010-03-31', 'region' => $this->value('region')];

        if ($this->string('key') !== null && $this->string('secret') !== null) {
            $options['credentials'] = ['key' => $this->value('key'), 'secret' => $this->value('secret')];
        }

        return $this->client ??= new SnsClient($options);
    }
}
