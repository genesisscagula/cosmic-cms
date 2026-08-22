# Test 3 local typography verification hotfix

The deterministic typography router supported generic heading mapping after its intent gate,
but the gate itself did not recognize generic heading/headline/title wording.

Now:
- "Make this section's heading slightly smaller" is intercepted deterministically.
- A selected ordinary section maps generic heading to local H2.
- A selected hero maps generic heading to local H1.
- Local changes write luna_typography_overrides only on the selected block.
- "Make all H2 headings across the website slightly smaller" remains global H2 only.
- Deterministic typography costs 0 credits because no AI/API request is made.
