<?php

namespace MrRijal\LaravelSms\Tests;

use MrRijal\LaravelSms\Facades\Sms;
use MrRijal\LaravelSms\Drivers\FakeDriver;
use MrRijal\LaravelSms\SmsMessage;

class SmsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeDriver::reset();

        config()->set('sms.default', 'fake');
        config()->set('sms.queue', false);
    }

    public function test_can_send_plain_sms()
    {
        Sms::to('+1234567890')
            ->message('Hello Test')
            ->sendNow();

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertEquals('Hello Test', FakeDriver::$messages[0]['message']);
        $this->assertContains('+1234567890', FakeDriver::$messages[0]['to']);
    }

    public function test_can_send_template_sms()
    {
        Sms::to('+1234567890')
            ->template('TEMPLATE123', ['otp' => 1234])
            ->sendNow();

        $this->assertEquals('TEMPLATE123', FakeDriver::$messages[0]['template']);
        $this->assertEquals(['otp' => 1234], FakeDriver::$messages[0]['vars']);
    }

    public function test_can_send_to_multiple_recipients()
    {
        Sms::to(['+1234567890', '+0987654321'])
            ->message('Hello Everyone')
            ->sendNow();

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertCount(2, FakeDriver::$messages[0]['to']);
        $this->assertContains('+1234567890', FakeDriver::$messages[0]['to']);
        $this->assertContains('+0987654321', FakeDriver::$messages[0]['to']);
    }

    public function test_state_resets_after_sending()
    {
        Sms::to('+1234567890')
            ->message('First Message')
            ->sendNow();

        Sms::to('+0987654321')
            ->message('Second Message')
            ->sendNow();

        $this->assertCount(2, FakeDriver::$messages);
        $this->assertEquals('First Message', FakeDriver::$messages[0]['message']);
        $this->assertEquals('Second Message', FakeDriver::$messages[1]['message']);
        $this->assertNotEquals(FakeDriver::$messages[0]['to'], FakeDriver::$messages[1]['to']);
    }

    public function test_validation_requires_recipient()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one recipient is required');

        Sms::message('Test message')
            ->sendNow();
    }

    public function test_validation_requires_message_or_template()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message text or template ID is required');

        Sms::to('+1234567890')
            ->sendNow();
    }

    public function test_validation_rejects_invalid_phone_number()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid phone number format');

        Sms::to('invalid-phone')
            ->message('Test')
            ->sendNow();
    }

    public function test_validation_rejects_empty_message()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message text cannot be empty');

        Sms::to('+1234567890')
            ->message('')
            ->sendNow();
    }

    public function test_validation_rejects_message_too_long()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SMS message cannot exceed 1600 characters');

        Sms::to('+1234567890')
            ->message(str_repeat('a', 1601))
            ->sendNow();
    }

    public function test_can_use_send_method_with_queue_disabled()
    {
        config()->set('sms.queue', false);

        Sms::to('+1234567890')
            ->message('Test')
            ->send();

        $this->assertCount(1, FakeDriver::$messages);
    }

    public function test_can_use_send_method_with_queue_enabled()
    {
        config()->set('sms.queue', true);

        // Reset messages before test
        FakeDriver::reset();

        $result = Sms::to('+1234567890')
            ->message('Test')
            ->send();

        $this->assertTrue($result);
        // When queued with sync connection, job runs immediately, so message will be in FakeDriver
        // This is expected behavior with sync queue connection
        $this->assertCount(1, FakeDriver::$messages);
    }

    public function test_can_use_provider_method()
    {
        Sms::provider('fake')
            ->to('+1234567890')
            ->message('Test')
            ->sendNow();

        $this->assertCount(1, FakeDriver::$messages);
    }
}
