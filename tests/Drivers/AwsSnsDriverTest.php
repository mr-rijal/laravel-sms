<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use MrRijal\LaravelSms\Drivers\AwsSnsDriver;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;

class AwsSnsDriverTest extends TestCase
{
    public function test_implements_sms_provider(): void
    {
        $driver = new AwsSnsDriver([
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'us-east-1',
        ]);

        $this->assertInstanceOf(\MrRijal\LaravelSms\Contracts\SmsProvider::class, $driver);
    }

    public function test_constructor_throws_when_key_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AWS SNS configuration is incomplete');

        new AwsSnsDriver(['secret' => 'secret', 'region' => 'us-east-1']);
    }

    public function test_constructor_throws_when_secret_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AWS SNS configuration is incomplete');

        new AwsSnsDriver(['key' => 'key', 'region' => 'us-east-1']);
    }

    public function test_constructor_throws_when_region_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AWS SNS configuration is incomplete');

        new AwsSnsDriver(['key' => 'key', 'secret' => 'secret']);
    }

    public function test_constructor_succeeds_with_valid_config(): void
    {
        $driver = new AwsSnsDriver([
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'us-east-1',
        ]);

        $this->assertInstanceOf(AwsSnsDriver::class, $driver);
    }

    public function test_send_throws_when_no_text_or_template(): void
    {
        $driver = new AwsSnsDriver([
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'us-east-1',
        ]);
        $message = (new SmsMessage)->to('+15551234567');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message text or template ID is required');

        $driver->send($message);
    }

    public function test_send_throws_when_template_but_no_text(): void
    {
        $driver = new AwsSnsDriver([
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'us-east-1',
        ]);
        $message = (new SmsMessage)->to('+15551234567')->template('TPL_1', []);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AWS SNS requires message text');

        $driver->send($message);
    }

    public function test_send_accepts_optional_sns_client_for_testing(): void
    {
        $client = new class extends \Aws\Sns\SnsClient {
            public function __construct()
            {
                // Skip parent constructor for unit test double
            }

            public function publish(array $args = []): \Aws\Result
            {
                return new \Aws\Result(['MessageId' => 'test-msg-id']);
            }
        };

        $driver = new AwsSnsDriver([
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'us-east-1',
        ], $client);
        $message = (new SmsMessage)->to('+15551234567')->message('Hello AWS SNS');

        $result = $driver->send($message);

        $this->assertTrue($result);
    }
}
