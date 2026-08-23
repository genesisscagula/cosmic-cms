# Luna AI Premium Color Family — One Batch

**PASS — static integration QA**

Example supported request:

> Can you make the website theme into this brand #224248

## Behavior
Luna is asked to design a complete premium semantic palette around the exact supplied HEX. The server validates the proposal before it reaches the Builder. The exact supplied HEX is always retained as primary, heading, and primary-button color.

## Semantic family
Primary/hover/soft, secondary, accent, page background, surface, alternate surface, heading, body, muted, border, primary/secondary button colors, success/warning/error, on-primary/on-dark, and a four-stop semantic gradient.

## Guardrails
Invalid/missing HEX values are repaired from a deterministic premium fallback. Text/button contrast is checked, weak palette separation is repaired, and a HEX rebrand cannot accidentally trigger an unrelated named theme.

## Parity
The full family is persisted as `My Brand`, installed into Builder CSS tokens, mapped into component tokens, and passed to the static compiler for published/live output.
