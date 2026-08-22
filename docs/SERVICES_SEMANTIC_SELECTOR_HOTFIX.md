# Services Semantic Selector Hotfix

All eight premium Services Sparks were already present and registered correctly.

Root cause:
The generic Services redesign fallback used a fixed preferred order. That meant Luna
could repeatedly choose the first available premium variant even when the prompt
explicitly requested dark, split, grid, image-led, featured, or bento styling.

This hotfix routes explicit design language to the matching Spark:

- dark / high contrast / performance-focused -> services_dark_premium
- minimal / luxurious / whitespace -> services_minimal_luxury
- editorial / magazine / asymmetric -> services_editorial_premium
- alternating / split -> services_split_premium
- 3-column / grid -> services_grid_premium
- featured service / supporting cards -> services_feature_premium
- more visual / large imagery / showcase -> services_showcase_premium
- bento / varied card sizes -> services_bento_premium

Generic "more premium / better" still selects a materially different premium Services
variant, but now uses ranking instead of fixed-order selection.

Applied to both trial and authenticated Luna pageChat redesign paths.
