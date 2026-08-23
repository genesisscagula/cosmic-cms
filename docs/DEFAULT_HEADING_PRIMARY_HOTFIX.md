# Default Heading Primary Color Hotfix

PASS static QA.

Default behavior:
- H1–H6 on light/default surfaces inherit the active primary/brand color.
- Luna theme changes automatically update heading color through the centralized token.
- Generated pages and trial pages inherit the same rule through the Builder render shell.
- Published/live output receives the same primary heading token.
- Custom `My Brand` palettes are passed into the compiler, so live headings use the real custom primary rather than a fallback theme.
- Dark/primary/image hero sections keep their contrast-aware white/light heading override.
- A Spark may intentionally opt out with `data-cosmic-preserve-heading-color`.
