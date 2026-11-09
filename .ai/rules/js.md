---
paths:
    - 'resources/js/**'
---

# Js

## Translate React UI with full English string keys

Wrap user-facing React text with `__()` from `@erag/lang-sync-inertia/react`, using the full English fallback text as the translation key. Register project-specific keys in `lang/vendor-patches/**`.

## Use PasswordInput for password fields

Render every password field with `@/components/password-input` (it has the show/hide eye icon), never `<Input type="password">`. This includes re-authentication fields such as a current-password prompt.

## Extend the existing notification system

Extend app notifications through `SendGeneralNotification` and `GeneralNotification`, using Laravel database notifications and the existing Inertia shared-prop, `NotificationButton`, and `notifications.read` flow. Do not introduce a parallel notification model, table, endpoint, or delivery pipeline.

## Use shadcn/ui dialogs for confirmations

Use the existing shadcn/ui Dialog components from @/components/ui/dialog for confirmation flows, including destructive actions. Do not use browser-native window.confirm(), window.alert(), or window.prompt(). Provide a clear title, description, cancel action, and explicit confirmation button; disable submission while processing.
