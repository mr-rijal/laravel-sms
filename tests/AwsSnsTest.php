<?php

namespace MrRijal\LaravelSms\Tests;

use Aws\Exception\AwsException;
use Aws\Result;
use Aws\Sns\SnsClient;
use Mockery;
use MrRijal\LaravelSms\Drivers\AwsSnsDriver;
use MrRijal\LaravelSms\SmsMessage;

class AwsSnsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_aws_sns_driver_requires_key_secret_and_region()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AWS SNS configuration is incomplete');

        new AwsSnsDriver([]);
    }

    public function test_aws_sns_driver_requires_key()
    {
        $this->expectException(\InvalidArgumentException::class);

        new AwsSnsDriver([
            'secret' => 'test_secret',
            'region' => 'us-east-1',
        ]);
    }

    public function test_aws_sns_driver_requires_secret()
    {
        $this->expectException(\InvalidArgumentException::class);

        new AwsSnsDriver([
            'key' => 'test_key',
            'region' => 'us-east-1',
        ]);
    }

    public function test_aws_sns_driver_requires_region()
    {
        $this->expectException(\InvalidArgumentException::class);

        new AwsSnsDriver([
            'key' => 'test_key',
            'secret' => 'test_secret',
        ]);
    }

    public function test_aws_sns_driver_can_send_text_message()
    {
        $mockClient = Mockery::mock(SnsClient::class);
        $mockResult = new Result([
            'MessageId' => 'test-message-id-123',
        ]);

        $mockClient->shouldReceive('publish')
            ->once()
            ->with(Mockery::on(function ($params) {
                return $params['PhoneNumber'] === '+1234567890'
                    && $params['Message'] === 'Test message';
            }))
            ->andReturn($mockResult);

        $driver = $this->createDriverWithMockClient($mockClient);

        $message = new SmsMessage;
        $message->to('+1234567890');
        $message->message('Test message');

        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    public function test_aws_sns_driver_can_send_to_multiple_recipients()
    {
        $mockClient = Mockery::mock(SnsClient::class);
        $mockResult = new Result([
            'MessageId' => 'test-message-id-123',
        ]);

        $mockClient->shouldReceive('publish')
            ->twice()
            ->andReturn($mockResult);

        $driver = $this->createDriverWithMockClient($mockClient);

        $message = new SmsMessage;
        $message->to(['+1234567890', '+0987654321']);
        $message->message('Test message');

        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    public function test_aws_sns_driver_includes_sender_id_when_configured()
    {
        $mockClient = Mockery::mock(SnsClient::class);
        $mockResult = new Result([
            'MessageId' => 'test-message-id-123',
        ]);

        $mockClient->shouldReceive('publish')
            ->once()
            ->with(Mockery::on(function ($params) {
                return isset($params['MessageAttributes']['AWS.SNS.SMS.SenderID'])
                    && $params['MessageAttributes']['AWS.SNS.SMS.SenderID']['StringValue'] === 'MySenderID';
            }))
            ->andReturn($mockResult);

        $driver = $this->createDriverWithMockClient($mockClient, [
            'key' => 'test_key',
            'secret' => 'test_secret',
            'region' => 'us-east-1',
            'sender_id' => 'MySenderID',
        ]);

        $message = new SmsMessage;
        $message->to('+1234567890');
        $message->message('Test message');

        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    public function test_aws_sns_driver_includes_sms_type_when_configured()
    {
        $mockClient = Mockery::mock(SnsClient::class);
        $mockResult = new Result([
            'MessageId' => 'test-message-id-123',
        ]);

        $mockClient->shouldReceive('publish')
            ->once()
            ->with(Mockery::on(function ($params) {
                return isset($params['MessageAttributes']['AWS.SNS.SMS.SMSType'])
                    && $params['MessageAttributes']['AWS.SNS.SMS.SMSType']['StringValue'] === 'Promotional';
            }))
            ->andReturn($mockResult);

        $driver = $this->createDriverWithMockClient($mockClient, [
            'key' => 'test_key',
            'secret' => 'test_secret',
            'region' => 'us-east-1',
            'sms_type' => 'Promotional',
        ]);

        $message = new SmsMessage;
        $message->to('+1234567890');
        $message->message('Test message');

        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    public function test_aws_sns_driver_includes_both_sender_id_and_sms_type()
    {
        $mockClient = Mockery::mock(SnsClient::class);
        $mockResult = new Result([
            'MessageId' => 'test-message-id-123',
        ]);

        $mockClient->shouldReceive('publish')
            ->once()
            ->with(Mockery::on(function ($params) {
                return isset($params['MessageAttributes']['AWS.SNS.SMS.SenderID'])
                    && isset($params['MessageAttributes']['AWS.SNS.SMS.SMSType'])
                    && $params['MessageAttributes']['AWS.SNS.SMS.SenderID']['StringValue'] === 'MySenderID'
                    && $params['MessageAttributes']['AWS.SNS.SMS.SMSType']['StringValue'] === 'Transactional';
            }))
            ->andReturn($mockResult);

        $driver = $this->createDriverWithMockClient($mockClient, [
            'key' => 'test_key',
            'secret' => 'test_secret',
            'region' => 'us-east-1',
            'sender_id' => 'MySenderID',
            'sms_type' => 'Transactional',
        ]);

        $message = new SmsMessage;
        $message->to('+1234567890');
        $message->message('Test message');

        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    public function test_aws_sns_driver_does_not_include_message_attributes_when_not_configured()
    {
        $mockClient = Mockery::mock(SnsClient::class);
        $mockResult = new Result([
            'MessageId' => 'test-message-id-123',
        ]);

        $mockClient->shouldReceive('publish')
            ->once()
            ->with(Mockery::on(function ($params) {
                return ! isset($params['MessageAttributes']);
            }))
            ->andReturn($mockResult);

        $driver = $this->createDriverWithMockClient($mockClient);

        $message = new SmsMessage;
        $message->to('+1234567890');
        $message->message('Test message');

        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    public function test_aws_sns_driver_requires_message_text()
    {
        $driver = $this->createDriver();

        $message = new SmsMessage;
        $message->to('+1234567890');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message text or template ID is required');

        $driver->send($message);
    }

    public function test_aws_sns_driver_rejects_template_without_message_text()
    {
        $driver = $this->createDriver();

        $message = new SmsMessage;
        $message->to('+1234567890');
        $message->template('TEMPLATE123', ['var' => 'value']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AWS SNS requires message text');

        $driver->send($message);
    }

    public function test_aws_sns_driver_handles_aws_exception()
    {
        $mockClient = Mockery::mock(SnsClient::class);
        $awsException = Mockery::mock(AwsException::class);
        $awsException->shouldReceive('getAwsErrorMessage')
            ->andReturn('Invalid phone number');
        $awsException->shouldReceive('getCode')
            ->andReturn(400);
        $awsException->shouldReceive('getMessage')
            ->andReturn('Invalid phone number');

        $mockClient->shouldReceive('publish')
            ->once()
            ->andThrow($awsException);

        $driver = $this->createDriverWithMockClient($mockClient);

        $message = new SmsMessage;
        $message->to('+1234567890');
        $message->message('Test message');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to send SMS via AWS SNS');

        $driver->send($message);
    }

    public function test_aws_sns_driver_handles_generic_exception()
    {
        $mockClient = Mockery::mock(SnsClient::class);

        $mockClient->shouldReceive('publish')
            ->once()
            ->andThrow(new \Exception('Network error'));

        $driver = $this->createDriverWithMockClient($mockClient);

        $message = new SmsMessage;
        $message->to('+1234567890');
        $message->message('Test message');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to send SMS via AWS SNS: Network error');

        $driver->send($message);
    }

    public function test_aws_sns_driver_throws_exception_when_no_message_id_returned()
    {
        $mockClient = Mockery::mock(SnsClient::class);
        $mockResult = new Result([]); // No MessageId

        $mockClient->shouldReceive('publish')
            ->once()
            ->andReturn($mockResult);

        $driver = $this->createDriverWithMockClient($mockClient);

        $message = new SmsMessage;
        $message->to('+1234567890');
        $message->message('Test message');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No message ID returned');

        $driver->send($message);
    }

    public function test_aws_sns_driver_uses_correct_region()
    {
        $mockClient = Mockery::mock(SnsClient::class);
        $mockResult = new Result([
            'MessageId' => 'test-message-id-123',
        ]);

        $mockClient->shouldReceive('publish')
            ->once()
            ->andReturn($mockResult);

        $driver = $this->createDriverWithMockClient($mockClient, [
            'key' => 'test_key',
            'secret' => 'test_secret',
            'region' => 'eu-west-1',
        ]);

        $message = new SmsMessage;
        $message->to('+1234567890');
        $message->message('Test message');

        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    /**
     * Create a driver instance with default config
     */
    protected function createDriver(): AwsSnsDriver
    {
        return new AwsSnsDriver([
            'key' => 'test_key',
            'secret' => 'test_secret',
            'region' => 'us-east-1',
        ]);
    }

    /**
     * Create a driver instance with a mocked SNS client
     */
    protected function createDriverWithMockClient(SnsClient $mockClient, ?array $config = null): AwsSnsDriver
    {
        $config = $config ?? [
            'key' => 'test_key',
            'secret' => 'test_secret',
            'region' => 'us-east-1',
        ];

        $driver = new AwsSnsDriver($config);

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($driver);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($driver, $mockClient);

        return $driver;
    }
}
