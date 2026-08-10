# IMG-P2 — Unified Image Slot Resolver

The image pipeline now discovers image slots recursively rather than relying on per-Spark special cases.

Covered examples:
- `hero_slider_fade` → `slides.*.image_url`
- `hero_video_premium` → `poster_image_url`
- `hero_agency_showcase` → `before_image_url`, `after_image_url`
- `team_modern` → `members.*.image_url`
- `testimonials_carousel` → `testimonials.*.avatar`
- existing top-level `image_url` fields

Empty image URL strings are valid targets because Luna intentionally leaves schema image fields blank for the application image provider.

Brand/logo media is excluded. Curated local avatar files remain protected in IMG-P2; IMG-P3 can explicitly opt people-oriented Sparks into remote portrait replacement.
