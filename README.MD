# Laravel SMS

A unified, extensible SMS gateway for Laravel that supports multiple providers with a clean, consistent API.

Laravel SMS provides a clean API to send SMS using multiple providers. It supports Laravel Notifications, queues, template-based messages, events, macro extensions, random driver selection, user-level helpers, and webhook handling.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
- [Driver Documentation](#driver-documentation)
  - [Twilio](#twilio)
  - [MSG91](#msg91)
  - [Sparrow SMS](#sparrow-sms)
  - [Vonage (Nexmo)](#vonage-nexmo)
  - [AWS SNS](#aws-sns)
  - [WhatsApp Business API](#whatsapp-business-api)
  - [Fake Driver (Testing)](#fake-driver-testing)
- [Advanced Features](#advanced-features)
- [Webhooks](#webhooks)
- [Extending with Custom Drivers](#extending-with-custom-drivers)
- [Testing](#testing)
- [Development](#development)
- [Contributing](#contributing)
- [License](#license)

---

## Requirements

* PHP ^8.4
* Laravel ^12.0

---

## Installation

```bash
composer require mr-rijal/laravel-sms
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=sms-config
```

---

## Configuration

The configuration file is located at `config/sms.php`.

```php
return [
    'default' => env('SMS_PROVIDER', 'fake'),
    'queue' => false,
    
    'drivers' => [
        'twilio' => \MrRijal\LaravelSms\Drivers\TwilioDriver::class,
        'sparrow' => \MrRijal\LaravelSms\Drivers\SparrowDriver::class,
        'msg91' => \MrRijal\LaravelSms\Drivers\Msg91Driver::class,
        'vonage' => \MrRijal\LaravelSms\Drivers\VonageDriver::class,
        'whatsapp' => \MrRijal\LaravelSms\Drivers\WhatsAppDriver::class,
        'aws_sns' => \MrRijal\LaravelSms\Drivers\AwsSnsDriver::class,
        'fake' => \MrRijal\LaravelSms\Drivers\FakeDriver::class,
    ],
    
    'providers' => [
        // See driver documentation below for configuration details
    ],
    
    'random_drivers' => ['twilio', 'msg91'],
    
    'webhooks' => [
        'enabled' => env('SMS_WEBHOOKS_ENABLED', false),
        'middleware' => ['web'],
        // Provider-specific webhook configurations
    ],
];
```

---

## Basic Usage

### Sending a Message

```php
use MrRijal\LaravelSms\Facades\Sms;

Sms::to('9812345678')
    ->message('Hello from Laravel SMS')
    ->send();
```

### Send Immediately (Without Queue)

```php
Sms::to('9812345678')
    ->message('Urgent message')
    ->sendNow();
```

### Multiple Recipients

```php
Sms::to(['9812345678', '9800000000'])
    ->message('System maintenance tonight')
    ->send();
```

### Template Messages

```php
Sms::to('9812345678')
    ->template('1207161789456789012', [
        'otp' => 123456,
    ])
    ->send();
```

### Using Specific Provider

```php
Sms::provider('twilio')
    ->to('9812345678')
    ->message('Hello from Twilio')
    ->send();
```

### Random Driver Selection

```php
// Set 'random' as driver in config or at runtime
Sms::provider('random')
    ->to('9812345678')
    ->message('This will randomly pick a driver from config(random_drivers)')
    ->send();
```

### Send Later / Schedule

```php
// Queue message immediately
Sms::to('9812345678')->message('Queued SMS')->sendLater();

// Schedule message at specific time
Sms::to('9812345678')
    ->message('Scheduled SMS')
    ->sendLaterAt(now()->addMinutes(10));
```

---

## Driver Documentation

### Twilio

**Provider:** Twilio

**Features:**
- ✅ Plain text messages
- ❌ Template messages (not supported via this driver)
- ✅ Multiple recipients
- ✅ Webhook support

**Configuration:**

Add to your `.env` file:

```env
TWILIO_SID=your_account_sid
TWILIO_TOKEN=your_auth_token
TWILIO_FROM=+1234567890
```

Update `config/sms.php`:

```php
'providers' => [
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],
],
```

**Usage:**

```php
use MrRijal\LaravelSms\Facades\Sms;

// Send plain text message
Sms::provider('twilio')
    ->to('+1234567890')
    ->message('Hello from Twilio!')
    ->send();
```

**Notes:**
- Phone numbers should be in E.164 format (e.g., `+1234567890`)
- The `from` number must be a valid Twilio phone number or alphanumeric sender ID
- Twilio requires Account SID and Auth Token from your Twilio console

**Webhook Configuration:**

```php
'webhooks' => [
    'twilio' => [
        'secret' => env('TWILIO_WEBHOOK_SECRET'),
    ],
],
```

---

### MSG91

**Provider:** MSG91

**Features:**
- ✅ Plain text messages
- ✅ Template messages (Flow API)
- ✅ Multiple recipients
- ✅ Webhook support

**Configuration:**

Add to your `.env` file:

```env
MSG91_AUTHKEY=your_auth_key
MSG91_SENDER=your_sender_id
```

Update `config/sms.php`:

```php
'providers' => [
    'msg91' => [
        'authkey' => env('MSG91_AUTHKEY'),
        'sender' => env('MSG91_SENDER'),
    ],
],
```

**Usage:**

```php
use MrRijal\LaravelSms\Facades\Sms;

// Send plain text message
Sms::provider('msg91')
    ->to('9812345678')
    ->message('Hello from MSG91!')
    ->send();

// Send template message (Flow API)
Sms::provider('msg91')
    ->to('9812345678')
    ->template('YOUR_TEMPLATE_ID', [
        'otp' => 123456,
        'name' => 'John Doe',
    ])
    ->send();
```

**Notes:**
- MSG91 uses Flow API for template messages
- Template variables are passed as key-value pairs
- The sender ID must be approved by MSG91
- Plain text messages use transactional route (route 4)

**Webhook Configuration:**

```php
'webhooks' => [
    'msg91' => [
        'secret' => env('MSG91_WEBHOOK_SECRET'),
    ],
],
```

---

### Sparrow SMS

**Provider:** Sparrow SMS (Nepal)

**Features:**
- ✅ Plain text messages
- ❌ Template messages (not supported)
- ✅ Multiple recipients
- ✅ Webhook support

**Configuration:**

Add to your `.env` file:

```env
SPARROW_TOKEN=your_api_token
SPARROW_FROM=your_sender_id
```

Update `config/sms.php`:

```php
'providers' => [
    'sparrow' => [
        'token' => env('SPARROW_TOKEN'),
        'from' => env('SPARROW_FROM'),
    ],
],
```

**Usage:**

```php
use MrRijal\LaravelSms\Facades\Sms;

// Send plain text message
Sms::provider('sparrow')
    ->to('9812345678')
    ->message('Hello from Sparrow SMS!')
    ->send();
```

**Notes:**
- Sparrow SMS is primarily used in Nepal
- The sender ID must be registered with Sparrow SMS
- Phone numbers should be in local format (without country code) or international format

**Webhook Configuration:**

```php
'webhooks' => [
    'sparrow' => [
        'secret' => env('SPARROW_WEBHOOK_SECRET'),
    ],
],
```

---

### Vonage (Nexmo)

**Provider:** Vonage (formerly Nexmo)

**Features:**
- ✅ Plain text messages
- ❌ Template messages (not supported)
- ✅ Multiple recipients
- ✅ Webhook support

**Configuration:**

Add to your `.env` file:

```env
VONAGE_KEY=your_api_key
VONAGE_SECRET=your_api_secret
VONAGE_FROM=your_sender_id
```

Update `config/sms.php`:

```php
'providers' => [
    'vonage' => [
        'key' => env('VONAGE_KEY'),
        'secret' => env('VONAGE_SECRET'),
        'from' => env('VONAGE_FROM'),
    ],
],
```

**Usage:**

```php
use MrRijal\LaravelSms\Facades\Sms;

// Send plain text message
Sms::provider('vonage')
    ->to('1234567890')
    ->message('Hello from Vonage!')
    ->send();
```

**Notes:**
- Vonage requires API key and secret from your Vonage dashboard
- The `from` field can be an alphanumeric sender ID or a Vonage number
- Phone numbers should be in international format without the `+` sign

**Webhook Configuration:**

```php
'webhooks' => [
    'vonage' => [
        'secret' => env('VONAGE_WEBHOOK_SECRET'),
    ],
],
```

---

### AWS SNS

**Provider:** Amazon Web Services Simple Notification Service

**Features:**
- ✅ Plain text messages
- ❌ Template messages (not directly supported)
- ✅ Multiple recipients
- ✅ Sender ID configuration
- ✅ SMS type (Transactional/Promotional)

**Configuration:**

Add to your `.env` file:

```env
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_SNS_SENDER_ID=YourSenderID
AWS_SNS_SMS_TYPE=Transactional
```

Update `config/sms.php`:

```php
'providers' => [
    'aws_sns' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'sender_id' => env('AWS_SNS_SENDER_ID', ''),
        'sms_type' => env('AWS_SNS_SMS_TYPE', 'Transactional'), // Transactional or Promotional
    ],
],
```

**Usage:**

```php
use MrRijal\LaravelSms\Facades\Sms;

// Send plain text message
Sms::provider('aws_sns')
    ->to('+1234567890')
    ->message('Hello from AWS SNS!')
    ->send();
```

**Notes:**
- Phone numbers should be in E.164 format (e.g., `+1234567890`)
- SMS type can be `Transactional` or `Promotional`
- Sender ID is optional and may be restricted by AWS based on your account settings
- Requires AWS SDK for PHP (included as dependency)
- Make sure your AWS credentials have SNS publish permissions

**AWS IAM Permissions Required:**

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "sns:Publish"
            ],
            "Resource": "*"
        }
    ]
}
```

---

### WhatsApp Business API

**Provider:** Meta WhatsApp Business API

**Features:**
- ✅ Plain text messages
- ✅ Template messages
- ✅ Multiple recipients
- ✅ Webhook support (verification and status updates)
- ✅ Preview URL configuration
- ✅ Custom template language

**Configuration:**

Add to your `.env` file:

```env
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_ACCESS_TOKEN=your_access_token
WHATSAPP_BUSINESS_ACCOUNT_ID=your_business_account_id
WHATSAPP_API_VERSION=v21.0
WHATSAPP_TEMPLATE_LANGUAGE=en
WHATSAPP_PREVIEW_URL=false
WHATSAPP_DEFAULT_COUNTRY_CODE=+1
```

Update `config/sms.php`:

```php
'providers' => [
    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
        'preview_url' => env('WHATSAPP_PREVIEW_URL', false),
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', ''),
    ],
],
```

**Usage:**

```php
use MrRijal\LaravelSms\Facades\Sms;

// Send plain text message
Sms::provider('whatsapp')
    ->to('+1234567890')
    ->message('Hello from WhatsApp!')
    ->send();

// Send template message
Sms::provider('whatsapp')
    ->to('+1234567890')
    ->template('your_template_name', [
        '0' => 'John Doe',  // First parameter
        '1' => '123456',    // Second parameter
    ])
    ->send();
```

**Notes:**
- Phone numbers must be in E.164 format (e.g., `+1234567890`)
- Template messages require pre-approved templates in your WhatsApp Business account
- Template parameters should be passed as indexed array (0, 1, 2, etc.) or named keys
- Plain text messages can only be sent within the 24-hour messaging window after user interaction
- For outbound messages without prior interaction, use template messages
- The `default_country_code` is used if phone numbers don't include country code

**Webhook Configuration:**

```php
'webhooks' => [
    'whatsapp' => [
        'secret' => env('WHATSAPP_WEBHOOK_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],
],
```

**Webhook Setup:**

1. Configure webhook URL in Meta Developer Console: `https://yourdomain.com/laravel-sms/webhook/whatsapp`
2. Set the verify token in your `.env` file
3. Enable webhooks in `config/sms.php`:

```php
'webhooks' => [
    'enabled' => true,
    // ...
],
```

---

### Fake Driver (Testing)

**Provider:** Fake (for testing)

**Features:**
- ✅ Plain text messages
- ✅ Template messages
- ✅ Multiple recipients
- ✅ Message storage for assertions

**Configuration:**

No configuration required. The fake driver is used for testing and development.

```php
'providers' => [
    'fake' => [],
],
```

**Usage:**

```php
use MrRijal\LaravelSms\Facades\Sms;
use MrRijal\LaravelSms\Drivers\FakeDriver;

// Send message (stored in FakeDriver::$messages)
Sms::provider('fake')
    ->to('9812345678')
    ->message('Test message')
    ->send();

// Access sent messages
$messages = FakeDriver::$messages;

// Reset messages
FakeDriver::reset();
```

**Testing Example:**

```php
use MrRijal\LaravelSms\Facades\Sms;
use MrRijal\LaravelSms\Drivers\FakeDriver;

public function test_sms_sending()
{
    FakeDriver::reset();
    
    Sms::provider('fake')
        ->to('9812345678')
        ->message('Test message')
        ->send();
    
    $this->assertCount(1, FakeDriver::$messages);
    $this->assertEquals('9812345678', FakeDriver::$messages[0]['to'][0]);
    $this->assertEquals('Test message', FakeDriver::$messages[0]['message']);
}
```

---

## Advanced Features

### Laravel Notifications

#### Using `SmsNotification`

```php
use MrRijal\LaravelSms\Notifications\SmsNotification;

$user->notify(new SmsNotification(
    message: 'Your order has been shipped'
));
```

#### Template Notification

```php
$user->notify(new SmsNotification(
    templateId: '1207161789456789012',
    variables: ['otp' => 456789]
));
```

### User Model Helpers

Attach SMS capability directly to your User model:

```php
use MrRijal\LaravelSms\Traits\HasSms;

class User extends Authenticatable
{
    use HasSms;

    public function routeNotificationForSms(): string
    {
        return $this->phone;
    }
}
```

Usage:

```php
Auth::user()->sms('Welcome to the app');

$user->sendSms('Password changed');

$user->smsTemplate('1207161789456789012', ['otp' => 987654]);
```

### Events

The package fires two events:

* `SmsSending` - Fired before sending SMS
* `SmsSent` - Fired after sending SMS (success or failure)

Listen for them:

```php
use MrRijal\LaravelSms\Events\SmsSending;
use MrRijal\LaravelSms\Events\SmsSent;

Event::listen(SmsSending::class, function ($event) {
    // Logging, auditing, blocking, etc.
    Log::info('Sending SMS', [
        'provider' => $event->provider,
        'recipients' => $event->message->getTo(),
    ]);
});

Event::listen(SmsSent::class, function ($event) {
    if ($event->success) {
        Log::info('SMS sent successfully', [
            'provider' => $event->provider,
        ]);
    } else {
        Log::error('SMS failed', [
            'provider' => $event->provider,
            'error' => $event->error,
        ]);
    }
});
```

### Macro Support

The package supports **facade macros**, so developers can extend your package without touching core code:

```php
use MrRijal\LaravelSms\Facades\Sms;

Sms::macro('sendOtp', function ($number, $otp) {
    return $this->to($number)
                ->template('OTP_TEMPLATE', ['otp' => $otp])
                ->sendNow();
});

// Usage
Sms::sendOtp('9812345678', 123456);
```

---

## Webhooks

The package supports webhooks for receiving status updates and incoming messages from SMS/WhatsApp providers.

### Enable Webhooks

1. Enable webhooks in `config/sms.php`:

```php
'webhooks' => [
    'enabled' => env('SMS_WEBHOOKS_ENABLED', true),
    'middleware' => ['web'],
],
```

2. Configure provider-specific webhook secrets:

```php
'webhooks' => [
    'twilio' => [
        'secret' => env('TWILIO_WEBHOOK_SECRET'),
    ],
    'whatsapp' => [
        'secret' => env('WHATSAPP_WEBHOOK_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],
    // ... other providers
],
```

### Webhook Routes

Webhooks are automatically registered at:

```
POST /laravel-sms/webhook/{provider}
GET  /laravel-sms/webhook/{provider}  (for WhatsApp verification)
```

### Listening to Webhook Events

```php
use MrRijal\LaravelSms\Events\SmsWebhookReceived;

Event::listen(SmsWebhookReceived::class, function ($event) {
    Log::info('Webhook received', [
        'provider' => $event->provider,
        'message_id' => $event->messageId,
        'status' => $event->status,
        'recipient' => $event->recipient,
        'payload' => $event->payload,
    ]);
    
    // Update your database, send notifications, etc.
});
```

### Provider-Specific Webhook Setup

#### WhatsApp

1. Set webhook URL in Meta Developer Console: `https://yourdomain.com/laravel-sms/webhook/whatsapp`
2. Configure verify token in `.env`:

```env
WHATSAPP_VERIFY_TOKEN=your_verify_token
WHATSAPP_WEBHOOK_SECRET=your_webhook_secret
```

#### Twilio

1. Set webhook URL in Twilio Console: `https://yourdomain.com/laravel-sms/webhook/twilio`
2. Configure webhook secret in `.env`:

```env
TWILIO_WEBHOOK_SECRET=your_webhook_secret
```

---

## Extending with Custom Drivers

### Step 1: Create a Driver

Create a driver class that implements `SmsProvider`:

```php
<?php

namespace App\SmsDrivers;

use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class MySmsDriver implements SmsProvider
{
    public function __construct(protected array $config)
    {
        // Validate configuration
        if (empty($config['api_key'])) {
            throw new \InvalidArgumentException('MySMS configuration is incomplete');
        }
    }

    public function send(SmsMessage $message): bool
    {
        // Use $message->getTo() for recipients
        // Use $message->getText() for message text
        // Use $message->getTemplateId() for template ID
        // Use $message->getVariables() for template variables
        
        // Implement your SMS sending logic here
        // Return true on success, throw exception on failure
        
        return true;
    }
}
```

### Step 2: Add Driver to Config

```php
'drivers' => [
    // ...
    'mysms' => \App\SmsDrivers\MySmsDriver::class,
],

'providers' => [
    // ...
    'mysms' => [
        'api_key' => env('MYSMS_API_KEY'),
        'api_secret' => env('MYSMS_API_SECRET'),
    ],
],
```

### Step 3: Use the Driver

```php
Sms::provider('mysms')
    ->to('9812345678')
    ->message('Hello from MySMS')
    ->sendNow();
```

### Optional: Add Webhook Support

If your provider supports webhooks, implement the `handleWebhook` method:

```php
use Illuminate\Http\Request;
use Illuminate\Http\Response;

public function handleWebhook(Request $request): Response
{
    // Verify webhook signature
    // Parse webhook payload
    // Dispatch SmsWebhookReceived event
    
    Event::dispatch(new SmsWebhookReceived(
        provider: 'mysms',
        payload: $request->all(),
        messageId: $parsedData['message_id'] ?? null,
        status: $parsedData['status'] ?? null,
        recipient: $parsedData['recipient'] ?? null
    ));
    
    return response('OK', 200);
}
```

---

## Testing

### Using the Fake Driver

Use the `FakeDriver` for local testing:

```php
Sms::provider('fake')
    ->to('9800000000')
    ->message('Test')
    ->sendNow();
```

### Testing in Your Application

```php
use MrRijal\LaravelSms\Facades\Sms;
use MrRijal\LaravelSms\Drivers\FakeDriver;

public function test_sms_notification()
{
    FakeDriver::reset();
    
    $user = User::factory()->create(['phone' => '9812345678']);
    $user->notify(new SmsNotification('Test message'));
    
    $this->assertCount(1, FakeDriver::$messages);
    $this->assertEquals('9812345678', FakeDriver::$messages[0]['to'][0]);
}
```

### Run the Test Suite

```bash
vendor/bin/phpunit
```

---

## Development

### Code Formatting

This package uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting.

Format code:
```bash
composer format
```

Check formatting (without making changes):
```bash
composer format:test
```

---

## Contributing

See `CONTRIBUTING.md` for contribution guidelines.

---

## License

MIT License
