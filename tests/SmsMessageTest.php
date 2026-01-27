<?php

namespace MrRijal\LaravelSms\Tests;

use MrRijal\LaravelSms\SmsMessage;
use PHPUnit\Framework\TestCase as BaseTestCase;

class SmsMessageTest extends BaseTestCase
{
    public function test_to_accepts_single_number(): void
    {
        $message = new SmsMessage;
        $message->to('9812345678');

        $this->assertSame(['9812345678'], $message->getTo());
    }

    public function test_to_accepts_array_of_numbers(): void
    {
        $message = new SmsMessage;
        $message->to(['9812345678', '9800000000']);

        $this->assertSame(['9812345678', '9800000000'], $message->getTo());
    }

    public function test_to_accepts_numbers_with_plus_prefix(): void
    {
        $message = new SmsMessage;
        $message->to('+9812345678');

        $this->assertSame(['+9812345678'], $message->getTo());
    }

    public function test_to_merges_and_deduplicates_recipients(): void
    {
        $message = new SmsMessage;
        $message->to('9812345678');
        $message->to(['9812345678', '9800000000']);

        $this->assertCount(2, $message->getTo());
        $this->assertContains('9812345678', $message->getTo());
        $this->assertContains('9800000000', $message->getTo());
    }

    public function test_to_throws_for_invalid_number_format(): void
    {
        $message = new SmsMessage;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid phone number format');

        $message->to('invalid');
    }

    public function test_to_throws_for_too_short_number(): void
    {
        $message = new SmsMessage;

        $this->expectException(\InvalidArgumentException::class);

        $message->to('123'); // less than 7 digits
    }

    public function test_message_sets_text(): void
    {
        $message = new SmsMessage;
        $message->message('Hello world');

        $this->assertSame('Hello world', $message->getText());
    }

    public function test_message_trimmed(): void
    {
        $message = new SmsMessage;
        $message->message('  Hello  ');

        $this->assertSame('Hello', $message->getText());
    }

    public function test_message_throws_when_empty(): void
    {
        $message = new SmsMessage;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message text cannot be empty');

        $message->message('');
    }

    public function test_message_throws_when_exceeds_1600_chars(): void
    {
        $message = new SmsMessage;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot exceed 1600 characters');

        $message->message(str_repeat('x', 1601));
    }

    public function test_template_sets_id_and_variables(): void
    {
        $message = new SmsMessage;
        $message->template('TPL_123', ['otp' => 123456]);

        $this->assertSame('TPL_123', $message->getTemplateId());
        $this->assertSame(['otp' => 123456], $message->getVariables());
    }

    public function test_template_throws_when_id_empty(): void
    {
        $message = new SmsMessage;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Template ID cannot be empty');

        $message->template('');
    }

    public function test_validate_throws_when_no_recipients(): void
    {
        $message = new SmsMessage;
        $message->message('Hello');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one recipient is required');

        $message->validate();
    }

    public function test_validate_throws_when_no_text_or_template(): void
    {
        $message = new SmsMessage;
        $message->to('9812345678');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message text or template ID is required');

        $message->validate();
    }

    public function test_validate_passes_with_text_and_recipient(): void
    {
        $message = (new SmsMessage)->to('9812345678')->message('Hello');

        $message->validate();
        $this->assertTrue(true);
    }

    public function test_validate_passes_with_template_and_recipient(): void
    {
        $message = (new SmsMessage)->to('9812345678')->template('TPL_1', []);

        $message->validate();
        $this->assertTrue(true);
    }
}
