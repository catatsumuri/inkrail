---
paths:
    - '**/*'
---

# General

## Run the complete CI check before finishing changes

Before marking changes complete, run `vendor/bin/sail composer ci:check` and confirm it succeeds. If it cannot be run or fails, report the reason and any unresolved failures. Do not substitute only targeted checks for this final check.
