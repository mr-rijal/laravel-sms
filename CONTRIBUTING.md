# Contributing to Laravel SMS

Thank you for considering contributing. Please use the following guidelines.

## Before you open a PR

Run the same checks as CI locally:

```bash
composer ci
```

This runs Pint (style), PHPStan / Larastan, and PHPUnit.

You can run steps individually:

```bash
composer format:test
composer analyse
composer test
```

## Code style

- Laravel Pint is used for formatting (`composer format` to fix files).
- Follow existing patterns in `src/` and keep changes focused on the feature or fix.

## Tests

- Add or update PHPUnit tests for behavior you change, especially drivers and the SMS manager.
- Use the `fake` driver in tests so no real provider APIs are called.

## Branching

- Use `main` as the stable branch.
- Prefer feature branches such as `feature/xyz` or `bugfix/xyz`.

## Pull requests

- Ensure `composer ci` passes.
- Use clear commit messages and describe what changed and why in the PR.

## Issues

- Bug reports and feature requests are welcome.
- For bugs, include steps to reproduce, expected vs actual behavior, and PHP / Laravel versions if relevant.
