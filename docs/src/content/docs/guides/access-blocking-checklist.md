---
title: 'Appendix: Access blocking checklist'
description: Session revocation, account suspension, operator actions, auditing, and operational verification.
---

This checklist reviews access blocking capabilities and operations. Items are checked where this edition's implementation, existing tests, and documentation provide evidence. It is not an exhaustive audit or a report of production verification.

:::note[Scope of this edition and extension policy]
This edition focuses on account protection by users themselves and session revocation through an operator CLI. It does not include administrator-facing user management.

Account suspension and reinstatement would require administrator permissions, suspension checks across authentication paths, and operation auditing, substantially expanding the scope. Such management features are intended as an extension on a separate branch. Unchecked items include areas to consider for that extension, rather than uniformly indicating defects in this edition.
:::

:::note[Scope of the checks]
Session revocation requires `SESSION_DRIVER=database`. Checks apply to revoking existing sessions with that configuration; they do not mean the account is suspended or new logins are prohibited. Unchecked items include unimplemented, inapplicable, and operationally unverified items.
:::

## 1. Session revocation

- [x] Can all authenticated sessions for a specific user be forcibly revoked?
  - [x] Can sessions on multiple devices, such as a PC and smartphone, be revoked?
  - [x] Can an operator revoke them without action by the user?
  - [x] Is the next authenticated request using a revoked session rejected?
  - [x] Can automatic reauthentication such as Remember Me be invalidated too?

:::tip[Implementation and tests]
`security:revoke-sessions` deletes the user's stored sessions and rotates the remember token. Existing tests cover multiple clients, copied cookies, and Remember me cookies. Access to execute the CLI must be managed on the server.
:::

## 2. Account suspension

- [ ] Can a specific user's account be temporarily suspended?
  - [ ] Are new logins refused while suspended?
  - [ ] Is access through sessions authenticated before suspension blocked?
  - [ ] Does suspension apply to every authentication method, including passwords and passkeys?
  - [ ] Is bypassing suspension through password resets or similar flows prevented?
  - [ ] Can an administrator reinstate the account?

:::note[Additional implementation]
Account suspension state and suspend/reinstate operations are not implemented. Revoking sessions still allows a fresh login with valid credentials.
:::

## 3. Executing blocking operations

- [x] Is there a way for an operator to execute a blocking operation?
  - [x] Can it be performed through an administration screen or CLI?
  - [x] Can the target be precisely identified, for example by user ID?
  - [x] Can the outcome be checked?
  - [ ] Can unauthorized users be prohibited from blocking or reinstating users?

:::note[CLI execution permissions]
The checked operation here is CLI session revocation. Select a target with `id:123` or `email:user@example.com` and inspect output, errors, and exit status. The CLI has no application administrator-role check. Restricting server access must be verified operationally.
:::

## 4. Auditing blocking operations

- [ ] Are blocking and reinstatement operations recorded?
  - [ ] Is the operation time recorded?
  - [ ] Are the actor and target user recorded separately?
  - [ ] Are the operation type and result recorded?
  - [ ] Can a reason be recorded when needed?

:::note[Additional auditing is needed]
There are no dedicated session revocation audit events or records of operator identity and reasons. The presence of account change security events does not satisfy this category.
:::

## 5. Invalidating authentication tokens (when API token authentication is used)

- [ ] Can authentication credentials other than sessions, such as API tokens, be invalidated?
  - [ ] Can all issued tokens for a user be revoked together?
  - [ ] Can a specific token be revoked?
  - [ ] Is token-based access blocked while an account is suspended?

:::note[Not applicable to the current kit]
This kit does not include API token authentication. If added, separately verify token revocation and suspension checks.
:::

## 6. Individual session management (optional extension)

- [x] Can active user sessions be listed?
- [x] Can a specific device or session be revoked?
- [x] Can users revoke their own sessions on other devices?

:::tip[User-facing session management]
Users can list sessions, revoke one session, or revoke other sessions together in settings. Individual revocation also rotates the user-wide remember token. There is no administration screen for managing another user's individual sessions.
:::

## 7. Emergency blocking (optional extension)

- [ ] Can all users' sessions be revoked in one operation?
- [x] Can access to the application be temporarily restricted?
- [ ] Can automatic blocking triggered by serious anomaly detection be added?

:::note[Maintenance mode]
Laravel's standard maintenance mode (`php artisan down`, restored with `php artisan up`) temporarily restricts access. It does not revoke everyone's sessions or interrupt requests already in progress. A dedicated command to revoke all users' sessions and anomaly-triggered automatic blocking are not implemented.

Global and automatic blocking can interrupt business operations or react to false positives. Consider them according to actual needs.
:::

## 8. Operations and verification (verify in the deployment environment)

- [x] Are execution procedures documented?
- [ ] Can operators execute blocking operations in an emergency?
- [x] Can revocation be verified while signed in on multiple devices?
- [ ] Can rejection of fresh logins after account suspension be verified?
- [ ] Can reinstatement and normal authentication be verified?

:::note[Verify in the operational environment]
[Session management](../../sessions/) documents browser and CLI procedures. Tests cover revocation across multiple clients, but production permissions and emergency execution arrangements need operational verification.
:::

## 9. Reliability of blocking

- [ ] Is suspension checked on authenticated requests too?
- [ ] Does blocking cover every applicable access path, including Web and API?
- [x] Can Remember Me automatic reauthentication be prevented?
- [x] Is forced revocation supported by the session storage in use?
- [ ] Is bypassing suspension prevented when login races with session deletion?
- [ ] Is the time at which blocking takes effect defined?
- [x] Is the difference between session revocation and account suspension clear?
- [ ] Do revoked sessions stay invalid after account reinstatement?

:::caution[Checks apply to database sessions]
The storage check applies only to the database configuration. This kit cannot revoke sessions stored in file, Redis, or other backends. This check does not guarantee blocking timing across login races, requests already in progress, or every access path.
:::

## 10. Administrative safety and automated tests

- [ ] Are administrator permissions for blocking and reinstatement clearly defined?
- [ ] Are permission checks enforced on the server?
- [ ] Do administrative actions have CSRF protection and any necessary reauthentication?
- [ ] Is handling defined for blocking the acting administrator or last administrator?
- [ ] Are unauthorized attempts and failures audited too?
- [x] Can access revocation across multiple devices be tested automatically?
- [ ] Is suspension tested for each authentication method, including passwords and passkeys?
- [ ] Is there an end-to-end test for suspension, reinstatement, and a fresh login?

:::tip[Automated test coverage]
`SessionManagementTest` and `RevokeUserSessionsCommandTest` check revocation across clients. User-facing Web actions have checks for authentication, confirmation, and rejection of other users' sessions. These differ from administrator suspension/reinstatement permission checks, so those items remain unchecked. See the [test inventory](../tests/).
:::

## Assessment criteria

- **Implemented**: The feature exists and expected blocking behavior can be verified.
- **Partially implemented**: The feature exists but does not block certain authentication methods or access paths.
- **Not implemented**: No blocking mechanism exists.
- **Not applicable**: The authentication method or feature is not used.

## Implementation principles

1. Reuse the authentication foundation; avoid unnecessary custom authentication logic.
2. Treat session revocation and account suspension as separate operations.
3. Prioritize manually executable blocking operations.
4. Audit blocking and reinstatement.
5. Add automatic blocking, individual session management, and global blocking as needed.
6. Address WAF, network blocking, and infrastructure access restrictions in a separate checklist.
