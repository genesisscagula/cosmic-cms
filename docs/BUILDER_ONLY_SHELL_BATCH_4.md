# Builder-only Shell Consolidation — Batch 4

**PASS — static integration QA**

## User-facing architecture
The separate Dashboard **Website Shell** editing surface has been removed.

The Builder is now the single editing experience:

**Global Header → Page Sections → Global Footer**

Header/footer remain global website data underneath. They are not converted into ordinary page Sparks.

## Builder
Header and footer now expose explicit `data-cosmic-shell-region` markers and subtle hover labels showing that edits are site-wide. Existing individual manual + Luna controls remain in place.

## Persistence
Builder Save continues to submit:
- `global_header`
- `global_footer`
- page `blocks`
- theme settings

The legacy `updateBlocks` endpoint was brought into parity by adding `global_footer` persistence too.

## Publish/export
Publishing snapshots:
- `published_global_header`
- `published_global_footer`

Static deployment reads the published shell snapshots, while the existing header/footer compilers remain the output layer.

## Runtime QA
Browser/runtime checks are still required after installing this patch: Save → hard refresh → another page → Publish → preview/live.
