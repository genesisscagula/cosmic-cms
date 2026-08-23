# Website Intelligence — Batch 8: Builder ↔ Live Parity

## Goal
Reduce structural divergence between Builder rendering and published/live rendering by enforcing one render contract.

## Fixed in this batch

### Registered Spark renderer coverage
The Builder registry currently contains 321 registered Sparks.

Before this patch, seven registered Services variants existed in Builder but had no dedicated static compiler case:
- services_editorial_premium
- services_showcase_premium
- services_minimal_luxury
- services_contrast_premium
- services_split_premium
- services_grid_premium
- services_feature_premium

All seven now have live/export compiler rendering. Static registry-to-compiler coverage is now 321 / 321.

### Shared render contract marker
Builder Spark roots and live compiler Spark roots now expose:
`data-cosmic-render-contract="2026.08-b8"`

This gives QA/runtime tooling a deterministic way to identify the render-contract generation.

### Central token parity
The live compiler now consumes more of the same centralized design values used by Builder:
- desktop/tablet/mobile button height and horizontal padding;
- desktop/tablet/mobile input height;
- desktop/tablet/mobile card padding;
- full H1-H6 and semantic typography roles;
- tablet/mobile typography sizes;
- responsive grid gap and card padding;
- content/default/full container tokens.

Existing brand/background/local override contracts remain preserved.

### Render parity diagnostics
Added `LunaRenderParityService`.

It reports:
- render contract version;
- registered renderer coverage;
- unsupported block types on the current page;
- page data/context fingerprint;
- shared contracts;
- whether runtime visual parity has actually been verified.

The service intentionally reports:
`runtime_visual_parity_verified = false`
until a real rendered Builder-vs-Live comparison has been performed.

### Publisher
Published deployment packages now include:
`render_contract: 2026.08-b8`

## Important boundary
321/321 compiler coverage means every registered Builder Spark has a live compiler path. It does NOT prove pixel-perfect runtime parity.

Differences can still come from:
- JavaScript motion/runtime behavior;
- Tailwind build output;
- browser viewport;
- external image/video loading;
- animation timing;
- live deployment CSS caching;
- one renderer using a slightly different class/layout implementation.

Therefore the planned representative 10-Spark runtime audit remains required.
