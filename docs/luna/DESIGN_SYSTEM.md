# Centralized Design System

Cosmic CMS uses centralized semantic design tokens. Individual Sparks should consume these tokens instead of hardcoding reusable site-wide styling.

## Brand/color tokens
Include primary, secondary, accent, neutral/surface values, semantic success/warning/error, readable foregrounds, borders, and relevant gradients.

When a user explicitly asks Luna to build a theme around a HEX such as `#224248`, Luna may design a premium semantic family. The server validates and repairs the family. The exact requested HEX remains the primary anchor.

## Heading colors
H1–H6 default to the active primary/brand color on light/default surfaces. Dark, brand, image, cinematic, or otherwise contrast-sensitive sections may override headings to a readable light foreground. Contrast context has priority over the generic heading-primary rule.

## Typography
Centralized tokens cover H1–H6, body, small/eyebrow typography, font family, size, weight, line-height, and letter-spacing, including responsive values.

## Radius
Centralized radius tokens cover buttons, cards, images/media, inputs, modals, and appropriate section/surface treatments. Ordinary Spark images should inherit the centralized image radius unless an intentional full-bleed/background/shape treatment opts out.

## Spacing and layout
Centralized tokens cover section vertical padding, horizontal padding, container gaps, card padding, grid gaps, and normal/narrow/wide container widths.

## Components
Buttons, forms, cards, links, borders, shadows, surfaces, and media should consume semantic component tokens. Luna should prefer changing the design system for site-wide requests rather than patching every Spark independently.

## Responsive behavior
Desktop/tablet/mobile typography, spacing, and appropriate component values should be centralized where possible.
