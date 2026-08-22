# Test 2 schema-selection root fix

Root cause confirmed from Laravel log:
ContentGenerator received a replacement Spark that resolved to zero supported schemas.

Fixes:
- Authenticated pageChat generic redesign now uses the schema-aware deterministic alternative selector (previous robust patch had landed in the trial path, not the authenticated path).
- Generic redesign discards unsupported/no-op model edit/replace operations on the selected section.
- Services redesign prefers registered alternatives such as Horizontal, Interactive Tabs, Hover Cards, and Mega Grid.
- SelectedSchemaLoader cache version bumped to 18.0.0.
- Added self-healing: if a cached schema-selection result is empty while the requested Spark exists in the live schema map, it recomputes uncached immediately.
