# Laravel SMS

[![Workflow](https://img.shields.io/github/actions/workflow/status/mr-rijal/laravel-sms/.github%2Fworkflows%2Fci.yml
)](https://github.com/mr-rijal/laravel-sms/actions)
[![Latest Version](https://img.shields.io/packagist/v/mr-rijal/laravel-sms.svg)](https://packagist.org/packages/mr-rijal/laravel-sms)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php&logoColor=white)
[![Latest Stable Version](https://poser.pugx.org/mr-rijal/laravel-sms/v)](https://packagist.org/packages/mr-rijal/laravel-sms)
[![License](https://img.shields.io/packagist/l/mr-rijal/laravel-sms)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/mr-rijal/laravel-sms.svg)](https://packagist.org/packages/mr-rijal/laravel-sms)
[![Star](https://img.shields.io/packagist/stars/mr-rijal/laravel-sms)](https://github.com/mr-rijal/laravel-sms)

A unified, extensible SMS gateway for Laravel. Send SMS via Twilio, Vonage, MSG91, Sparrow, AWS SNS, WhatsApp, or your own driver—with one simple API.

## Features

- **Multiple drivers** — Twilio, Vonage (Nexmo), MSG91, Sparrow SMS, AWS SNS, WhatsApp Business API
- **Laravel Notifications** — Use the `sms` channel and `SmsNotification` out of the box
- **Queue & schedule** — `sendLater()` and `sendLaterAt()` for queued and scheduled messages
- **Templates** — Template IDs and variables for providers that support them (e.g. MSG91, WhatsApp)
- **Events** — `SmsSending` and `SmsSent` for logging, auditing, or blocking
- **Macros** — Extend the facade with custom methods (e.g. `sendOtp()`)
- **Random driver** — Load balancing across multiple providers via `random_drivers`
- **Fake driver** — Test without hitting real APIs
- **Custom drivers** — Implement `SmsProvider` and register your own gateway

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or 12.x
- Guzzle HTTP (required by drivers that call APIs)

## Installation

Install the package via Composer:

```bash
composer require mr-rijal/laravel-sms
```

Publish the config file:

```bash
php artisan vendor:publish --tag=sms-config
```

This creates `config/sms.php`. Set your default driver and provider credentials in `.env`:

```env
SMS_PROVIDER=twilio

# Example: Twilio
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=
```

## Configuration

The published config defines the default driver, queue behaviour, and credentials per provider. Main options:

| Key              | Description                                        |
|------------------|----------------------------------------------------|
| `default`        | Default driver name (e.g. `twilio`, `fake`)       |
| `queue`         | When `true`, `send()` queues instead of sending now|
| `drivers`        | Map of driver names to driver classes              |
| `providers`      | Credentials per driver (keys, tokens, etc.)         |
| `random_drivers`| Drivers used when provider is `random`              |

Example `config/sms.php` layout:

```php
return [
    'default' => env('SMS_PROVIDER', 'fake'),
    'queue'   => env('SMS_QUEUE', false),

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
        // … other providers
    ],

    'random_drivers' => ['twilio', 'msg91'],
];
```

## Usage

### Sending a message

```php
use MrRijal\LaravelSms\Facades\Sms;

Sms::to('9812345678')
    ->message('Hello from Laravel SMS')
    ->send();
```

When `sms.queue` is `true`, `send()` queues the message. To send immediately:

```php
Sms::to('9812345678')
    ->message('Urgent message')
    ->sendNow();
```

### Using a specific provider

```php
Sms::provider('twilio')
    ->to('9812345678')
    ->message('Hello from Twilio')
    ->send();
```

### Multiple recipients

```php
Sms::to(['9812345678', '9800000000'])
    ->message('System maintenance tonight')
    ->send();
```

### Template messages

```php
Sms::to('9812345678')
    ->template('1207161789456789012', ['otp' => 123456])
    ->send();
```

`template()` takes a template ID and an array of variables. Supported where the driver implements it (e.g. MSG91, WhatsApp).

### Queued and scheduled sending

```php
// Queue and send when the worker runs
Sms::to('9812345678')->message('Queued SMS')->sendLater();

// Schedule for a specific time
Sms::to('9812345678')
    ->message('Scheduled SMS')
    ->sendLaterAt(now()->addMinutes(10));
```

### Random driver selection

Use the `random` provider to pick one of the configured drivers (e.g. for load balancing):

```php
Sms::provider('random')
    ->to('9812345678')
    ->message('Sent via a random driver from config(random_drivers)')
    ->send();
```

## Laravel Notifications

Use the built-in SMS channel and notification class.

### Via `SmsNotification`

```php
use MrRijal\LaravelSms\Notifications\SmsNotification;

$user->notify(new SmsNotification(message: 'Your order has been shipped'));

// With template
$user->notify(new SmsNotification(
    templateId: '1207161789456789012',
    variables: ['otp' => 456789]
));
```

### User model setup

Add the `HasSms` trait and `routeNotificationForSms()`:

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

Then you can do:

```php
$user->sms('Welcome to the app');
$user->sendSms('Password changed');
$user->smsTemplate('1207161789456789012', ['otp' => 987654]);
```

## Events

The package dispatches:

| Event        | When                        |
|-------------|-----------------------------|
| `SmsSending`| Before the driver sends     |
| `SmsSent`   | After send (success/failure)|

Example listener:

```php
use MrRijal\LaravelSms\Events\SmsSending;
use MrRijal\LaravelSms\Events\SmsSent;

Event::listen(SmsSending::class, function (SmsSending $event) {
    // Log, audit, or short-circuit sending
});

Event::listen(SmsSent::class, function (SmsSent $event) {
    if ($event->success) {
        // …
    } else {
        // $event->error …
    }
});
```

## Macros

Extend the `Sms` facade without editing the package:

```php
use MrRijal\LaravelSms\Facades\Sms;

Sms::macro('sendOtp', function (string $number, int $otp) {
    return $this->to($number)
        ->template('OTP_TEMPLATE', ['otp' => $otp])
        ->sendNow();
});

Sms::sendOtp('9812345678', 123456);
```

## Custom drivers

1. Implement `MrRijal\LaravelSms\Contracts\SmsProvider`:

```php
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsMessage;

class MySmsDriver implements SmsProvider
{
    public function __construct(protected array $config) {}

    public function send(SmsMessage $message): bool
    {
        // Use $message->getTo(), getText(), getTemplateId(), getVariables()
        return true;
    }
}
```

2. Register the driver and its config in `config/sms.php`:

```php
'drivers' => [
    // …
    'mysms' => \App\Sms\MySmsDriver::class,
],
'providers' => [
    // …
    'mysms' => [
        'api_key' => env('MYSMS_API_KEY'),
    ],
],
```

3. Use it:

```php
Sms::provider('mysms')
    ->to('9812345678')
    ->message('Hello from MySMS')
    ->sendNow();
```

## Testing

Use the `fake` driver so no real SMS are sent:

```php
Sms::provider('fake')
    ->to('9800000000')
    ->message('Test')
    ->sendNow();
```

In tests you can assert on `FakeDriver::$messages` and call `FakeDriver::reset()` between tests.

Run the test suite:

```bash
composer test
# or
vendor/bin/phpunit
```

## Changelog

Please see [Releases](https://github.com/mr-rijal/laravel-sms/releases) for a changelog.

## Contributing

Contributions are welcome. Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover a security issue, please email [prashantrijal.721@gmail.com](mailto:prashantrijal.721@gmail.com) instead of opening an issue.

## Credits

- [Prashant Rijal](https://github.com/mr-rijal)
- [All contributors](https://github.com/mr-rijal/laravel-sms/contributors)

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
