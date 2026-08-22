# Semantic Typography + Media Master Batch

## 1. Asymmetric 1 + 3 layout audit
- Expansion split/featured/spotlight families no longer force a three-row right stack.
- Tall first-card layouts use a two-row span.
- Team Leadership Split renders 1 large + 2 stacked items while retaining all repeater data for Luna/manual CRUD.

## 2. Video hero manual media controls
- Video-capable sections expose Video URL and Video Thumbnail / Poster together in the section manual editor.
- Poster can be selected from Media Library; trial mode can upload it.
- Poster replacement supports poster_image_url, poster_url, thumbnail_url, video_thumbnail_url and video-style image_url.
- Thumbnail preview areas are marked no-Luna-hover so editing stays consolidated in the section manual panel.

## 3. Badge / small-text specificity
- Added semantic `badge` and `meta` typography roles.
- Hero badge text such as “Built for the road” is tagged as `badge`.
- Generic `[data-luna-target=text]` and label rules now apply only when no explicit semantic typography role exists.

## 4. Card title + stat title hierarchy
- Card title default is balanced at clamp(1.25rem, 1.65vw, 1.65rem).
- Added dedicated `stat-title` for compact statistic/proof cards.
- Existing `data-cosmic-type` props on EditableText now reach the DOM, fixing previously ignored card-title semantics.

## 5. Global H1–H6 semantic system
- Centralized H1, H2, H3, H4, H5, H6, card-title, stat-title, body, card-body, lead, eyebrow, small, badge, meta and button roles.
- Main hero `data.heading` fields migrate to H1.
- Main non-hero `data.heading` fields migrate to H2.
- Expansion section headings explicitly use H2 and card items retain card-title/stat-title.
- Untyped Luna heading targets default to H3 instead of being incorrectly forced into H2.
- Luna typography commands now understand H5, H6, badges, meta and stat titles.
- Local `luna_typography_overrides` supports every new role, preserving special per-section requests without changing global typography.
- Builder and Export Live share the same semantic token contract.
