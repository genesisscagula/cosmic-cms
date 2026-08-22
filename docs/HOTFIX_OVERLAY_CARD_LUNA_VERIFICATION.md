# Hotfix — Overlay Header + Card Title + Luna Verified Billing

1. Overlay Header
- Explicit overlay state is no longer silently blocked by Clean page style or Stone/White theme families.
- Builder and Export Live now honor the saved overlay request consistently.
- Contrast logic remains responsible for readable logo/navigation colors.

2. Card title parity
- Central card title default reduced to max 1.55rem.
- Semantic card-title sizing is now `!important` in Builder and Export contracts to beat legacy Spark CSS.
- Export also includes a compatibility fallback for older card/article H3/H4 markup that has not yet migrated to the semantic data role.

3. Luna billing verification
- Trial and authenticated page chat now calculate before/after state first.
- Execution credits are consumed only when a block or shell mutation is actually verified.
- If Luna returns “I could not verify a real change…”, execution credit_cost is 0.
