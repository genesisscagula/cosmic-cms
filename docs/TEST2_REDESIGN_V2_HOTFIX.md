# Test 2 Redesign V2 Hotfix

Problem:
A compatible alternative Spark was selected, but a single-Spark content generation failure could still leave the page unchanged.

Fix:
- Services redesigns prefer materially different service layouts: Horizontal, Interactive Tabs, Hover Cards, Mega Grid.
- AI generation remains the primary path.
- If that single-Spark generation fails validation/API execution, a deterministic schema-shaped migration preserves existing service copy into the selected registered Services Spark.
- Current site theme remains sticky.
- Successful fallback still produces a real type/layout change and can be verified.
- `applied_operations` records `content_fallback=true` when this recovery path is used.
