<?php

namespace MrRijal\LaravelSms\Tests;

use MrRijal\LaravelSms\Exceptions\InvalidMessageException;
use MrRijal\LaravelSms\Exceptions\SmsException;
use MrRijal\LaravelSms\SmsMessage;
use PHPUnit\Framework\TestCase as BaseTestCase;

class SmsMessageTest extends BaseTestCase
{
    public function test_recipients_are_normalized_and_deduplicated(): void
    {
        $message = (new SmsMessage)->to(['+1 (555) 123-0001', '+1.555.123.0001', '9812345678', '  ']);

        $this->assertSame(['+15551230001', '9812345678'], $message->getTo());
    }

    public function test_accepts_any_iterable_of_numbers(): void
    {
        $message = (new SmsMessage)->to(collect(['+15551230001', '+15551230002']));

        $this->assertCount(2, $message->getTo());
    }

    public function test_rejects_invalid_numbers(): void
    {
        $this->expectException(InvalidMessageException::class);
        $this->expectExceptionMessage('Invalid phone number format: 12ab');

        (new SmsMessage)->to('12ab');
    }

    public function test_rejects_empty_and_overlong_text(): void
    {
        try {
            (new SmsMessage)->message('   ');
            $this->fail('Expected exception.');
        } catch (InvalidMessageException $e) {
            $this->assertSame('Message text cannot be empty', $e->getMessage());
        }

        $this->expectExceptionMessage('cannot exceed 1600 characters');
        (new SmsMessage)->message(str_repeat('é', 1601));
    }

    public function test_length_is_counted_in_characters_not_bytes(): void
    {
        $this->assertSame(1600, mb_strlen((string) (new SmsMessage)->message(str_repeat('é', 1600))->getText()));
    }

    public function test_template_sender_and_provider(): void
    {
        $message = SmsMessage::create()->template('OTP', ['code' => 1])->from(' ACME ')->provider('twilio');

        $this->assertSame('OTP', $message->getTemplateId());
        $this->assertSame(['code' => 1], $message->getVariables());
        $this->assertSame('ACME', $message->getFrom());
        $this->assertSame('twilio', $message->getProvider());
        $this->assertNull((new SmsMessage)->from('  ')->getFrom());
    }

    public function test_validate_requires_recipient_and_content(): void
    {
        try {
            (new SmsMessage)->message('Hi')->validate();
            $this->fail('Expected exception.');
        } catch (InvalidMessageException $e) {
            $this->assertSame('At least one recipient is required', $e->getMessage());
        }

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('Message text or template ID is required');
        (new SmsMessage)->to('9812345678')->validate();
    }

    public function test_for_recipient_copies_without_mutating_the_original(): void
    {
        $message = SmsMessage::create('Hi')->to(['9812345678', '9800000000']);

        $this->assertSame(['9800000000'], $message->forRecipient('9800000000')->getTo());
        $this->assertSame(['9812345678'], $message->forRecipients(['9812345678'])->getTo());
        $this->assertSame(['9812345678', '9800000000'], $message->getTo());
    }

    public function test_redacts_numbers_for_logging(): void
    {
        $this->assertSame(['***5678'], (new SmsMessage)->to('9812345678')->getRedactedRecipientsForLogging());
        $this->assertSame('***', SmsMessage::redact('12'));
    }
}
