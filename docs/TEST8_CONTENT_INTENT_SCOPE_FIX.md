# Test 8 Content Intent Scope Fix

Fixes `Undefined variable $contentOnlyIntent` in authenticated `pageChat()`.

- Initializes content-only intent immediately after `$normalizedPrompt`.
- Ensures all later mutation/media guards can safely use it.
- Preserves the content-only scope lock added in the prior hotfix.
- No changes to Tests 1–7 behavior.
