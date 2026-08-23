# Global Design System — Batch 4

**PASS**

All **322 registered Sparks** now pass through the centralized design-system render shell.

Templates are composed from registered Sparks, so template-rendered sections inherit the same semantic token system rather than requiring duplicated per-template CSS rewrites.

Migrated centrally:
- standard card/image/button/input radii
- standard shadow scale
- CTA-like button radius/weight
- common form control radius
- common card surfaces
- rounded media
- standard container ceilings
- old Luna card/image radius overrides

Protected:
- immersive/cinematic layouts
- explicit white/on-dark treatments
- circles/pills
- icon-only controls
- asymmetric/special radii via preserve hooks
- edge-to-edge/fullscreen media
- specialized form controls

Legacy style inventory under registered Block source:
```json
{
  "rounded": 590,
  "shadow": 81,
  "maxw": 340,
  "buttons": 113,
  "images": 44
}
```
