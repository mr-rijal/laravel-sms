<?php

namespace MrRijal\LaravelSms\Tests;

use MrRijal\LaravelSms\Facades\SMS;
use MrRijal\LaravelSms\Drivers\FakeDriver;

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
        SMS::to('9800000000')
            ->message('Hello Test')
            ->sendNow();

        $this->assertCount(1, FakeDriver::$messages);
        $this->assertEquals('Hello Test', FakeDriver::$messages[0]['message']);
    }

    public function test_can_send_template_sms()
    {
        SMS::to('9800000000')
            ->template('TEMPLATE123', ['otp' => 1234])
            ->sendNow();

        $this->assertEquals('TEMPLATE123', FakeDriver::$messages[0]['template']);
        $this->assertEquals(['otp' => 1234], FakeDriver::$messages[0]['vars']);
    }
}
