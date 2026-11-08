---
title: System requirements
description: Shared requirements for installing this starter kit.
sidebar:
  order: 0
---

These requirements cover local development. To get started, use **PHP 8.4, Composer 2, Node.js 24, and SQLite**.

## Shared requirements

| Component | Requirement or recommended environment |
| --- | --- |
| PHP | 8.4 or later within the 8.x series |
| PHP extensions | XML, BCMath, and the PDO driver for your database. See “Checking PHP extensions” below for other extensions |
| Composer | 2.2 or later within the 2.x series |
| Node.js | 24.x, version 24.11 or later; 24.22.2 or later recommended |
| npm | The version bundled with Node.js |
| Database | SQLite by default; requires SQLite 3.26 or later and the PHP `pdo_sqlite` extension |

Node.js runs the React development server and builds the frontend. The recommended version also supports this repository's documentation site.

SQLite stores data in a file, so you can start without a separate database server. For MySQL or PostgreSQL, provide a server, connection settings, and the matching PHP PDO driver. See [Laravel's database requirements](https://laravel.com/docs/13.x/database#introduction).

## What to install on your computer

| Method | Local tools |
| --- | --- |
| Laravel Installer (`laravel new`) | PHP, Composer, Node.js, and npm as above, plus the Laravel Installer |
| Laravel Sail | Running Docker and Docker Compose; supports macOS and Linux, and Windows through WSL2 |

With Sail, the application's PHP, Composer, and Node.js run inside containers. The Sail installation guide will cover initial project creation and dependency setup. Choose a container environment that meets the version requirements above. See [Sail's supported environments](https://laravel.com/docs/13.x/sail#introduction).

## Install PHP extensions

**Installing Composer does not necessarily install every required PHP extension.** Missing XML or BCMath extensions prevent Composer dependencies from installing. A missing SQLite driver prevents migrations from running.

On the Debian ARM64 environment with PHP 8.4, installing the following packages after Composer allowed verification from application creation through welcome-page display.

| Package | Stage where it was needed |
| --- | --- |
| `php-xml`, `php-bcmath` | Composer dependency installation |
| `php-sqlite3` | Database migrations using SQLite; added after migration failed due to the missing driver |

```sh
sudo apt install php-xml php-bcmath php-sqlite3
```

Use package names appropriate for your OS and PHP version. Follow the [Laravel Installer guide](./laravel-new/) for the installation sequence.

<details>
<summary>Checking PHP extensions</summary>

Alongside [Laravel's required PHP extensions](https://laravel.com/docs/13.x/deployment#server-requirements), this kit needs extensions for its dependencies and tests. In particular, enable `bcmath`, `curl`, `dom`, `fileinfo`, `iconv`, `mbstring`, `openssl`, `pdo`, `xml`, `xmlwriter`, and the PDO driver for your database. Standard PHP extensions are also required; most come with typical PHP distributions.

The Pail log viewer also requires `pcntl`.

After installing dependencies, check for missing platform requirements with the following command. Run it inside the container when using Sail.

```sh
composer check-platform-reqs
```

</details>

## Settings specific to this kit

- **Session revocation requires `SESSION_DRIVER=database`.** The application can use other drivers, such as Redis or file, but this kit's revocation feature is unavailable with them.
- **You can start without an email delivery service.** The default `MAIL_MAILER=log` writes email content to the log. Configure a mail transport to deliver actual messages.
- **Redis and Slack are optional.** The default configuration uses the database for sessions, queues, and cache.

## Next steps

- [Laravel Installer (laravel new)](./laravel-new/)
- [Laravel Sail](./sail/)
