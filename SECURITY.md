# 🔐 Security Policy

Thank you for helping keep **mr-rijal/laravel-pipeline** and its users safe. We take security seriously and appreciate responsible disclosure of vulnerabilities.

---

## 📬 Reporting a Vulnerability

If you discover a security issue, **do not open a public GitHub issue**.

Instead, please report it privately via email:

> **📧 [prashantrijal.721@gmail.com](mailto:prashantrijal.721@gmail.com)**

When reporting, please include as much detail as possible to help us investigate quickly:

* A clear description of the vulnerability
* Steps to reproduce the issue
* Affected versions (if known)
* Proof-of-concept (if available)
* Potential impact (data leak, RCE, privilege escalation, etc.)

You will typically receive a response **within 48 hours** acknowledging your report.

---

## 🛠️ Supported Versions

Only the **latest major release** of this package is actively supported with security updates.

| Version | Supported |
| ------- | --------- |
| Latest  | ✅ Yes     |
| Older   | ❌ No      |

We strongly recommend keeping your installation up to date to receive the latest security fixes.

---

## ⏳ Disclosure Process

We follow a responsible disclosure process:

1. You report the vulnerability privately
2. We confirm and investigate the issue
3. A fix is developed and tested
4. A security release is published
5. Public disclosure is made (if appropriate)

Please allow reasonable time for us to resolve the issue before making it public.

---

## 🔍 Security Best Practices for Users

We recommend the following when using this package in production:

* Always validate and sanitize input passed into pipeline steps
* Avoid executing untrusted classes or closures
* Run pipelines with least-privilege service accounts
* Keep Laravel and PHP dependencies up to date
* Enable logging and monitoring for pipeline execution failures

---

## 🧪 Reporting Non-Security Bugs

For general bugs, feature requests, or questions, please use:

> 🐛 **GitHub Issues**

Security-related concerns should always be sent by email instead of public channels.

---

## 🙏 Acknowledgments

We appreciate the community’s efforts in helping make open-source software safer. Contributors who responsibly disclose vulnerabilities may be credited in release notes (upon request).

---

**Thank you for helping keep Laravel Pipeline secure.** 🚀
