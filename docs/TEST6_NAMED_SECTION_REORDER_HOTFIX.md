# Test 6 — Named Section Reorder Hotfix

Adds a deterministic selected-section reorder router before the generic AI planner.

Supported:
- Move this section above/before Services, Testimonials, Pricing, FAQ, Contact, CTA,
  Gallery/Portfolio, Process, Team, About, Stats/Why Choose Us, Hero/Banner.
- Move below/after a named section.
- Move to top/bottom.
- Move one section up/down.

Behavior:
- Moves the existing block only; no duplicate, regeneration, content, theme, or layout mutation.
- Resolves named targets from SparkCatalog category/type/name plus current heading/title/eyebrow.
- Corrects indexes for removal-before-insertion.
- Returns 0 credits because this is a deterministic structural control with no AI/API request.
- Returns a useful no-target/already-positioned reply instead of a false success.
