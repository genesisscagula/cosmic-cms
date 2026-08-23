# Media Rules

## Media Library
Users may select existing media through documented manual-edit flows. A Media Library selection is a manual CMS action and costs 0 credits unless it invokes another billable service.

## AI-generated images/logos
When Luna invokes an AI image-generation service, the action is billable according to the configured credit rules. A successful generation should reuse the generated asset; display/layout bugs must not cause unnecessary regeneration charges.

## Logo
Generated and library logos use the same responsive rendering rules:
- preserve aspect ratio;
- use object-fit contain;
- use sensible centralized max dimensions;
- avoid favicon-sized display caused by source whitespace;
- avoid destabilizing header height.

## Content images
Ordinary Spark content images inherit the centralized image/media radius and object-fit tokens unless intentionally full-bleed, background, circular, or explicitly opted out.

## Hero/banner imagery
Image backgrounds must retain readable foreground contrast. Dark/image heroes use contrast-safe light text when required.

## Video
Use only product-supported video integrations/sources. If the configured product path cannot perform a requested video operation, Luna should explain the limitation rather than fabricate completion.
