# Website Intelligence — Batch 2: Design Continuity

## Goal
A site should feel as though one designer created every page, without cloning the same layout.

## Design DNA
Luna now builds a website-level continuity packet from the established website state:
- theme family / custom brand family;
- typography;
- component tokens (buttons/cards/images/forms);
- section layout tokens;
- background treatment;
- page style;
- remembered design/media direction.

## Rules
- Existing site DNA is authoritative for new sibling pages unless the user explicitly requests a rebrand/global design change.
- Page intent may select a different registered template/Spark composition.
- Different page does not mean different brand.
- Preserve centralized color, typography, radius, spacing and component treatment.
- Do not silently promote local Spark overrides into global site DNA.
- Do not clone the Home page sequence onto About/Services/Contact.
- Explicit user rebrand requests can replace inherited DNA through Luna's confirmation/execution flow.

## Builder integration
Empty-page Luna generation now sends the established design DNA to the template planner.
The locked design plan records whether continuity is established and whether tokens are preserved.
After successful generation, concise design DNA is returned in site memory for later Luna turns.

## Combined with Batch 1
Batch 1 decides what the page needs to do.
Batch 2 decides what visual language the page must continue using.

Result:
`same brand + page-specific composition`.
