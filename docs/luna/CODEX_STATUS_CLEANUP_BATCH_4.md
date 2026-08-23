# Codex-Style Status + Confirmation Cleanup — Batch 4

## Phase-aware status

Every Luna request begins with a neutral:

`Thinking…`

Conversation stays neutral until the reply returns.

### Build
`Thinking… -> Planning… -> Choosing the design… -> Building your page… -> Checking the result…`

### Update
`Thinking… -> Preparing changes… -> Applying changes… -> Checking the result…`

### Publish
`Thinking… -> Preparing to publish… -> Publishing… -> Checking the result…`

### Navigate
`Thinking… -> Opening it…`

These labels are UI progress language, not proof that a backend step completed. Final success wording still comes from Execution Verification.

## Confirmation cleanup

Normal build/update/publish/navigate no longer use pending confirmation.

The remaining confirmation infrastructure is safety-only for destructive delete actions:
- button label is `Confirm`, not `Continue`
- confirmation itself costs 0 credits
- pending state exists only to protect the destructive operation

## Chat
Chat does not cycle through fake action stages. It shows `Thinking…`, returns the documentation-grounded reply, and stops.
