# Laravel React Secure Starter Kit

A Laravel + React starter kit with additional controls for account security, session management, and security event logging. Based on the multilingual [react-starter-kit-i18n](https://github.com/catatsumuri/react-starter-kit-i18n) project's `main` branch, which derives from Laravel's official [react-starter-kit](https://github.com/laravel/react-starter-kit), it extends the existing authentication flows with tools for users to spot account changes and end unwanted sessions, and for operators to investigate security events.

Compared with that project's `main` branch, it adds:

- **Account change notifications** — email notices for password changes and resets, two-factor authentication changes, recovery code regeneration, passkey additions and removals, and account deletion. Email changes require the current password and notify the previous address.
- **Database session management** — A settings page showing devices, IP addresses, and recent activity, with options to revoke one other session, all other sessions, or every session. **Session revocation requires `SESSION_DRIVER=database`.** The application can use other drivers, such as Redis or file, but this kit's revocation feature is unavailable with them. Revocation also invalidates existing remember-me logins. The settings page requires recent password confirmation.
- **Operator session revocation (database only)** — an Artisan command to revoke a user's database sessions and reset remember-me logins by user ID or email.
- **Security event logging** — records of sensitive account changes and rejected authentication attempts, with a read-only JSON endpoint restricted to explicitly configured users with verified email addresses. Security records reject updates and deletes through the application's activity model, and scheduled cleanup uses configurable retention periods.
- **Rate limits for sensitive actions** — additional throttling for profile updates, password reset submissions, and session revocation.
- **Notifications and operational alerts** — an in-app notification menu, shared delivery support for database, email, and optional Slack notifications, and throttled email alerts for reported exceptions and failed queue jobs.

Regression tests cover account changes, security notifications and logs, access restrictions, session revocation, and failure handling.

For localization setup and adding locales, see the [upstream localization guide](https://github.com/catatsumuri/react-starter-kit-i18n#adding-a-locale).

## Live demo and sandboxes

The live demo runs from `main` with `DEMO_MODE=true`. Other installations leave this setting disabled.

- Public showcase articles belong to a protected owner with a random password. They are read-only, including for that owner.
- **Start my sandbox** creates a separate, verified temporary user for the current browser session and private copies of the example articles. Returning to the button reuses the current signed-in workspace. Other visitors cannot edit these copies, and sandbox articles cannot be published.
- All demo data and sessions reset at **08:00 and 20:00 Asia/Tokyo**, and on every deployment. This is disposable storage; visitors must copy anything they want to keep elsewhere.
- The Cloud App instance must have its scheduler enabled. `php artisan demo:reset` resets SQLite and seeds the original examples; it refuses to run unless demo mode is enabled and the connection is SQLite.
- Cloud builds initialize the disposable SQLite file and run `php artisan migrate --seed --force --no-interaction`. The regular production prohibition on destructive commands remains enabled outside the dedicated demo reset command.

## Project documentation

Documentation is published on GitHub Pages in [English](https://catatsumuri.github.io/laravel-react-secure-starter-kit/about/) and [Japanese](https://catatsumuri.github.io/laravel-react-secure-starter-kit/ja/about/). Each language's root page redirects to its project overview.

The Astro Starlight sources, editing instructions, and documentation publishing workflow live on the separate [`docs` branch](https://github.com/catatsumuri/laravel-react-secure-starter-kit/tree/docs). They are not included in the application starter kit.
