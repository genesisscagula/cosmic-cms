# IMG-P3 — Slot-aware Unsplash Population

Implemented:
- one Unsplash resolution path per discovered image slot
- distinct image identity tracking to avoid duplicates in the same generation
- landscape orientation for hero/gallery/service imagery
- portrait orientation for team/testimonial imagery
- Slider Fade gets three separate hero image queries
- Agency before/after gets separate semantic queries
- retry fallback query when a slot-specific query has no unique candidate
- exact block_index + field path metadata on resolved images
- path-aware assignment so one failed provider lookup does not shift later images
- local avatar placeholders can now be replaced for people-oriented generated Sparks
- logos and branding paths remain protected
- trial and registered generation both use the same slot-aware strategy
