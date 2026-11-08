---
title: About this project
description: A multilingual Laravel + React starter kit with tools to notice account changes and respond to them.
---

Laravel React Secure Starter Kit is a starting point for building Laravel + React applications. It includes **account change notifications, tools to review signed-in devices, and records of security-related actions**.

It helps users notice unfamiliar changes or logins, and gives operators tools to investigate what happened and respond.

## What can it do?

| When you need to… | The kit provides… |
| --- | --- |
| Know when a password or email address changes | [Email notices about the change](../security/#account-change-notifications) |
| Deal with an unfamiliar signed-in device | [A way to review and revoke login sessions](../sessions/) |
| Investigate what happened to an account | [Records of important changes and rejected authentication attempts, available to authorized operators](../events/) |
| Send updates or learn about failures | [In-app notifications, email delivery, and operational alerts](../notifications/) |

For example, users can end a login session in a browser they no longer use or receive a notice after a password change. Operators can also revoke a specific user's sessions.

## Where does it come from?

This project is based on the `main` branch of [react-starter-kit-i18n](https://github.com/catatsumuri/react-starter-kit-i18n), a multilingual starter kit.

Its original foundation is Laravel's official [react-starter-kit](https://github.com/laravel/react-starter-kit). The `react-starter-kit-i18n` project adds multilingual support to the official kit, and this project adds account protection and operational features on top of that.

It retains the Laravel + React foundation and English and Japanese translation support. The security additions have been separated into their own starter kit so they can be used together.

Login, password resets, two-factor authentication, and passkeys already exist in the parent project. This edition adds **change notices, session controls, and security event records** around those capabilities.

## Before you start

Keep these three points in mind. The getting started chapter will explain the setup steps.

- **PHP 8.4 or later is required.** This raises the parent project's PHP requirement.
- **Session revocation requires `SESSION_DRIVER=database`.** The application can use other drivers, but this kit's revocation feature is unavailable with them.
- **Email notices need a configured mail transport.** The initial configuration writes email content to the log.

## What to read next

If you are setting up the kit for the first time, start with [Getting started](../getting-started/). To explore the additions, choose a topic below.

- [Account protection](../security/) — [Password confirmation for email changes](../security/#password-confirmation-for-email-changes), [account change notifications](../security/#account-change-notifications), and [rate limits](../security/#rate-limits)
- [Session management](../sessions/) — Reviewing and ending login sessions
- [Security events](../events/) — Recording actions and inspecting them as an operator
- [Notifications and alerts](../notifications/) — Delivering updates and reporting failures

Detailed configuration and constraints will be added to each chapter over time.
