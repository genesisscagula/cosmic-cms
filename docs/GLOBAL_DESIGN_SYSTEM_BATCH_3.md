# Global Design System — Batch 3

Centralized component token foundation.

## Buttons
- primary / secondary / outline semantic variants
- height, horizontal padding, radius, weight
- background/text/border tokens
- hover movement token

## Forms
- input height/padding/radius
- background/text/placeholder/border
- focus color + focus ring
- label color/weight

## Cards
- background/text/heading/border
- padding/radius/shadow

## Media
- image radius
- object-fit
- auto / square / landscape / wide / portrait aspect-ratio presets

## Links
- normal/hover colors
- decoration and underline offset

## Reusable primitives
Added `CosmicComponents.jsx` for Button, Card, Image, Input, Textarea, Select, Label and Link.
Added global/local component variable mappers and wired them into the Builder render shell.

## Compatibility
This batch is opt-in and does not mass-rewrite every Spark yet. Existing special dark, overlay, cinematic and commerce components retain their current behavior until the controlled migration batch.
