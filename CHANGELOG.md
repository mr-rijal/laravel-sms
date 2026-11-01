# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### 2.0.0 — core rewrite

See [UPGRADE.md](UPGRADE.md) for the breaking changes.

#### Added

- Sinch, Bird (MessageBird), ClickSend, Clickatell, Brevo, Bandwidth and SendPulse drivers.
- `failover` driver that retries only the recipients a provider rejected on the next provider.
- `SmsResult` / `SentMessage` with provider message IDs, returned from every send.
- `DeliveryException` with `failedRecipients` and `partial` results; `SmsException` marker interface.
- `Sms::fake()` with `assertSent`, `assertSentTo`, `assertNotSent`, `assertNothingSent`, `assertSentCount`, `assertQueued`, `assertNothingQueued`.
- `PendingSms` fluent builder (a fresh message per call), `from()` sender override, `Conditionable` support.
- `SmsFailed` event; `SmsSending` listeners can cancel a send by returning `false`.
- `Sms::extend()` for custom drivers, `HttpDriver` base class for HTTP providers.
- `php artisan sms:test` command and `php artisan about` section.
- Concurrent per-recipient sends (`sms.http.concurrency`), configurable timeouts, OAuth token caching.
- Twilio Messaging Service support, Vonage automatic Unicode, Brevo templates, AWS SNS IAM-role credentials.
- Notifications can return a string or `SmsMessage` and support `Notification::route('sms', ...)`.

#### Changed

- All drivers use Laravel's HTTP client (fakeable with `Http::fake()`) instead of Guzzle directly.
- Bulk-capable providers (Sparrow, BulkSMS, and others) send one request for all recipients.
- `aws/aws-sdk-php` is now optional (`suggest`).
- Phone numbers are normalized (spaces, dashes, dots, parentheses removed); length limit counts characters, not bytes.
- Successful sends are no longer logged at info level; use the `SmsSent` event.
- PHPStan runs at max level.

#### Removed

- Telegram, Discord and Slack mirror drivers.
- `SmsManager::sendMessage()`; `SmsSent::$success` and `SmsSent::$error`.

### 1.x

### Added

- Telnyx, Plivo, and Infobip SMS drivers (from #13).
- Sendchamp, Termii, Africa's Talking, and BulkSMS drivers with delivery-report webhooks (from #10).
- `log` driver for local development, with an optional `SMS_LOG_CHANNEL`.
- Telegram, Discord, and Slack mirror drivers that post redacted copies to an internal channel.
- PHP 8.5 and Guzzle 8 support.
- `HandlesWebhooks` contract and `Sms::driver($name)` accessor.
- `sms.queue_connection` and `sms.queue_name` options; `SMS_QUEUE` env support.
- Configurable webhook prefix (`SMS_WEBHOOKS_PREFIX`) and named route `sms.webhook`.

### Changed

- **Breaking:** all webhooks fail closed. Requests are rejected unless the provider's webhook secret is configured and verified (WhatsApp previously accepted unsigned requests when no secret was set).
- **Breaking:** Africa's Talking webhooks are authenticated with a `?token=` shared secret instead of the spoofable `User-Agent` header.
- **Breaking:** webhook routes use the stateless `api` middleware group plus `throttle:120,1` instead of `web` (which applied CSRF to provider callbacks).
- Queued `SendSmsJob` payloads are encrypted (`ShouldBeEncrypted`).
- `sendLater()` / `sendLaterAt()` queue one job per recipient, so queue retries no longer resend to recipients that already succeeded.
- Infobip and Plivo failures include the provider's error text, cut to 200 characters.
- `SmsManager` is registered as a scoped binding (Octane and queue-worker safe); the `sms` notification channel is registered lazily.
- New drivers send credentials per request and no longer embed raw upstream response bodies in exception messages.
- Sendchamp `route` and Termii `channel` are validated; Termii accepts an HTTPS `base_url`.
- Failed-job logs no longer include stack traces.
- Explicit `illuminate/*` dependencies; PHPStan raised to level 8.
- CI: PHP 8.2–8.5 × Laravel 11–13, Composer audit, read-only token, `actions/checkout@v7`, `actions/cache@v6`.

### Removed

- Unused webhook secret keys for Twilio, Vonage, MSG91, and Sparrow (those drivers have no webhook handler).
