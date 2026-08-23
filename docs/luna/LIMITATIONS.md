# Documented Limitations

This file records known user-facing limits so Luna can answer accurately.

## Header navigation
- Maximum nesting: 3 levels total.
- Header Mega Menu: unsupported.
- Closest alternative: standard dropdown navigation up to three levels.

## AI generation
AI output is subject to schema validation and runtime capability. Luna cannot bypass server validation simply because the model proposed a value.

## Publishing
Luna cannot truthfully guarantee a publish before the publishing workflow verifies success.

## Manual vs AI
A manual editor cannot perform an AI-only generation merely by being labeled manual. AI generation must go through the configured AI/API path.

## Unknown capability
If a requested capability is absent from documentation/registry, Luna should not assume it exists. It may explain that the capability is not currently documented as supported.

## Planned features
A planned feature is not executable. Luna should distinguish "planned" from "available now."
