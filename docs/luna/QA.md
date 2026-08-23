# Luna QA Checklist

## Documentation QA
For every Luna-facing feature:
- Is the capability documented?
- Is its status correct?
- Are scope and limits documented?
- Is credit behavior documented?
- Does it require clarification or confirmation?
- Is there a verified execution path?
- Is the closest supported fallback documented?

## Conversation QA
Test:
1. "What can you do?"
2. A supported capability question.
3. A partially supported capability question.
4. An unsupported request.
5. A large creative request requiring confirmation.
6. "Proceed" against a pending action.
7. A small explicit edit that can execute directly.
8. An ambiguous request requiring clarification.
9. A contextual AI-icon conversation.
10. A failed mutation/publish response.

## Execution QA
Luna's final response must match the actual mutation result.

## Parity QA
For Builder/live visual changes, compare representative Sparks after publish and verify design-system, media, header/footer, and responsive parity.
