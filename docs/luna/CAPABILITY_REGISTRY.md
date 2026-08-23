# Capability Registry

Machine-readable registry: `resources/luna/capabilities.json`

Laravel access:
- `config/luna_capabilities.php`
- `App\Services\LunaCapabilityRegistry`

## Capability fields
- `id` — stable machine identifier.
- `name` — user-facing capability name.
- `category` — routing group such as header, footer, media, pages, publishing, conversation.
- `status` — supported, partially_supported, planned, or unsupported.
- `scopes` — site/page/section/element/header/footer contexts where the capability is relevant.
- `manual` — whether a manual CMS path exists.
- `luna` — whether Luna currently has an execution path.
- `confirmation` — direct, confirm_large, confirm_destructive, clarify_if_missing, or informational_only.
- `credit_behavior` — zero_manual, billable_ai, runtime_defined, or no_execution.
- `can_do` — documented supported behavior.
- `cannot_do` — explicit negative capability/guardrail.
- `limits` — structured limits such as max navigation depth.
- `fallback` — closest supported response/action.
- `verification` — what must be checked before Luna reports success.

## Runtime rule
The registry does not override server enforcement. Security, permissions, billing, schema validation, and hard guardrails remain authoritative.

## Batch ownership
Batch 2 creates lookup/runtime data only. Batch 3 will use the registry to retrieve relevant capability context before Luna answers product/capability questions.
