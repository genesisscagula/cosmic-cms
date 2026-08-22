# Overlay Header → Video/Image Hero Focus Hotfix

Root cause:
Builder applied overlay clearance to the first child of `.cosmic-builder-spark`.
For Sparks rendered through CosmicRenderShell, that first child is the shell wrapper,
not the visual hero `<section>`. Padding the shell exposed the Builder canvas white
background before the hero media started.

Fix:
- Overlay header host remains absolute/non-flowing at the top of the Builder canvas.
- No padding is applied to CosmicRenderShell.
- Header-clearance padding is applied to the actual first rendered hero/banner section.
- Therefore video/background-image media begins at the top of the canvas and runs
  behind the global header while content remains safely below the navigation.
- Export Live already identifies the actual first section; its contract is retained.
