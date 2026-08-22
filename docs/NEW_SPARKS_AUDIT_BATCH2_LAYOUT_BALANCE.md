# 100 New Sparks Audit — Batch 2: Layout Flow + Dead-Space Balance

Scope: all five expansion families / 100 new Sparks.

Goal:
Preserve each Spark's asymmetric/editorial/mosaic character while removing accidental
large blank zones caused by row-span mismatches, non-flowing transforms, or a featured
card that does not visually fill the height of its stacked companion cards.

Fix families:
- Split / Featured / Spotlight / Story / Agent / Destination:
  featured first card now fills the complete stacked height; its image flexes to use
  the remaining visual area rather than leaving a large blank panel.
- Mosaic / Wall / Asymmetric / Collage / Gallery / Progress / Staggered:
  top row spans are balanced so CSS Grid does not create a hidden one-row hole.
- Staggered / Floating / Panels / Overlap:
  visual offsets use margin instead of transform so the parent grid accounts for
  the offset and does not create misleading bottom/top whitespace.
- Mobile:
  stagger offsets collapse to zero and grids return to natural single-column flow.

No layouts were converted into generic equal-card grids. Content enrichment remains Batch 3.
