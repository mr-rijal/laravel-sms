# Laravel SMS

[![Workflow](https://img.shields.io/github/actions/workflow/status/mr-rijal/laravel-sms/.github%2Fworkflows%2Fci.yml
)](https://github.com/mr-rijal/laravel-sms/actions)
[![Latest Version](https://img.shields.io/packagist/v/mr-rijal/laravel-sms.svg)](https://packagist.org/packages/mr-rijal/laravel-sms)
![PHP](https://img.shields.io/badge/PHP-8.2%20%E2%80%93%208.5-777BB4?style=flat&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-FF2D20?style=flat&logo=laravel&logoColor=white)
[![Latest Stable Version](https://poser.pugx.org/mr-rijal/laravel-sms/v)](https://packagist.org/packages/mr-rijal/laravel-sms)
[![License](https://img.shields.io/packagist/l/mr-rijal/laravel-sms)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/mr-rijal/laravel-sms.svg)](https://packagist.org/packages/mr-rijal/laravel-sms)
[![Star](https://img.shields.io/packagist/stars/mr-rijal/laravel-sms)](https://github.com/mr-rijal/laravel-sms)

A unified, extensible SMS gateway for Laravel. Send SMS via Twilio, Vonage, MSG91, Sparrow, AWS SNS, WhatsApp, Telnyx, Plivo, Infobip, Sendchamp, Termii, Africa's Talking, BulkSMS, or your own driver—with one simple API.

## Features

- **Multiple drivers** — Twilio, Vonage (Nexmo), MSG91, Sparrow SMS, AWS SNS, WhatsApp Business API, Telnyx, Plivo, Infobip, Sendchamp, Termii, Africa's Talking, and BulkSMS
- **Laravel Notifications** — Use the `sms` channel and `SmsNotification` out of the box
- **Queue & schedule** — `sendLater()` and `sendLaterAt()` for queued and scheduled messages
- **Templates** — Template IDs and variables for providers that support them (e.g. MSG91, WhatsApp)
- **Events** — `SmsSending` and `SmsSent` for logging, auditing, or blocking
- **Macros** — Extend the facade with custom methods (e.g. `sendOtp()`)
- **Random driver** — Load balancing across multiple providers via `random_drivers`
- **Fake driver** — Test without hitting real APIs
- **Log driver** — Inspect outbound SMS payloads locally without sending them
- **Mirror drivers** — Send redacted SMS copies to Telegram, Discord, or Slack for development and operations
- **Custom drivers** — Implement `SmsProvider` and register your own gateway

## Requirements

| Laravel | PHP |
|---------|-----|
| 13.x | 8.3, 8.4, 8.5 |
| 12.x | 8.2, 8.3, 8.4, 8.5 |
| 11.x | 8.2, 8.3, 8.4, 8.5 |

- Guzzle 7.15.5+ or 8.x

> Laravel 11 is outside Laravel's security-support window and is kept for existing applications only. Use Laravel 12 or 13 for a security-supported deployment.

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

# Optional: queue every send() and route the jobs
SMS_QUEUE=false
SMS_QUEUE_CONNECTION=
SMS_QUEUE_NAME=

# Example: Twilio
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=

# Example: Sendchamp
SENDCHAMP_API_KEY=
SENDCHAMP_SENDER_NAME=Sendchamp
SENDCHAMP_ROUTE=dnd

# Example: Termii
TERMII_API_KEY=
TERMII_SENDER_ID=
TERMII_CHANNEL=generic

# Example: Africa's Talking
AFRICASTALKING_API_KEY=
AFRICASTALKING_USERNAME=sandbox
AFRICASTALKING_FROM=
AFRICASTALKING_SANDBOX=false

# Example: BulkSMS
BULKSMS_TOKEN_ID=
BULKSMS_TOKEN_SECRET=
BULKSMS_FROM=
```

## Configuration

The published config defines the default driver, queue behaviour, and credentials per provider. Main options:

| Key              | Description                                        |
|------------------|----------------------------------------------------|
| `default`        | Default driver name (e.g. `twilio`, `fake`)       |
| `queue`         | When `true`, `send()` queues instead of sending now|
| `queue_connection` / `queue_name` | Queue connection and queue used for `SendSmsJob` |
| `drivers`        | Map of driver names to driver classes              |
| `providers`      | Credentials per driver (keys, tokens, etc.)         |
| `random_drivers`| Drivers used when provider is `random`              |
| `webhooks`      | Webhook route, middleware, and per-provider secrets  |

Example `config/sms.php` layout:

```php
return [
    'default' => env('SMS_PROVIDER', 'fake'),
    'queue'   => env('SMS_QUEUE', false),

    'drivers' => [
        'twilio'         => \MrRijal\LaravelSms\Drivers\TwilioDriver::class,
        'sparrow'        => \MrRijal\LaravelSms\Drivers\SparrowDriver::class,
        'msg91'          => \MrRijal\LaravelSms\Drivers\Msg91Driver::class,
        'vonage'         => \MrRijal\LaravelSms\Drivers\VonageDriver::class,
        'aws_sns'        => \MrRijal\LaravelSms\Drivers\AwsSnsDriver::class,
        'whatsapp'       => \MrRijal\LaravelSms\Drivers\WhatsAppDriver::class,
        'sendchamp'      => \MrRijal\LaravelSms\Drivers\SendchampDriver::class,
        'termii'         => \MrRijal\LaravelSms\Drivers\TermiiDriver::class,
        'africastalking' => \MrRijal\LaravelSms\Drivers\AfricasTalkingDriver::class,
        'bulksms'        => \MrRijal\LaravelSms\Drivers\BulkSmsDriver::class,
        'telnyx'         => \\MrRijal\\LaravelSms\\Drivers\\TelnyxDriver::class,
        'plivo'          => \\MrRijal\\LaravelSms\\Drivers\\PlivoDriver::class,
        'infobip'        => \\MrRijal\\LaravelSms\\Drivers\\InfobipDriver::class,
        'fake'           => \MrRijal\LaravelSms\Drivers\FakeDriver::class,
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

## Supported Drivers

| Driver | Key | Region | Env Variables |
|--------|-----|--------|---------------|
| [Twilio](https://www.twilio.com) | `twilio` | Global | `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM` |
| [Vonage](https://www.vonage.com) (Nexmo) | `vonage` | Global | `VONAGE_KEY`, `VONAGE_SECRET`, `VONAGE_FROM` |
| [MSG91](https://msg91.com) | `msg91` | India | `MSG91_AUTHKEY`, `MSG91_SENDER` |
| [Sparrow SMS](https://sparrowsms.com) | `sparrow` | Nepal | `SPARROW_TOKEN`, `SPARROW_FROM` |
| [AWS SNS](https://aws.amazon.com/sns/) | `aws_sns` | Global | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` |
| [WhatsApp Business](https://business.whatsapp.com) | `whatsapp` | Global | `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN` |
| [Sendchamp](https://sendchamp.com) | `sendchamp` | Nigeria / Africa | `SENDCHAMP_API_KEY`, `SENDCHAMP_SENDER_NAME`, `SENDCHAMP_ROUTE` |
| [Termii](https://termii.com) | `termii` | Nigeria / Africa | `TERMII_API_KEY`, `TERMII_SENDER_ID`, `TERMII_CHANNEL` |
| [Africa's Talking](https://africastalking.com) | `africastalking` | Pan-Africa | `AFRICASTALKING_API_KEY`, `AFRICASTALKING_USERNAME`, `AFRICASTALKING_FROM` |
| [BulkSMS](https://www.bulksms.com) | `bulksms` | Africa / Global | `BULKSMS_TOKEN_ID`, `BULKSMS_TOKEN_SECRET`, `BULKSMS_FROM` |
| [Telnyx](https://telnyx.com) | `telnyx` | Global | `TELNYX_API_KEY`, `TELNYX_FROM` or `TELNYX_MESSAGING_PROFILE_ID` |
| [Plivo](https://www.plivo.com) | `plivo` | Global | `PLIVO_AUTH_ID`, `PLIVO_AUTH_TOKEN`, `PLIVO_FROM` |
| [Infobip](https://www.infobip.com) | `infobip` | Global | `INFOBIP_API_KEY`, `INFOBIP_SENDER`, `INFOBIP_BASE_URL` |
| Log (development) | `log` | — | `SMS_LOG_CHANNEL` (optional) |
| Telegram / Discord / Slack mirrors | `telegram_mirror`, `discord_webhook`, `slack_webhook` | — | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `DISCORD_WEBHOOK_URL`, `SLACK_WEBHOOK_URL` |
| Fake (testing) | `fake` | — | — |

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

### Telnyx, Plivo, and Infobip

Configure the provider credentials in your environment, then select the driver in the usual way:

```env
TELNYX_API_KEY=
TELNYX_FROM=

PLIVO_AUTH_ID=
PLIVO_AUTH_TOKEN=
PLIVO_FROM=

INFOBIP_API_KEY=
INFOBIP_SENDER=
```

```php
Sms::provider('telnyx')->to('+15551234567')->message('Hello')->send();
Sms::provider('plivo')->to('+15551234567')->message('Hello')->send();
Sms::provider('infobip')->to('+15551234567')->message('Hello')->send();
```

Telnyx can use `TELNYX_MESSAGING_PROFILE_ID` instead of `TELNYX_FROM`. Plivo disables provider-side content and recipient logging by default; set `PLIVO_LOG_MESSAGES=true` only when that retention is necessary.

### Log driver

Use the `log` driver during local development to validate an SMS request and write its message text, template data, and redacted recipients to Laravel's debug log without sending it:

```php
Sms::provider('log')->to('+15551234567')->message('Debug message')->send();
```

Set `SMS_LOG_CHANNEL` to write to a dedicated log channel. Do not use this driver in production: message content and template variables can contain sensitive data.

### Mirror drivers

Telegram, Discord, and Slack mirror drivers send a copy of an outgoing SMS to one configured developer or operations channel. They do not deliver to the SMS recipient and should never be used as the primary customer-notification channel.

```env
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
DISCORD_WEBHOOK_URL=
SLACK_WEBHOOK_URL=
```

```php
Sms::provider('telegram_mirror')->to('+15551234567')->message('Debug message')->send();
Sms::provider('discord_webhook')->to('+15551234567')->message('Debug message')->send();
Sms::provider('slack_webhook')->to('+15551234567')->message('Debug message')->send();
```

Webhook URLs and bot tokens are secrets. Keep them in environment variables and restrict mirror drivers to trusted internal recipients.

### Queued and scheduled sending

Queued jobs implement `ShouldBeEncrypted`, so phone numbers and message text are encrypted with your `APP_KEY` while on the queue. Set `SMS_QUEUE_CONNECTION` / `SMS_QUEUE_NAME` to route them.

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

## Webhooks

Delivery reports and inbound messages are received at `POST /laravel-sms/webhook/{provider}` (route name `sms.webhook`). Enable the route and configure a secret for each provider you receive webhooks from:

```env
SMS_WEBHOOKS_ENABLED=true
SMS_WEBHOOKS_PREFIX=laravel-sms/webhook

WHATSAPP_WEBHOOK_SECRET=     # Meta app secret (X-Hub-Signature-256)
WHATSAPP_VERIFY_TOKEN=       # subscription verify token (GET challenge)
SENDCHAMP_WEBHOOK_SECRET=    # HMAC-SHA256, X-Sendchamp-Signature
TERMII_WEBHOOK_SECRET=       # HMAC-SHA512, X-Termii-Signature
BULKSMS_WEBHOOK_SECRET=      # HMAC-SHA256, X-BulkSMS-Signature
AFRICASTALKING_WEBHOOK_SECRET=
```

Every webhook **fails closed**: a request is rejected with `401` unless the provider's secret is configured and the request proves it. Africa's Talking does not sign callbacks, so add the secret to the callback URL you register with them:

```
https://example.com/laravel-sms/webhook/africastalking?token=<AFRICASTALKING_WEBHOOK_SECRET>
```

The token is removed from the payload before it reaches your listeners.

Webhook routes use the stateless `api` middleware group plus `throttle:120,1` (no sessions or CSRF). Change `sms.webhooks.middleware` in the published config to adjust it.

Verified webhooks dispatch `MrRijal\LaravelSms\Events\SmsWebhookReceived` with `provider`, `payload`, `messageId`, `status`, and `recipient`.

To receive webhooks in a custom driver, implement `MrRijal\LaravelSms\Contracts\HandlesWebhooks`.

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
| `SmsWebhookReceived` | After a verified provider webhook |

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

### Continuous integration

Pull requests and pushes to `main` run [GitHub Actions](https://github.com/mr-rijal/laravel-sms/actions): **Pint** (code style), **PHPStan** (Larastan), **Composer audit**, and **PHPUnit** across PHP 8.2–8.5 with Laravel 11–13 (Laravel 13 runs on PHP 8.3+ only, per Laravel’s requirement), plus **lowest-dependencies** jobs.

Run the same checks locally:

```bash
composer ci
```

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) and [Releases](https://github.com/mr-rijal/laravel-sms/releases).

## Contributing

Contributions are welcome. Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover a security issue, please email [prashantrijal.721@gmail.com](mailto:prashantrijal.721@gmail.com) instead of opening an issue. See [SECURITY.md](SECURITY.md) for the full policy.

## Credits

- [Prashant Rijal](https://github.com/mr-rijal)
- [All contributors](https://github.com/mr-rijal/laravel-sms/contributors)

## Support

If this package is useful to you, you can support its maintenance via [buymemomo.com/rijal](https://buymemomo.com/rijal).

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
