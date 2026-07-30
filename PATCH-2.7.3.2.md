# Cosmic CMS v2.7.3.2 — AI Context, Spark Preview, Image Fallback & Pricing

## Enhancement 1 — Smarter AI context resolution
- Start-page generation remains driven by the user prompt.
- Builder page generation now places the Website Profile context first and appends the user instruction.
- Section planning and content generation use the same resolved context.

## Enhancement 2 — Premium Spark preview animation
- Cycles White → Primary → Surface.
- Displays each variant for 2.5 seconds.
- Uses a 600ms fade, scale, and translate transition.
- Runs only while the Spark preview is open.
- Manual variant selection resets the cycle timer.

## Enhancement 3 — Neutral default image fallback
- Replaces the hard-coded `construction` fallback with `default`.
- Adds `storage/app/public/cms-images/default/.gitkeep`.
- Falls back to Picsum only when both the requested folder and default folder contain no images.

## Enhancement 4 — Cosmic Credits pricing refresh
- Adds ⭐ Starter, 🚀 Growth, and 👑 Pro plan icons.
- Repositions plans around monthly Cosmic Credits, Sparks access, AI model access, and advanced workspace features.
- Keeps Growth highlighted as the popular plan.

## Safety
- No database migrations.
- No changes to existing credit deduction logic.
- No changes to authentication or ownership records.
