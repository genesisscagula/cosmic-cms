# Chat vs Action Router — Batch 1

## Goal
Reduce Luna's top-level routing to two states only:

- `chat`
- `action`

The secondary `action` field carries what Luna should do:
- `build`
- `update`
- `publish`
- `delete`
- `navigate`

For chat, `chat_type` may be:
- `general`
- `help`
- `capability`

## Canonical output
Example chat:

```json
{
  "intent": "chat",
  "chat_type": "capability",
  "action": "none",
  "scope": "none",
  "execution_allowed": false
}
```

Example build:

```json
{
  "intent": "action",
  "action": "build",
  "scope": "page",
  "target": "home",
  "entities": {
    "industry": "restaurant",
    "location": "Ormoc City"
  },
  "execution_allowed": true
}
```

## Routing rule
Downstream code reads `intent` first.

`chat`:
- reply only
- no mutation

`action`:
- enter the operation pipeline
- use `action`, `scope`, `target`, and `entities` as parameters

This batch changes the router and compatibility checks only.
Normal confirmation/pending-action behavior remains temporarily in place and is removed in Batch 2.
