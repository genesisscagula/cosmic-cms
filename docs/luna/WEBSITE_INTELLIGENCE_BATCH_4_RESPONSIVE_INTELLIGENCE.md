# Website Intelligence — Batch 4: Responsive Intelligence

## Goal
Luna plans and generates pages with deliberate desktop, tablet, and mobile behavior rather than treating responsive output as a later CSS accident.

## Responsive contract
A deterministic responsive-intelligence profile defines:
- tablet/mobile breakpoints;
- section vertical/horizontal spacing;
- grid gaps and card padding;
- button sizing;
- mobile reading-order expectations;
- grid/card collapse expectations;
- media/object-fit behavior;
- CTA wrapping/stacking;
- overflow prevention;
- touch/hover interaction rules.

## Planner integration
The template planner receives the responsive contract together with the page-composition contract.
Responsive-risk Sparks are identified from their registered IDs and metadata patterns such as:
- grid/bento/cards/comparison/pricing;
- gallery/slider/video/parallax;
- horizontal/marquee/timeline/table;
- slider/carousel/hover/interactive behavior.

Responsive risk does not automatically reject a Spark. It tells Luna to prefer a compatible template with a clear small-screen strategy.

## Content integration
Content generation receives the same responsive contract and is instructed to:
- keep button/label text from overflowing;
- keep grid/card sibling copy reasonably balanced;
- keep media-led section copy concise;
- avoid desktop-only text density.

## Central responsive tokens
New/filled responsive component tokens include:
- button height/padding for tablet/mobile;
- input height for tablet/mobile;
- card padding for tablet/mobile;
- existing section/grid/card responsive spacing tokens.

Existing site values win. Defaults only fill missing responsive token values.

## Runtime CSS
Builder and export styles now share the responsive component contract for:
- max-width-safe media;
- wrapping long headings/links/buttons;
- responsive button/input/card sizing;
- min-width safety for grids;
- touch-friendly mobile buttons.

## Important
This batch establishes responsive intelligence and centralized primitives. Batch 8 will perform the deeper Builder vs Live parity audit across representative Sparks.
