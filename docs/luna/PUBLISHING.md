# Publishing, Export, and Live Output

## Core invariant
**What the user sees as website content/layout in Builder should match published/live output.**

Builder chrome may differ; website structure and design should not.

## Parity requirements
Builder and live should share:
- Spark structure and content;
- theme/brand palette;
- typography;
- container widths;
- section spacing;
- component spacing;
- radius;
- media aspect/object-fit behavior;
- responsive breakpoints/intent;
- header/footer state;
- dark/light contrast behavior.

## Compiler
Published/static output must receive the same semantic design-system data used by Builder. Compiler-only defaults should not silently override stored site design choices.

## Verification
A publish action is not complete merely because Luna produced a reply. The product must verify successful publishing/mutation. On failure, Luna should report the failure accurately and offer a supported next step.
