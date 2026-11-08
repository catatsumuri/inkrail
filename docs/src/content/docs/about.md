---
title: About this project
description: A multilingual Laravel + React starter kit with tools to notice account changes and respond to them.
---

Laravel React Secure Starter Kit is a starting point for building Laravel + React applications. It includes **account change notifications, tools to review signed-in devices, and records of security-related actions**.

It helps users notice unfamiliar changes or logins, and gives operators tools to investigate what happened and respond.

## What can it do?

| When you need to… | The kit provides… |
| --- | --- |
| Know when a password or email address changes | Email notices about the change |
| Deal with an unfamiliar signed-in device | A way to review and revoke login sessions |
| Investigate what happened to an account | Records of important changes and rejected authentication attempts, available to authorized operators |
| Send updates or learn about failures | In-app notifications, email delivery, and operational alerts |

For example, users can end a login session in a browser they no longer use or receive a notice after a password change. Operators can also revoke a specific user's sessions.

## Where does it come from?

This project is based on the `main` branch of [react-starter-kit-i18n](https://github.com/catatsumuri/react-starter-kit-i18n), a multilingual starter kit.

It retains the Laravel + React foundation and English and Japanese translation support. The security additions have been separated into their own starter kit so they can be used together.

Login, password resets, two-factor authentication, and passkeys already exist in the parent project. This edition adds **change notices, session controls, and security event records** around those capabilities.

## Before you start

Keep these three points in mind. The getting started chapter will explain the setup steps.

- **PHP 8.4 or later is required.** This raises the parent project's PHP requirement.
- **Session controls use database sessions.** They require `SESSION_DRIVER=database`.
- **Email notices need a configured mail transport.** The initial configuration writes email content to the log.

## What to read next

If you are setting up the kit for the first time, start with [Getting started](../getting-started/). To explore the additions, choose a topic below.

- [Account protection](../security/) — Checks for email changes and notices about account changes
- [Session management](../sessions/) — Reviewing and ending login sessions
- [Security events](../events/) — Recording actions and inspecting them as an operator
- [Notifications and alerts](../notifications/) — Delivering updates and reporting failures

Detailed configuration and constraints will be added to each chapter over time.
