# InkRail

InkRail is an application for writing, managing, and publishing Markdown articles in English and Japanese. Keep drafts private and share finished articles through public URLs.

## Writing and publishing

- Create, edit, and delete your own articles.
- Keep articles as private drafts, publish them, or unpublish them at any time.
- Set an article slug and copy its public URL to share it with readers who do not need an account.
- Browse published articles through an optional public index, controlled by `ARTICLES_PUBLIC_INDEX_ENABLED`. Set `ARTICLES_HOME_PAGE=articles` to show that index at `/` instead of the welcome page.
- Render Markdown with [Inkstream](https://github.com/catatsumuri/inkstream), including supported GitHub, Zenn, and Mintlify syntax, diagrams, and charts.

The application uses Laravel, Inertia, React, and Tailwind CSS.

## Account security

InkRail includes registration, email verification, password resets, two-factor authentication, and passkeys. Users can manage their profile, review active sessions, and revoke access from other browsers. Session management requires `SESSION_DRIVER=database`.

Account security notifications, in-app notifications, security event logging, and configurable operational alerts provide the supporting account and operations features.

## Article languages

An article can contain English and Japanese versions of its title and Markdown body. Its URL, owner, and publication status are shared. Use the language tabs in the editor to edit both versions and save them together; one language is enough to save or publish an article.

The header language switch changes the UI and article display together. If a translation is missing, InkRail uses the configured fallback language, then another available supported language, and shows which language is being displayed. Missing translations remain empty in the editor. Clear both fields of a translation to remove it; at least one title must remain.

Existing articles migrate to English without changing their contents or URLs. Migration rollback retains only one version, preferring English; back up the database before rolling back a multilingual installation.

## Local development

The application runs on PHP 8.5 using Laravel Sail. With dependencies installed and Sail running:

```sh
vendor/bin/sail composer setup
vendor/bin/sail npm run dev
```

Keep `DEMO_MODE=false` for an ordinary installation. Optional sample content can be loaded with `vendor/bin/sail artisan db:seed --no-interaction`; it creates one welcome article with English and Japanese versions.

## Live demo branch

The `live-demo` branch maintains the Inkstream showcase presentation and disposable visitor sandboxes. Laravel Cloud's demo environment follows that branch. Changes to `main` are not automatically deployed to the live demo.

To run the checks:

```sh
vendor/bin/sail composer ci:check
```

## Built on

InkRail builds on the [Laravel React Secure Starter Kit](https://github.com/catatsumuri/laravel-react-secure-starter-kit), retaining its authentication, account security notifications, session management, audit logging, and localization support.

The frontend requires `@erag/lang-sync-inertia` ^3.1.1, which fixes unreplaced placeholders when translation keys are missing. Existing installations should run `vendor/bin/sail npm install '@erag/lang-sync-inertia@^3.1.1'` and `vendor/bin/sail npm run build`. See the [upstream release notes](https://github.com/eramitgupta/lang-sync-inertia/releases/tag/v3.1.1).

For Inkstream itself, visit the [library repository](https://github.com/catatsumuri/inkstream). For the underlying starter kit, see its [English](https://catatsumuri.github.io/laravel-react-secure-starter-kit/about/) or [Japanese](https://catatsumuri.github.io/laravel-react-secure-starter-kit/ja/about/) documentation.
