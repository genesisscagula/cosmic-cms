# Cosmic Typography Core

Batch 1 establishes the centralized semantic typography contract.

Roles:
- h1, h2, h3, h4
- lead, body
- eyebrow, small/meta
- button/link text

Global values use `--cosmic-type-*` variables.
A specific Spark/section may override only itself through `--cosmic-local-*` variables.

Reusable React components live in:
`resources/js/Pages/Websites/Components/CosmicTypography.jsx`

Export/Live receives the same token contract from `CmsHtmlCompiler`.

Batch 2 must migrate existing Sparks to these semantic roles and remove hard-coded
font-size/line-height/weight/letter-spacing declarations where the global role owns them.
Special cinematic typography may keep explicit local overrides rather than bypassing the contract.
