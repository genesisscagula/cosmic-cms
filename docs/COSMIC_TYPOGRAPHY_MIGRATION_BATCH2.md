# Cosmic Typography Migration — Batch 2

Registered Spark components audited: 314

The migration is applied at the common render boundary rather than editing hundreds
of individual Spark files. This means existing and future registered Sparks inherit
the centralized typography contract automatically.

Semantic mapping:
- h1 => global H1
- h2 and top-level Luna heading targets => global H2
- h3 and repeatable/card heading targets => global H3
- h4 => global H4
- hero descriptive text => Lead
- other paragraphs/text targets => Body
- top-level labels => Eyebrow
- repeatable/card labels => Small/Meta
- links/buttons => Button typography

Existing saved per-section Luna typography tweaks are bridged to `--cosmic-local-*`
variables, preserving section-specific behavior without mutating the global system.

Special art-directed typography can use `data-cosmic-type-lock="1"` together with
local locked variables. Batch 3 will route Luna global-vs-local requests into these
tokens explicitly.
