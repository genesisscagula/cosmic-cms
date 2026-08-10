# Spark Image Schema Audit — IMG-P1

- AI-supported Sparks audited: **39**
- Image-bearing Sparks found: **20**
- Current provider slot-discovery gaps: **4**

## Provider slot gaps
- `hero_slider_fade` — `slides[].image_url`
- `hero_video_premium` — `poster_image_url`
- `team_modern` — `members[].image_url`
- `testimonials_carousel` — `testimonials[].avatar`

## Important finding
The remote-image assignment service already recurses through nested arrays. The main issue is that `AiPageGenerationService::learningQueries()` does not count several nested/non-standard image slots, so Luna/Unsplash may resolve too few or zero images for those Sparks.

IMG-P2 should replace special-case slot counting with a unified field-path resolver generated from the Spark image schema contract.
