# Upgrade Guide

## From 1.x to 2.0

Most applications only need the first three steps. Estimated time: under 30 minutes.

### 1. Update the package

```bash
composer require mr-rijal/laravel-sms:^2.0
```

If you use the **AWS SNS** driver, the AWS SDK is no longer installed automatically:

```bash
composer require aws/aws-sdk-php
```

### 2. Re-publish or merge the config

```bash
php artisan vendor:publish --tag=sms-config --force
```

If you have a customized `config/sms.php`, merge these changes by hand:

- `default` now falls back to `log` instead of `fake`.
- New sections: `retry`, `http`, and `providers.failover`.
- New providers: `sinch`, `bird`, `clicksend`, `clickatell`, `brevo`, `bandwidth`, `sendpulse`.
- Removed providers: `telegram_mirror`, `discord_webhook`, `slack_webhook`.

### 3. Send results instead of booleans

`send()`, `sendNow()` and the `HasSms` helpers now return `?SmsResult` instead of `bool`.

```php
// 1.x
if (Sms::to($n)->message('Hi')->sendNow()) { ... }

// 2.0
$result = Sms::to($n)->message('Hi')->sendNow();
$result?->firstId();
```

A result is `null` when an `SmsSending` listener cancels the send, or when `send()` queues the message. Failures still throw, as in 1.x.

### Removed drivers

The Telegram, Discord and Slack mirror drivers were removed: they never delivered an SMS. If you used them for internal alerts, listen to the `SmsSent` event and post to your channel with a Laravel notification instead.

### Exceptions

Provider failures throw `MrRijal\LaravelSms\Exceptions\DeliveryException` (still a `RuntimeException`). Invalid input throws `InvalidMessageException`, and bad configuration throws `InvalidConfigurationException` (both still `InvalidArgumentException`). Catch `MrRijal\LaravelSms\Exceptions\SmsException` to handle all of them.

`DeliveryException::$failedRecipients` and `DeliveryException::$partial` tell you who did and did not get the message.

### Events

- `SmsSent` no longer has `success` and `error` properties. It is only dispatched on success and carries `result` (an `SmsResult`).
- Failures dispatch the new `SmsFailed` event with the `exception`.
- `SmsSending` listeners can now return `false` to cancel the send.

### Notifications

`toSms()` should now **return** an `SmsMessage` (or a string) instead of sending the message itself. The channel routes it with `routeNotificationForSms()` and supports `Notification::route('sms', $number)`.

```php
// 1.x
public function toSms($notifiable): void
{
    Sms::to($notifiable->phone)->message('Shipped')->sendNow();
}

// 2.0
public function toSms(object $notifiable): SmsMessage
{
    return SmsMessage::create('Shipped');
}
```

`SmsNotification` no longer reads `sms.queue`. To queue notifications, implement `ShouldQueue` on your notification, as with any Laravel notification.

### Facade and manager

- `Sms::to()`, `Sms::provider()`, `Sms::message()` and `Sms::template()` now return a new `PendingSms` each time, so no state is shared between sends.
- `Sms::sendMessage($message, $provider)` was replaced by `Sms::sendNow($message->provider($provider))`.
- `SmsManager` is constructed with the container. Resolve it from the container (`app(SmsManager::class)`) instead of calling `new SmsManager`.

### Custom drivers

`SmsProvider::send()` must now return an `SmsResult`:

```php
public function send(SmsMessage $message): SmsResult
{
    // ... call the provider ...

    return SmsResult::accepted($message->getTo(), $providerMessageId);
}
```

Extend `MrRijal\LaravelSms\Drivers\HttpDriver` to get config validation, timeouts, concurrent sends, partial-failure handling and safe error messages for free. Drivers now use Laravel's HTTP client, so constructors no longer accept a Guzzle `Client`; use `Http::fake()` in tests.

### Testing

Replace `FakeDriver::$messages` assertions with `Sms::fake()`:

```php
Sms::fake();
// ...
Sms::assertSentTo('+15551234567');
```

`FakeDriver` still exists for apps that rely on it.
