# Laravel React Secure Starter Kit

A Laravel + React starter kit with additional controls for account security, session management, and security event logging. Based on the [security-hardening branch of react-starter-kit-i18n](https://github.com/catatsumuri/react-starter-kit-i18n/tree/feature/security-hardening), it extends the existing authentication flows with tools for users to spot account changes and end unwanted sessions, and for operators to investigate security events.

Compared with that project's `main` branch, it adds:

- **Account change notifications** — email notices for password changes and resets, two-factor authentication changes, recovery code regeneration, passkey additions and removals, and account deletion. Email changes require the current password and notify the previous address.
- **Database session management** — **requires `SESSION_DRIVER=database`; Redis, file, and other session drivers are not supported.** A settings page showing devices, IP addresses, and recent activity, with options to revoke one other session, all other sessions, or every session. Revocation also invalidates existing remember-me logins. The settings page requires recent password confirmation.
- **Operator session revocation (database only)** — an Artisan command to revoke a user's database sessions and reset remember-me logins by user ID or email.
- **Security event logging** — records of sensitive account changes and rejected authentication attempts, with a read-only JSON endpoint restricted to explicitly configured users with verified email addresses. Security records reject updates and deletes through the application's activity model, and scheduled cleanup uses configurable retention periods.
- **Rate limits for sensitive actions** — additional throttling for profile updates, password reset submissions, and session revocation.
- **Notifications and operational alerts** — an in-app notification menu, shared delivery support for database, email, and optional Slack notifications, and throttled email alerts for reported exceptions and failed queue jobs.

Regression tests cover account changes, security notifications and logs, access restrictions, session revocation, and failure handling.

For localization setup and adding locales, see the [upstream localization guide](https://github.com/catatsumuri/react-starter-kit-i18n#adding-a-locale).

## Documentation

The Astro Starlight documentation scaffold lives in [`docs/`](docs/README.md).
It is configured for [GitHub Pages](https://catatsumuri.github.io/laravel-react-secure-starter-kit/).
