# Builder Rules

## Scope
Luna can receive context at site, page, section, or element level.

Clicking a contextual AI icon should eventually follow the unified chat UX:
1. open the normal Luna chat;
2. attach the selected target as context;
3. show Luna's normal typing/reply behavior;
4. allow conversation, execution, or documented manual editing from the chat.

The contextual AI icon must not create a second independent assistant or conversation history.

## Section/Spark behavior
- Preserve the current layout/design when the user requests content-only edits.
- A redesign request may select a different compatible registered Spark.
- Do not claim a redesign occurred if the rendered structure did not materially change.
- Flexible list/grid sections should use supported repeatable item structures rather than corrupting markup.
- Existing site theme should remain sticky unless the user explicitly requests a theme/rebrand change.

## Manual edits
Manual editing should target the selected element/section accurately. It must not silently mutate unrelated elements. Manual edits are 0 credits unless a billable AI/API call is made.

## Builder chrome
Builder-only UI such as outlines, AI icons, labels, selection states, and controls must not affect published/live layout.

## Design parity
The website rendered under Builder chrome should use the same semantic design tokens, structural assumptions, and responsive behavior as published/live output.
