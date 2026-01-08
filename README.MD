# Laravel SMS

A unified, extensible SMS gateway for Laravel.

Laravel SMS provides a clean API to send SMS using multiple providers. It supports Laravel Notifications, queues, template-based messages, events, macro extensions, random driver selection, and user-level helpers.

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

    'default' => env('SMS_DRIVER', 'twilio'),

    'queue' => true,

    // Random driver selection for load balancing
    'random_drivers' => ['twilio', 'msg91'],

    'drivers' => [
        'twilio'  => \MrRijal\LaravelSms\Drivers\TwilioDriver::class,
        'sparrow' => \MrRijal\LaravelSms\Drivers\SparrowDriver::class,
        'msg91'   => \MrRijal\LaravelSms\Drivers\Msg91Driver::class,
        'vonage'  => \MrRijal\LaravelSms\Drivers\VonageDriver::class,
        'aws_sns' => \MrRijal\LaravelSms\Drivers\AwsSnsDriver::class,
        'fake'    => \MrRijal\LaravelSms\Drivers\FakeDriver::class,
    ],

    'providers' => [
        'twilio'  => [
            'sid'   => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from'  => env('TWILIO_FROM'),
        ],

        'sparrow' => [
            'token' => env('SPARROW_TOKEN'),
            'from'  => env('SPARROW_FROM'),
        ],

        'msg91' => [
            'authkey' => env('MSG91_AUTHKEY'),
            'sender'  => env('MSG91_SENDER'),
        ],

        'vonage' => [
            'key'    => env('VONAGE_KEY'),
            'secret' => env('VONAGE_SECRET'),
            'from'   => env('VONAGE_FROM'),
        ],

        'aws_sns' => [
            'key'      => env('AWS_ACCESS_KEY_ID'),
            'secret'   => env('AWS_SECRET_ACCESS_KEY'),
            'region'   => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'sender_id' => env('AWS_SNS_SENDER_ID', ''),
            'sms_type' => env('AWS_SNS_SMS_TYPE', 'Transactional'), // Transactional or Promotional
        ],

        'fake' => [], // For testing
    ],
];
```

---

## Basic Usage

### Sending a message

```php
use MrRijal\LaravelSms\Facades\Sms;

Sms::to('9812345678')
    ->message('Hello from Laravel SMS')
    ->send();
```

Send immediately (without queue):

```php
Sms::to('9812345678')
    ->message('Urgent message')
    ->sendNow();
```

---

## Send Later / Schedule

```php
// Queue message immediately
Sms::to('9812345678')->message('Queued SMS')->sendLater();

// Schedule message at specific time
Sms::to('9812345678')
    ->message('Scheduled SMS')
    ->sendLaterAt(now()->addMinutes(10));
```

---

## Multiple Recipients

```php
Sms::to(['9812345678', '9800000000'])
    ->message('System maintenance tonight')
    ->send();
```

---

## Template Messages

```php
Sms::to('9812345678')
    ->template('1207161789456789012', [
        'otp' => 123456,
    ])
    ->send();
```

* `template()` accepts a **template ID**
* Variables are passed as key-value pairs
* Works with providers like MSG91 or Sparrow

---

## Random Driver Selection

```php
// Set 'random' as driver in config or at runtime
Sms::provider('random')
    ->to('9812345678')
    ->message('This will randomly pick a driver from config(random_drivers)')
    ->send();
```

---

## Laravel Notifications

### Using `SmsNotification`

```php
use MrRijal\LaravelSms\Notifications\SmsNotification;

$user->notify(new SmsNotification(
    message: 'Your order has been shipped'
));
```

### Template notification

```php
$user->notify(new SmsNotification(
    templateId: '1207161789456789012',
    variables: ['otp' => 456789]
));
```

---

## User Model Helpers

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

---

## Events

The package fires two events:

* `SmsSending`
* `SmsSent`

Listen for them:

```php
use MrRijal\LaravelSms\Events\SmsSending;

Event::listen(SmsSending::class, function ($event) {
    // logging, auditing, blocking, etc.
});
```

---

## Macro Support

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

## Extending with a Custom Driver

### Step 1: Create a driver

```php
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class MySmsDriver implements SmsProvider
{
    public function send(SmsMessage $message): bool
    {
        // Use $message->to, $message->text, $message->templateId, $message->variables
        return true;
    }
}
```

### Step 2: Add driver to config

```php
'drivers' => [
    // ...
    'mysms' => \App\SmsDrivers\MySmsDriver::class,
],
```

### Step 3: Use the driver

```php
Sms::provider('mysms')
    ->to('9812345678')
    ->message('Hello from MySMS')
    ->sendNow();
```

---

## Testing

Use the `FakeDriver` for local testing:

```php
Sms::provider('fake')
    ->to('9800000000')
    ->message('Test')
    ->sendNow();
```

Run the test suite:

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
