# Luna Spark Return Audit — 100 Premium Expansion Sparks

## Scope

Audited the 100 premium Expansion Sparks that already had frontend renderers but were not fully reachable through Luna generation/redesign.

## Root cause found

Before this patch:
- 100/100 had frontend renderer implementations.
- 100/100 had Cosmic Spark metadata overrides.
- 0/100 were fully wired through both the backend Luna planner registry and content schema map.
- Because Luna's verified redesign path filters candidate Sparks through SchemaManager, these layouts could exist in the UI code but still never be selected/generated reliably.

## Batch 1 — Luna registration

All 100 Expansion Sparks are now registered in:
- SparkPlannerRegistry
- SchemaManager
- ContentGenerator type-specific schemas

Every generated schema supports:
- theme = auto
- eyebrow / heading / text
- primary + secondary CTA
- 4–6 repeatable items
- label / title / text / image_url per item
- blank image URLs for Cosmic provider hydration
- industry-context guardrails
- no fabricated awards, metrics, people, addresses, or guarantees

## Batch 2 — LayoutEngine reachability

The same 100 layouts were added to their compatible LayoutEngine category pools so they can participate in single-section generation/fallback selection, including:
- Services
- About
- Features
- Portfolio/Gallery
- Process
- Statistics/Proof
- Team
- Testimonials
- CTA
- Contact
- Content
- industry-specific service layouts

## Batch 3 — Image reliability

The five shared Expansion renderer batches now guard empty image URLs instead of rendering broken `<img src="">` elements. Provider-hydrated images still render normally; a theme surface is used only while a valid image is absent.

## Batch 4 — Services premium family

Fixed Services Split Premium:
- removed synthetic gradient-only media panels
- added real per-service image slots
- alternating rows now render actual image + copy
- keeps the active website theme

Fixed Services Feature Premium:
- added a large featured image
- supporting cards may render their own relevant images
- remains theme-aware

New image slots:
- featured_image_url
- service_two_image_url
- service_three_image_url
- service_four_image_url
- service_five_image_url
- service_six_image_url
- service_seven_image_url

These field names are compatible with Cosmic's ImageSlotResolver and remote Unsplash assignment flow.

## Final static verification

- Expansion premium Sparks discovered: 100
- Renderer-ready: 100/100
- Planner-ready: 100/100
- Content-schema-ready: 100/100
- Spark metadata override-ready: 100/100
- Missing registry entries: 0
- PHP lint: PASS on all changed backend files
- Services Split real-media contract: PASS

## Recommended QA sequence

1. Services Split Premium with explicit automotive imagery.
2. Services Feature Premium with one featured automotive service/image.
3. One image-heavy Portfolio Spark.
4. One Team/Testimonials image Spark.
5. One industry Spark such as restaurant/construction/real-estate.
6. Generic "make this section more visual" and confirm Luna selects a compatible image-rich alternative.
7. Publish + hard refresh to confirm generated media persists.
