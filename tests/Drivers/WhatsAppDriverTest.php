<?php

namespace MrRijal\LaravelSms\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\Drivers\WhatsAppDriver;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\Tests\TestCase;

class WhatsAppDriverTest extends TestCase
{
    public function test_implements_sms_provider(): void
    {
        $driver = new WhatsAppDriver([
            'phone_number_id' => '123',
            'access_token' => 'token',
        ]);

        $this->assertInstanceOf(SmsProvider::class, $driver);
    }

    public function test_constructor_throws_when_phone_number_id_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WhatsApp Business API configuration is incomplete');

        new WhatsAppDriver(['access_token' => 'token']);
    }

    public function test_constructor_throws_when_access_token_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WhatsApp Business API configuration is incomplete');

        new WhatsAppDriver(['phone_number_id' => '123']);
    }

    public function test_constructor_succeeds_with_valid_config(): void
    {
        $driver = new WhatsAppDriver([
            'phone_number_id' => '123',
            'access_token' => 'token',
        ]);

        $this->assertInstanceOf(WhatsAppDriver::class, $driver);
    }

    public function test_send_throws_when_no_text_or_template(): void
    {
        $driver = new WhatsAppDriver([
            'phone_number_id' => '123',
            'access_token' => 'token',
        ]);
        $message = (new SmsMessage)->to('+15551234567');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message text or template ID is required');

        $driver->send($message);
    }

    public function test_send_throws_when_text_and_template_are_whitespace_only(): void
    {
        $driver = new WhatsAppDriver([
            'phone_number_id' => '123',
            'access_token' => 'token',
        ]);
        $message = (new SmsMessage)->to('+15551234567');
        $textProp = new \ReflectionProperty(SmsMessage::class, 'text');
        $textProp->setAccessible(true);
        $textProp->setValue($message, '  ');
        $tplProp = new \ReflectionProperty(SmsMessage::class, 'templateId');
        $tplProp->setAccessible(true);
        $tplProp->setValue($message, "\t");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message text or template ID is required');

        $driver->send($message);
    }

    public function test_send_text_success_with_mock_client(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'messages' => [['id' => 'wamid.123']],
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new WhatsAppDriver([
            'phone_number_id' => '123',
            'access_token' => 'token',
        ], $client);
        $message = (new SmsMessage)->to('+15551234567')->message('Hello WhatsApp');

        $result = $driver->send($message);

        $this->assertTrue($result);
    }

    public function test_send_uses_text_path_when_template_id_is_whitespace_only(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'messages' => [['id' => 'wamid.789']],
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new WhatsAppDriver([
            'phone_number_id' => '123',
            'access_token' => 'token',
        ], $client);
        $message = (new SmsMessage)->to('+15551234567')->message('Hello WhatsApp');
        $tplProp = new \ReflectionProperty(SmsMessage::class, 'templateId');
        $tplProp->setAccessible(true);
        $tplProp->setValue($message, '   ');

        $this->assertTrue($driver->send($message));
    }

    public function test_send_template_success_with_mock_client(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'messages' => [['id' => 'wamid.456']],
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $driver = new WhatsAppDriver([
            'phone_number_id' => '123',
            'access_token' => 'token',
        ], $client);
        $message = (new SmsMessage)->to('+15551234567')->template('hello_world', ['0' => 'John']);

        $result = $driver->send($message);

        $this->assertTrue($result);
    }
}
