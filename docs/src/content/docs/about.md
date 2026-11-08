---
title: About this project
description: Where this kit comes from and what it adds.
---

This project is a derivative of the main branch of [react-starter-kit-i18n](https://github.com/catatsumuri/react-starter-kit-i18n). Its security-related extensions have been separated into an independent repository.

## Relationship to the original

It retains the Laravel + React foundation and internationalization pipeline. Refer to the original project’s documentation for the shared translation setup.

## What this edition adds

| Area | Additions |
| --- | --- |
| Account protection | Change notifications, password confirmation for email changes, and rate limits for sensitive actions |
| Session management | Session listing and revocation in account settings, plus an operator revocation command |
| Security events | Records of sensitive changes and failed authentication attempts, access controls, and retention periods |
| Notifications and alerts | In-app, email, and optional Slack delivery, plus alerts for exceptions and failed jobs |

## Requirements to keep in mind

Session management requires `SESSION_DRIVER=database`. Redis, file, and other session drivers are not supported.

These docs focus on the additions and their constraints. Detailed procedures will be added to each chapter over time.
