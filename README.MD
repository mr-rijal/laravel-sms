# Laravel SMS

[![Workflow](https://img.shields.io/github/actions/workflow/status/mr-rijal/laravel-sms/.github%2Fworkflows%2Fci.yml)](https://github.com/mr-rijal/laravel-sms/actions)
[![Latest Version](https://img.shields.io/packagist/v/mr-rijal/laravel-sms.svg)](https://packagist.org/packages/mr-rijal/laravel-sms)
![PHP](https://img.shields.io/badge/PHP-8.2%20%E2%80%93%208.5-777BB4?style=flat&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-FF2D20?style=flat&logo=laravel&logoColor=white)
[![License](https://img.shields.io/packagist/l/mr-rijal/laravel-sms)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/mr-rijal/laravel-sms.svg)](https://packagist.org/packages/mr-rijal/laravel-sms)

One fluent API for sending SMS from Laravel through 20 providers, with failover, queueing, notifications, delivery webhooks and first-class testing.

```php
$result = Sms::to('+15551234567')->message('Your code is 4821')->sendNow();

$result?->firstId(); // the provider's message ID (null if an SmsSending listener cancelled the send)
```

## Highlights

- **20 providers**, from global platforms to regional gateways, plus `log`, `fake` and `failover` drivers.
- **Fast:** built on Laravel's HTTP client. Providers without a bulk endpoint get concurrent per-recipient requests (`Http::pool`), and bulk providers get one request for everyone.
- **Reliable:** every send returns an `SmsResult` with message IDs. Partial failures say exactly which recipients failed, and `failover` retries only those recipients on the next provider.
- **Secure:** webhooks fail closed with signature checks, queued jobs are encrypted, and the package never puts credentials in exception messages, URLs or cache keys.
- **Testable:** `Sms::fake()` with `assertSent()`, `assertSentTo()` and `assertQueued()`, and every driver works with `Http::fake()`.
- **Laravel-native:** Octane- and queue-safe (each send builds a fresh message), notification channel, events, macros, `Sms::extend()`, `php artisan sms:test`, and `php artisan about` integration.
- **Typed:** PHPStan at max level.

## Requirements

| Laravel | PHP |
|---------|-----|
| 13.x | 8.3 – 8.5 |
| 12.x | 8.2 – 8.5 |
| 11.x | 8.2 – 8.5 |

> Laravel 11 is past its security-support window. Use Laravel 12 or 13 in production.

## Installation

```bash
composer require mr-rijal/laravel-sms
php artisan vendor:publish --tag=sms-config
```

Pick a provider and add its credentials to `.env`:

```env
SMS_PROVIDER=twilio
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=+15550000000
```

Check the setup with a real message:

```bash
php artisan sms:test +15551234567
```

## Providers

| Provider | Key | Coverage | Environment variables |
|---|---|---|---|
| [Twilio](https://www.twilio.com) | `twilio` | Global | `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM` or `TWILIO_MESSAGING_SERVICE_SID` |
| [Vonage](https://www.vonage.com) | `vonage` | Global | `VONAGE_KEY`, `VONAGE_SECRET`, `VONAGE_FROM` |
| [Sinch](https://sinch.com) | `sinch` | Global | `SINCH_SERVICE_PLAN_ID`, `SINCH_API_TOKEN`, `SINCH_FROM`, `SINCH_REGION` |
| [Bird](https://bird.com) (MessageBird) | `bird` | Global | `BIRD_API_KEY`, `BIRD_FROM`, `BIRD_REGION`, `BIRD_CATEGORY` |
| [Plivo](https://www.plivo.com) | `plivo` | Global | `PLIVO_AUTH_ID`, `PLIVO_AUTH_TOKEN`, `PLIVO_FROM` |
| [Telnyx](https://telnyx.com) | `telnyx` | Global | `TELNYX_API_KEY`, `TELNYX_FROM` or `TELNYX_MESSAGING_PROFILE_ID` |
| [Infobip](https://www.infobip.com) | `infobip` | Global | `INFOBIP_API_KEY`, `INFOBIP_SENDER`, `INFOBIP_BASE_URL` |
| [ClickSend](https://www.clicksend.com) | `clicksend` | Global | `CLICKSEND_USERNAME`, `CLICKSEND_API_KEY`, `CLICKSEND_FROM` |
| [Clickatell](https://www.clickatell.com) | `clickatell` | Global | `CLICKATELL_API_KEY`, `CLICKATELL_FROM` |
| [Brevo](https://www.brevo.com) | `brevo` | Global | `BREVO_API_KEY`, `BREVO_SMS_SENDER`, `BREVO_SMS_TYPE` |
| [Bandwidth](https://www.bandwidth.com) | `bandwidth` | US / Canada | `BANDWIDTH_ACCOUNT_ID`, `BANDWIDTH_APPLICATION_ID`, `BANDWIDTH_CLIENT_ID`, `BANDWIDTH_CLIENT_SECRET`, `BANDWIDTH_FROM` |
| [SendPulse](https://sendpulse.com) | `sendpulse` | Global | `SENDPULSE_CLIENT_ID`, `SENDPULSE_CLIENT_SECRET`, `SENDPULSE_SENDER` |
| [Amazon SNS](https://aws.amazon.com/sns/) | `aws_sns` | Global | `AWS_DEFAULT_REGION` (+ keys, or an IAM role) — needs `composer require aws/aws-sdk-php` |
| [WhatsApp Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api) | `whatsapp` | Global | `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN` |
| [MSG91](https://msg91.com) | `msg91` | India | `MSG91_AUTHKEY`, `MSG91_SENDER` |
| [Sparrow SMS](https://sparrowsms.com) | `sparrow` | Nepal | `SPARROW_TOKEN`, `SPARROW_FROM` |
| [Termii](https://termii.com) | `termii` | Africa | `TERMII_API_KEY`, `TERMII_SENDER_ID`, `TERMII_CHANNEL` |
| [Sendchamp](https://sendchamp.com) | `sendchamp` | Africa | `SENDCHAMP_API_KEY`, `SENDCHAMP_SENDER_NAME`, `SENDCHAMP_ROUTE` |
| [Africa's Talking](https://africastalking.com) | `africastalking` | Africa | `AFRICASTALKING_API_KEY`, `AFRICASTALKING_USERNAME` |
| [BulkSMS](https://www.bulksms.com) | `bulksms` | Global | `BULKSMS_TOKEN_ID`, `BULKSMS_TOKEN_SECRET` |
| Failover | `failover` | — | `SMS_FAILOVER_DRIVERS=twilio,vonage` |
| Log (development) | `log` | — | `SMS_LOG_CHANNEL` (optional) |
| Fake (tests) | `fake` | — | — |

Templates (`->template($id, $vars)`) are supported by MSG91 (DLT flows), WhatsApp (approved templates) and Brevo (SMS templates). The other providers need message text.

## Sending

```php
use MrRijal\LaravelSms\Facades\Sms;

// Default provider
Sms::to('+15551234567')->message('Hello')->send();

// Several recipients, a specific provider and sender
Sms::provider('sinch')
    ->to(['+15551234567', '+15557654321'])
    ->from('ACME')
    ->message('Maintenance tonight at 10pm')
    ->send();

// Templates
Sms::to('+919812345678')->template('6512a...', ['otp' => 4821])->send();

// Build a message once and send it anywhere
$message = SmsMessage::create('Hello')->to('+15551234567');
Sms::sendNow($message);
```

Numbers may contain spaces, dashes, dots and parentheses; they are normalized before sending.

### Results and errors

`sendNow()` returns an `SmsResult`:

```php
$result = Sms::to($numbers)->message('Hi')->sendNow();

$result->provider;     // "twilio"
$result->recipients(); // accepted numbers
$result->ids();        // provider message IDs
foreach ($result->messages as $sent) {
    $sent->to; $sent->id; $sent->status;
}
```

When a provider rejects a recipient, a `DeliveryException` is thrown. It tells you exactly what happened:

```php
use MrRijal\LaravelSms\Exceptions\DeliveryException;

try {
    Sms::to($numbers)->message('Hi')->sendNow();
} catch (DeliveryException $e) {
    $e->failedRecipients;        // numbers that were not accepted
    $e->partial?->recipients();  // numbers that were accepted before the failure
}
```

Every package exception implements `MrRijal\LaravelSms\Exceptions\SmsException`. Invalid input throws `InvalidMessageException`, and bad configuration throws `InvalidConfigurationException`.

### Queueing

```php
Sms::to($numbers)->message('Hi')->sendLater();
Sms::to($numbers)->message('Hi')->sendLaterAt(now()->addMinutes(10));
```

Set `SMS_QUEUE=true` to make `send()` queue by default (then `send()` returns `null`). Queued messages are split into **one job per recipient**, so a provider error for one recipient never makes the retry re-send to the others. Jobs are encrypted with your `APP_KEY`.

SMS delivery is still at-least-once: if a provider accepts a message but the response is lost (a timeout, a dropped connection), the retry, or failover, can send it to that recipient again.

```env
SMS_QUEUE_CONNECTION=redis
SMS_QUEUE_NAME=sms
SMS_QUEUE_TRIES=3
SMS_QUEUE_TIMEOUT=120   # keep your queue's retry_after above this
```

### Failover and load balancing

```env
SMS_PROVIDER=failover
SMS_FAILOVER_DRIVERS=twilio,vonage,sinch
```

Failover tries each provider in order. If Twilio accepts 98 of 100 recipients, only the 2 failed numbers go to Vonage.

For simple load balancing, set the provider to `random` and list the pool in `sms.random_drivers`.

### Speed

Providers with a bulk endpoint (Sinch, Plivo, Infobip, ClickSend, Clickatell, Sparrow, Sendchamp, Africa's Talking, BulkSMS, SendPulse, MSG91 templates) receive **one request** for every recipient. The rest send per-recipient requests **concurrently**:

```env
SMS_HTTP_CONCURRENCY=10
SMS_HTTP_TIMEOUT=30
SMS_HTTP_CONNECT_TIMEOUT=10
```

OAuth providers (Bandwidth, SendPulse) cache their access tokens until shortly before they expire.

## Notifications

Return an `SmsMessage` (or a plain string) from `toSms()`:

```php
use MrRijal\LaravelSms\SmsMessage;

class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return SmsMessage::create("Order #{$this->order->id} has shipped.");
    }
}
```

Route notifications with `routeNotificationForSms()` on the notifiable, or on demand:

```php
public function routeNotificationForSms(): string
{
    return $this->phone;
}

Notification::route('sms', '+15551234567')->notify(new OrderShipped($order));
```

For quick one-offs, use the bundled `SmsNotification`:

```php
$user->notify(new SmsNotification(message: 'Welcome!'));
$user->notify(new SmsNotification(templateId: 'OTP', variables: ['otp' => 4821], provider: 'msg91'));
```

The `HasSms` trait adds `$user->sms('...')` and `$user->smsTemplate($id, $vars)`.

## Events

| Event | When | Properties |
|---|---|---|
| `SmsSending` | Before sending. Return `false` from a listener to cancel. | `message`, `provider` |
| `SmsSent` | After the provider accepted the message | `message`, `provider`, `result` |
| `SmsFailed` | After a send failed (the exception is re-thrown) | `message`, `provider`, `exception` |
| `SmsWebhookReceived` | After a verified provider webhook | `provider`, `payload`, `messageId`, `status`, `recipient` |

## Webhooks

Delivery reports and inbound messages are received at `POST /laravel-sms/webhook/{provider}` (route name `sms.webhook`) for WhatsApp, Termii, Sendchamp, Africa's Talking and BulkSMS.

```env
SMS_WEBHOOKS_ENABLED=true

WHATSAPP_VERIFY_TOKEN=       # Meta's GET subscription challenge
WHATSAPP_WEBHOOK_SECRET=     # app secret that signs POST callbacks (X-Hub-Signature-256)
TERMII_WEBHOOK_SECRET=       # HMAC-SHA512, X-Termii-Signature
SENDCHAMP_WEBHOOK_SECRET=    # HMAC-SHA256, X-Sendchamp-Signature
BULKSMS_WEBHOOK_SECRET=      # HMAC-SHA256, X-BulkSMS-Signature
AFRICASTALKING_WEBHOOK_SECRET=
```

Every webhook **fails closed**: requests are rejected with `401` unless the secret is configured and verified. Africa's Talking does not sign callbacks, so add the secret to the callback URL: `…/webhook/africastalking?token=<secret>`. The token is stripped before your listeners see the payload.

Webhook routes use the stateless `api` middleware group with `throttle:120,1`. Change `sms.webhooks.middleware` and `SMS_WEBHOOKS_PREFIX` as needed. Custom drivers can receive webhooks by implementing `Contracts\HandlesWebhooks`.

## Custom drivers

```php
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MrRijal\LaravelSms\Drivers\HttpDriver;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;

class AcmeDriver extends HttpDriver
{
    protected string $label = 'Acme';

    protected function validateConfig(): void
    {
        $this->require('api_key', 'from');
    }

    public function send(SmsMessage $message): SmsResult
    {
        $text = $this->text($message);

        return $this->sendEach(
            $message,
            fn (PendingRequest $request, string $to) => $request
                ->withToken($this->value('api_key'))
                ->post('https://api.acme.test/sms', ['to' => $to, 'from' => $this->sender($message), 'text' => $text]),
            function (Response $response, string $to): SentMessage {
                if (! $response->successful()) {
                    $this->fail($response, 'error.message');
                }

                return new SentMessage($to, $this->jsonString($response, 'id'));
            },
        );
    }
}
```

Register it in `config/sms.php` under `drivers` (and its credentials under `providers`), or at runtime:

```php
Sms::extend('acme', fn ($app, array $config) => new AcmeDriver($config));
```

Any class implementing `Contracts\SmsProvider` works as a driver.

## Testing

```php
use MrRijal\LaravelSms\Facades\Sms;
use MrRijal\LaravelSms\SmsMessage;

public function test_it_sends_the_login_code(): void
{
    Sms::fake();

    // ... run your code ...

    Sms::assertSentTo('+15551234567');
    Sms::assertSent(fn (SmsMessage $sms) => str_contains($sms->getText(), 'code'));
    Sms::assertSentCount(1);
    Sms::assertQueued(2);
}

public function test_it_sends_nothing_for_unverified_users(): void
{
    Sms::fake();

    // ... run your code ...

    Sms::assertNothingSent();
    Sms::assertNothingQueued();
}
```

`Sms::fake()` fires the same events and resolves providers like a real send, so a typo in a provider name still fails your test. It keeps drivers registered with `Sms::extend()`.

To test an HTTP driver end to end without network calls, use `Http::fake()`. The AWS SNS driver uses the AWS SDK instead, so pass a mocked `SnsClient` to `new AwsSnsDriver($config, $client)`.

Run the package's own checks with `composer ci` (Pint, PHPStan at max level, and PHPUnit). CI covers PHP 8.2–8.5 × Laravel 11–13.

## Upgrading

See [UPGRADE.md](UPGRADE.md) for moving from 1.x to 2.0, and [CHANGELOG.md](CHANGELOG.md) for all changes.

## Contributing

Contributions are welcome. Please see [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

If you discover a security issue, please email [prashantrijal.721@gmail.com](mailto:prashantrijal.721@gmail.com) instead of opening an issue. See [SECURITY.md](SECURITY.md).

## Credits

- [Prashant Rijal](https://github.com/mr-rijal)
- [All contributors](https://github.com/mr-rijal/laravel-sms/contributors)

## Support

If this package is useful to you, you can support its maintenance via [buymemomo.com/rijal](https://buymemomo.com/rijal).

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
