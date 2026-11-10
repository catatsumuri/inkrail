# InkRail

An interactive showcase for [Inkstream](https://github.com/catatsumuri/inkstream), a Markdown rendering library. Browse working examples, compare source with rendered output, and experiment with your own private copies.

**[Open the live demo](https://inkrail-production-nkokth.laravel.cloud/)**

## Explore Inkstream

The public showcase includes guides for:

- **Markdown** — everyday writing and formatting.
- **GitHub Flavored Markdown** — tables, task lists, and alerts.
- **Zenn** — messages and disclosures.
- **Mintlify** — documentation components and layouts.
- **Inkstream extensions** — diagrams, charts, trees, and quizzes.
- **Inertia.js integration** — rendering Markdown in a Laravel and React application.

Each guide pairs copyable syntax with rendered examples. The application uses `@catatsumuri/inkstream` with Laravel, Inertia, React, and Tailwind CSS.

## Try a private sandbox

Public examples are read-only. Select **Start my sandbox** to create a temporary workspace without signing up. Each browser session gets its own user and editable copies of the example articles.

You can edit, create, and delete your own articles. Sandbox articles remain private and cannot be published; your changes do not affect the public examples or other visitors. Returning to the app in the same signed-in session reuses your workspace.

**The live demo is disposable.** All data and login sessions reset daily at **08:00 and 20:00 Japan time (`Asia/Tokyo`)**, and on every deployment. Copy anything you want to keep elsewhere.

## Article languages

An article can contain English and Japanese versions of its title and Markdown body. Its URL, owner, and publication status are shared. Use the language tabs in the editor to edit both versions and save them together; one language is enough to save or publish an article.

The header language switch changes the UI and article display together. If a translation is missing, InkRail uses the configured fallback language, then another available supported language, and shows which language is being displayed. Missing translations remain empty in the editor. Clear both fields of a translation to remove it; at least one title must remain.

Sandboxes copy all available translations. Existing articles migrate to English without changing their contents or URLs. Migration rollback retains only one version, preferring English; back up the database before rolling back a multilingual installation.

## Local development

The application runs on PHP 8.5 using Laravel Sail. With dependencies installed and Sail running:

```sh
vendor/bin/sail composer setup
vendor/bin/sail artisan db:seed --no-interaction
vendor/bin/sail npm run dev
```

Seed content lives in `database/seeders/showcase/`. Outside demo mode, the application supports ordinary registration, authentication, and article management.

To enable the protected showcase and visitor sandboxes, set `DEMO_MODE=true` in `.env` before seeding. Leave it disabled for an ordinary installation. The dedicated reset command and scheduled resets require demo mode; the reset command also requires SQLite.

## Live demo operations

The Laravel Cloud deployment follows `main` and uses `DEMO_MODE=true` with a disposable SQLite database. The App instance has its scheduler enabled and runs as a single replica so scheduled resets operate on the same database as web requests.

- Each build empties the SQLite file and runs `php artisan migrate --seed --force --no-interaction` before the new release goes live.
- The scheduler runs `php artisan demo:reset` at 08:00 and 20:00 Japan time. This rebuilds the database and restores the seed content.
- Public examples belong to a protected owner with a random password. The application rejects writes by that owner, including article changes and account deletion.
- Production restrictions on destructive Artisan commands remain enabled outside the dedicated demo reset command.

To run the checks:

```sh
vendor/bin/sail composer ci:check
```

## Built on

InkRail builds on the [Laravel React Secure Starter Kit](https://github.com/catatsumuri/laravel-react-secure-starter-kit), retaining its authentication, account security notifications, session management, audit logging, and localization support.

For Inkstream itself, visit the [library repository](https://github.com/catatsumuri/inkstream). For the underlying starter kit, see its [English](https://catatsumuri.github.io/laravel-react-secure-starter-kit/about/) or [Japanese](https://catatsumuri.github.io/laravel-react-secure-starter-kit/ja/about/) documentation.
