---
title: Development and customization
description: Extension points, translation updates, tests, and static analysis.
---

This chapter introduces the places to change when adapting the kit to your application. After changing screens, behavior, or translations, use the existing tests and static analysis to check the impact.

## Structure and extension points

Laravel handles authentication, data changes, and authorization, and passes data to React through Inertia. Enforce permissions and identity checks on the server as well as deciding which controls the screen displays.

| Location | Purpose |
| --- | --- |
| `routes/web.php`, `routes/settings.php` | Page and operation routes, authentication, confirmation, and rate limits |
| `app/Http/Controllers/`, `app/Http/Requests/` | Request handling, input validation, and responses |
| `app/Actions/` | Session revocation, notification delivery, security event recording, and other operations |
| `app/Providers/` | Authentication configuration, event listeners, and viewing permissions |
| `app/Notifications/` | Database, mail, and Slack notification formats |
| `resources/js/pages/` | React pages rendered by Inertia |
| `resources/js/components/` | Shared components |
| `resources/js/layouts/` | Shared authentication, application, and settings layouts |
| `resources/css/app.css` | Application styles |
| `config/` | Session, recipient, authentication, and retention settings |
| `database/migrations/` | Schema changes |
| `tests/Feature/`, `tests/Unit/` | Feature and unit tests |

### Adding pages and routes

Add React pages under `resources/js/pages/` and return them as Inertia pages from Laravel routes or controllers. `resources/js/app.tsx` selects shared layouts: `auth/` uses the authentication layout, and `settings/` uses the application and settings layouts.

The Wayfinder Vite plugin generates TypeScript controller and route references. `resources/js/actions/`, `resources/js/routes/`, and `resources/js/wayfinder/` are generated output; change routes in PHP rather than editing those files.

### Changing the added features

Session management lives in `app/Actions/Sessions/`, and delivery logic in `app/Actions/Notifications/`. For notification formatting, inspect `GeneralNotification`; for account change notification conditions, inspect `SecurityNotificationServiceProvider`.

Use `LogSecurityEvent` to record security events. Record successful state changes only after confirming persistence. Use `failure()` for rejected attempts. Do not pass passwords, tokens, or complete submitted input into properties.

Authentication settings live in `config/fortify.php` and `FortifyServiceProvider`. Chisel feature selections affect the available pages and code. Check the effect of changes on configurations where a feature is absent as well.

See [Account protection](../security/), [Session management](../sessions/), [Security events](../events/), and [Notifications and alerts](../notifications/) for feature requirements.

## Adding translations

Use Laravel's `__()` in PHP. In React, obtain `__()` from `lang()` in `@erag/lang-sync-inertia`. English source strings act as keys mapped to translations.

```tsx
import { lang } from '@erag/lang-sync-inertia/react';

export function NoticeHeading() {
    const { __ } = lang();

    return <h2>{__('Maintenance notice')}</h2>;
}
```

### Updating translation sources

Add project-specific source strings to `lang/vendor-patches/source/react.json` and Japanese translations to `lang/vendor-patches/locales/ja.json`. Use matching keys in both files.

Source example:

```json
{
    "Maintenance notice": "Maintenance notice"
}
```

Japanese example:

```json
{
    "Maintenance notice": "メンテナンスのお知らせ"
}
```

After editing, run these from the project root:

```sh
composer patch-vendor
php artisan lang:update
```

`composer patch-vendor` runs `scripts/patch-vendor.php`, merging the sources into `vendor/laravel-lang/starter-kits/`. `lang:update` then updates locale files. Composer's dependency installation and update hooks also apply these changes.

:::note[Edit the sources]
Changes made directly in `vendor/` or generated files such as `lang/ja.json` can be overwritten by dependency or translation updates. Keep changes in the Git-tracked `lang/vendor-patches/` sources.
:::

### Choosing the locale

Set the default language with `APP_LOCALE` and the fallback with `APP_FALLBACK_LOCALE` in `.env`. To default to Japanese:

```dotenv
APP_LOCALE=ja
APP_FALLBACK_LOCALE=en
```

Rebuild cached configuration with `php artisan config:cache`. When adding a locale, provide its translations and check both screens and server messages. Preserve placeholders such as `:browser` in translated strings and verify that displayed values replace them correctly.

## Tests and static analysis

The [test inventory](./tests/) lists exact English test keys by file. Its Japanese counterpart pairs those keys with Japanese descriptions.

Run commands from the project root. With Sail, use the corresponding `vendor/bin/sail composer`, `vendor/bin/sail npm`, or `vendor/bin/sail artisan` command.

### Running the full checks

```sh
composer ci:check
```

This is the CI entry point. It runs frontend code checks and TypeScript checks, then clears PHP configuration caching, checks PHP formatting with Pint, runs PHPStan, and executes PHPUnit tests.

### Running individual checks

| Command | Purpose |
| --- | --- |
| `npm run check` | Frontend checks through Vite+ |
| `npm run types:check` | TypeScript type checking |
| `composer lint:check` | PHP formatting check with Pint |
| `composer types:check` | PHPStan analysis with Larastan at level 7 |
| `composer test` | Clear cached configuration, check PHP formatting and types, and run tests |
| `php artisan test --filter=NotificationTest` | Run tests matching the specified name |
| `npm run build` | Production frontend build |
| `node scripts/check-test-inventory.mjs` | Compare tracked test methods with both documentation inventories |

Use `composer lint` or `npm run check:fix` to apply fixes. These commands modify files, so inspect the diff afterward.

### Test database

Test settings live in `phpunit.xml`. Database tests use `RefreshDatabase`, so provide a dedicated test connection separate from development and production data. Configure `.env.testing` as needed.

Before running `php artisan test` directly, clear stale configuration with `php artisan config:clear`. `composer test` includes this step.

When changing authentication or permissions, check unauthenticated, unauthorized, and failed-confirmation paths as well as success. Notification and logging changes should also preserve the original account operation when delivery or recording fails.
