<?php

namespace MrRijal\LaravelSms\Tests;

use MrRijal\LaravelSms\Drivers\FakeDriver;
use MrRijal\LaravelSms\SmsMessage;

class FakeDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeDriver::reset();
    }

    protected function tearDown(): void
    {
        FakeDriver::reset();
        parent::tearDown();
    }

    public function test_implements_sms_provider(): void
    {
        $driver = new FakeDriver([]);

        $this->assertInstanceOf(\MrRijal\LaravelSms\Contracts\SmsProvider::class, $driver);
    }

    public function test_send_stores_plain_message(): void
    {
        $driver = new FakeDriver([]);
        $message = (new SmsMessage)->to('9812345678')->message('Hello world');

        $result = $driver->send($message);

        $this->assertTrue($result);
        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame(['9812345678'], FakeDriver::$messages[0]['to']);
        $this->assertSame('Hello world', FakeDriver::$messages[0]['message']);
        $this->assertNull(FakeDriver::$messages[0]['template']);
        $this->assertSame([], FakeDriver::$messages[0]['vars']);
    }

    public function test_send_stores_template_message(): void
    {
        $driver = new FakeDriver([]);
        $message = (new SmsMessage)
            ->to('9812345678')
            ->template('TPL_OTP', ['otp' => 123456]);

        $result = $driver->send($message);

        $this->assertTrue($result);
        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame(['9812345678'], FakeDriver::$messages[0]['to']);
        $this->assertNull(FakeDriver::$messages[0]['message']);
        $this->assertSame('TPL_OTP', FakeDriver::$messages[0]['template']);
        $this->assertSame(['otp' => 123456], FakeDriver::$messages[0]['vars']);
    }

    public function test_send_stores_multiple_recipients(): void
    {
        $driver = new FakeDriver([]);
        $message = (new SmsMessage)
            ->to(['9812345678', '9800000000'])
            ->message('Bulk message');

        $driver->send($message);

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertSame(['9812345678', '9800000000'], FakeDriver::$messages[0]['to']);
    }

    public function test_send_appends_to_messages_array(): void
    {
        $driver = new FakeDriver([]);
        $driver->send((new SmsMessage)->to('9811111111')->message('First'));
        $driver->send((new SmsMessage)->to('9822222222')->message('Second'));

        $this->assertCount(2, FakeDriver::$messages);
        $this->assertSame('First', FakeDriver::$messages[0]['message']);
        $this->assertSame('Second', FakeDriver::$messages[1]['message']);
    }

    public function test_reset_clears_all_messages(): void
    {
        $driver = new FakeDriver([]);
        $driver->send((new SmsMessage)->to('9812345678')->message('Test'));

        $this->assertCount(1, FakeDriver::$messages);

        FakeDriver::reset();

        $this->assertCount(0, FakeDriver::$messages);
    }

    public function test_accepts_empty_config(): void
    {
        $driver = new FakeDriver([]);
        $message = (new SmsMessage)->to('9812345678')->message('Hello');

        $this->assertTrue($driver->send($message));
    }
}
