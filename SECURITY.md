# Security policy

Thank you for helping keep **mr-rijal/laravel-sms** and its users safe. We take security seriously and appreciate responsible disclosure of vulnerabilities.

## Reporting a vulnerability

If you discover a security issue, **do not open a public GitHub issue**.

Report it privately by email: [prashantrijal.721@gmail.com](mailto:prashantrijal.721@gmail.com)

Include as much detail as possible:

- A clear description of the vulnerability
- Steps to reproduce
- Affected versions (if known)
- Proof-of-concept (if available)
- Potential impact (credential exposure, webhook abuse, etc.)

You should receive an acknowledgment within **48 hours** in most cases.

## Supported versions

Only the **latest major release** of this package is actively supported with security updates.

| Version | Supported |
| ------- | --------- |
| Latest  | Yes       |
| Older   | No        |

Keep Laravel, PHP, and dependencies up to date.

Laravel 11 compatibility is provided for existing applications, but Laravel 11 is outside its upstream security-support window. Use the latest Laravel 12 or Laravel 13 release for security-supported deployments.

## Disclosure process

1. You report the vulnerability privately
2. We confirm and investigate
3. A fix is developed and tested
4. A release is published when ready
5. Public disclosure may follow, as appropriate

Please allow reasonable time before public disclosure.

## Security practices for users

When using this package in production:

- Store provider credentials in environment variables or a secrets manager, not in source control
- Serve webhook routes over HTTPS only. Every webhook fails closed: it is rejected unless the provider's `*_WEBHOOK_SECRET` is configured and the request carries a valid signature (WhatsApp, Sendchamp, Termii, BulkSMS) or secret token (Africa's Talking)
- Keep webhooks disabled (`SMS_WEBHOOKS_ENABLED=false`) unless you need them
- Queued SMS jobs are encrypted with `APP_KEY`; keep that key secret and rotate it with Laravel's `APP_PREVIOUS_KEYS`
- Never use the `log` driver or the Telegram/Discord/Slack mirror drivers to deliver to customers; they exist for development and internal operations
- Limit who can trigger SMS sends in your app (rate limits, auth, auditing)
- Review logs so message bodies and phone numbers are not exposed unnecessarily
- Keep `guzzlehttp/guzzle`, `aws/aws-sdk-php`, and Laravel patched

## Non-security bugs

Use **GitHub Issues** for general bugs and features. Security concerns should go to the email above.

## Acknowledgments

Contributors who responsibly disclose vulnerabilities may be credited in release notes upon request.

Thank you for helping keep this project secure.
