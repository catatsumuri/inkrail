# Laravel React Secure Starter Kit: `extended`

This is the `extended` branch of the [Laravel React Secure Starter Kit](https://github.com/catatsumuri/laravel-react-secure-starter-kit). It builds on the [`main` branch](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/README.md) and adds username sign-in, optional email addresses and deployment-time feature flags.

Everything the base kit provides is described in the [`main` README](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/README.md) and is not repeated here: account change notifications, database session management, security event logging, rate limits, operational alerts, localization and the published documentation.

## What `extended` adds

- **Sign in with a username or an email address.** One field accepts either. Usernames are lowercase, 3-30 characters of `a-z 0-9 . _ -`, start and end with a letter or digit, and never contain `@`. Failed attempts are throttled per account, so alternating between the two identifiers does not give extra guesses.
- **Email address is optional.** Registration requires a username; the email address can be left out, added later, and changed, but not removed once set. Accounts without one skip email verification, receive security notices in the app instead of by mail, and cannot read the security log.
- **Feature flags.** Registration, passkeys, two-factor authentication and password confirmation can be switched off with environment variables.
- **No Chisel.** The install-time feature selection of the official starter kit was removed so this branch can keep merging `main`. The `@chisel-*` marker comments are left in place on purpose.

## Feature flags

They are read when the application boots, so they are decisions to make before launch, not switches to flip while the application is in use. Turning two-factor authentication off, for example, lets accounts that already enrolled sign in with their password alone.

| Variable                        | Default | Turns off                                                                    |
| ------------------------------- | ------- | ---------------------------------------------------------------------------- |
| `FEATURE_REGISTRATION`          | `true`  | Self-service registration                                                    |
| `FEATURE_PASSKEYS`              | `true`  | Passkey sign-in and management                                               |
| `FEATURE_TWO_FACTOR`            | `true`  | Two-factor setup, recovery codes and the sign-in challenge                   |
| `FEATURE_PASSWORD_CONFIRMATION` | `true`  | Password re-entry for sensitive settings, and the browser session management |

See [FEATURE_FLAGS.md](FEATURE_FLAGS.md) for what each flag does, what to read before turning one off, accounts without an email address, how to add a flag, and how to merge `main` into this branch.

## Getting started

```bash
git clone --branch extended https://github.com/catatsumuri/laravel-react-secure-starter-kit.git
cd laravel-react-secure-starter-kit
composer setup
```

`composer setup` installs dependencies, creates `.env`, generates the application key, runs the migrations and builds the frontend. `php artisan db:seed` creates a development account (username `test`, email `test@example.com`, password `password`); do not use it outside development.

After pulling changes, run `php artisan migrate`. `composer install` and `composer update` regenerate the language files (`patch-vendor` and `lang:update`) on their own; run `composer patch-vendor` and `php artisan lang:update` by hand if only the translation sources changed.

## Known limitation

An account without an email address that forgets its password has no recovery path yet, because the self-service reset works by email only. An operator command for it is not implemented.

## Documentation

The documentation site and its sources describe the `main` branch. See the [`main` README](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/README.md#documentation) for links.
