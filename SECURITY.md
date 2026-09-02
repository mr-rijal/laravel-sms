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
- Restrict webhook routes (HTTPS and IP allowlists where appropriate). WhatsApp webhook requests require a valid `X-Hub-Signature-256` signature and a configured `WHATSAPP_WEBHOOK_SECRET`.
- Limit who can trigger SMS sends in your app (rate limits, auth, auditing)
- Review logs so message bodies and phone numbers are not exposed unnecessarily
- Keep `guzzlehttp/guzzle`, `aws/aws-sdk-php`, and Laravel patched

## Non-security bugs

Use **GitHub Issues** for general bugs and features. Security concerns should go to the email above.

## Acknowledgments

Contributors who responsibly disclose vulnerabilities may be credited in release notes upon request.

Thank you for helping keep this project secure.
