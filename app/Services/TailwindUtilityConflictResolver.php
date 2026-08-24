<?php

namespace App\Services;

/**
 * Deterministic conflict resolver for Luna Tailwind token patches.
 *
 * Tailwind's cascade is order-sensitive and dynamic schema patches are appended
 * after legacy classes. This service identifies mutually-exclusive utility
 * families so the mutation engine can remove stale peers instead of leaving
 * ambiguous class stacks (for example text-5xl + text-4xl).
 */
final class TailwindUtilityConflictResolver
{
    /**
     * Return tokens from the rendered/current class list that conflict with an
     * added token at the exact same variant scope (base, md:, hover:, etc.).
     *
     * @return array<int,string>
     */
    public function conflictsFor(string $addedToken, array $currentTokens): array
    {
        $added = $this->describe($addedToken);
        if ($added['family'] === null) return [];

        $conflicts = [];
        foreach ($currentTokens as $currentToken) {
            if (! is_string($currentToken) || $currentToken === $addedToken) continue;
            $current = $this->describe($currentToken);
            if ($current['family'] === null) continue;
            if ($current['variant'] !== $added['variant']) continue;
            if ($current['family'] !== $added['family']) continue;
            $conflicts[] = $currentToken;
        }

        return array_values(array_unique($conflicts));
    }

    /**
     * @return array{variant:string,utility:string,family:?string}
     */
    public function describe(string $token): array
    {
        [$variant, $utility] = $this->splitVariant($token);
        $plain = ltrim($utility, '!');
        $plain = preg_replace('/^-/', '', $plain) ?: $plain;

        return [
            'variant' => $variant,
            'utility' => $utility,
            'family' => $this->family($plain),
        ];
    }

    /** Split variants without treating colons inside arbitrary values as separators. */
    private function splitVariant(string $token): array
    {
        $parts = [];
        $buffer = '';
        $square = 0;
        $round = 0;
        $length = strlen($token);

        for ($i = 0; $i < $length; $i++) {
            $char = $token[$i];
            if ($char === '[') $square++;
            elseif ($char === ']' && $square > 0) $square--;
            elseif ($char === '(') $round++;
            elseif ($char === ')' && $round > 0) $round--;

            if ($char === ':' && $square === 0 && $round === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $parts[] = $buffer;

        $utility = (string) array_pop($parts);
        return [implode(':', $parts), $utility];
    }

    private function family(string $utility): ?string
    {
        // Typography. Keep text color separate from text size/alignment.
        if (preg_match('/^text-(xs|sm|base|lg|xl|[2-9]xl|\[.*\])$/', $utility)) return 'text-size';
        if (preg_match('/^text-(left|center|right|justify|start|end)$/', $utility)) return 'text-align';
        if (preg_match('/^font-(thin|extralight|light|normal|medium|semibold|bold|extrabold|black|\[.*\])$/', $utility)) return 'font-weight';
        if (preg_match('/^leading-/', $utility)) return 'line-height';
        if (preg_match('/^tracking-/', $utility)) return 'letter-spacing';
        if (preg_match('/^(uppercase|lowercase|capitalize|normal-case)$/', $utility)) return 'text-transform';
        if (preg_match('/^(italic|not-italic)$/', $utility)) return 'font-style';
        if (preg_match('/^(underline|overline|line-through|no-underline)$/', $utility)) return 'text-decoration-line';

        // Layout/display.
        if (preg_match('/^(block|inline-block|inline|flex|inline-flex|grid|inline-grid|flow-root|contents|table|hidden)$/', $utility)) return 'display';
        if (preg_match('/^flex-(row|row-reverse|col|col-reverse)$/', $utility)) return 'flex-direction';
        if (preg_match('/^flex-(wrap|wrap-reverse|nowrap)$/', $utility)) return 'flex-wrap';
        if (preg_match('/^items-/', $utility)) return 'align-items';
        if (preg_match('/^justify-/', $utility)) return 'justify-content';
        if (preg_match('/^content-/', $utility)) return 'align-content';
        if (preg_match('/^self-/', $utility)) return 'align-self';
        if (preg_match('/^place-items-/', $utility)) return 'place-items';
        if (preg_match('/^place-content-/', $utility)) return 'place-content';
        if (preg_match('/^grid-cols-/', $utility)) return 'grid-cols';
        if (preg_match('/^grid-rows-/', $utility)) return 'grid-rows';
        if (preg_match('/^col-span-/', $utility)) return 'col-span';
        if (preg_match('/^row-span-/', $utility)) return 'row-span';
        if (preg_match('/^order-/', $utility)) return 'order';
        if (preg_match('/^(static|fixed|absolute|relative|sticky)$/', $utility)) return 'position';
        if (preg_match('/^overflow-x-/', $utility)) return 'overflow-x';
        if (preg_match('/^overflow-y-/', $utility)) return 'overflow-y';
        if (preg_match('/^overflow-/', $utility)) return 'overflow';

        // Sizing / spacing. Axis-specific families intentionally remain separate.
        foreach ([
            'px-' => 'padding-x', 'py-' => 'padding-y', 'pt-' => 'padding-top', 'pr-' => 'padding-right',
            'pb-' => 'padding-bottom', 'pl-' => 'padding-left', 'p-' => 'padding',
            'mx-' => 'margin-x', 'my-' => 'margin-y', 'mt-' => 'margin-top', 'mr-' => 'margin-right',
            'mb-' => 'margin-bottom', 'ml-' => 'margin-left', 'm-' => 'margin',
            'gap-x-' => 'gap-x', 'gap-y-' => 'gap-y', 'gap-' => 'gap',
            'min-w-' => 'min-width', 'max-w-' => 'max-width', 'w-' => 'width',
            'min-h-' => 'min-height', 'max-h-' => 'max-height', 'h-' => 'height',
            'space-x-' => 'space-x', 'space-y-' => 'space-y',
        ] as $prefix => $family) {
            if (str_starts_with($utility, $prefix)) return $family;
        }

        // Visual families.
        if (preg_match('/^rounded(?:-|$)/', $utility)) return $this->roundedFamily($utility);
        if (preg_match('/^opacity-/', $utility)) return 'opacity';
        if (preg_match('/^shadow(?:-|$)/', $utility)) return 'shadow';
        if (preg_match('/^aspect-/', $utility)) return 'aspect-ratio';
        if (preg_match('/^object-(contain|cover|fill|none|scale-down)$/', $utility)) return 'object-fit';
        if (preg_match('/^object-/', $utility)) return 'object-position';
        if (preg_match('/^z-/', $utility)) return 'z-index';
        if (preg_match('/^cursor-/', $utility)) return 'cursor';
        if (preg_match('/^pointer-events-/', $utility)) return 'pointer-events';
        if (preg_match('/^select-/', $utility)) return 'user-select';
        if (preg_match('/^transition(?:-|$)/', $utility)) return 'transition-property';
        if (preg_match('/^duration-/', $utility)) return 'transition-duration';
        if (preg_match('/^delay-/', $utility)) return 'transition-delay';
        if (preg_match('/^ease-/', $utility)) return 'transition-timing';

        // Color-like utilities are grouped by property prefix. Arbitrary values work too.
        if (preg_match('/^bg-/', $utility)) return 'background';
        if (preg_match('/^text-/', $utility)) return 'text-color';
        if (preg_match('/^border-(?:[trblxy]-)?/', $utility)) return $this->borderFamily($utility);
        if (preg_match('/^ring-offset-/', $utility)) return 'ring-offset';
        if (preg_match('/^ring(?:-|$)/', $utility)) return 'ring';
        if (preg_match('/^fill-/', $utility)) return 'fill';
        if (preg_match('/^stroke-/', $utility)) return 'stroke';

        // Position offsets.
        foreach (['inset-x-' => 'inset-x', 'inset-y-' => 'inset-y', 'inset-' => 'inset', 'top-' => 'top', 'right-' => 'right', 'bottom-' => 'bottom', 'left-' => 'left'] as $prefix => $family) {
            if (str_starts_with($utility, $prefix)) return $family;
        }

        return null;
    }

    private function roundedFamily(string $utility): string
    {
        foreach (['rounded-tl' => 'radius-tl', 'rounded-tr' => 'radius-tr', 'rounded-bl' => 'radius-bl', 'rounded-br' => 'radius-br', 'rounded-t' => 'radius-top', 'rounded-r' => 'radius-right', 'rounded-b' => 'radius-bottom', 'rounded-l' => 'radius-left'] as $prefix => $family) {
            if (str_starts_with($utility, $prefix)) return $family;
        }
        return 'radius';
    }

    private function borderFamily(string $utility): string
    {
        // Width/style/color can share the border prefix. Recognize common width/style tokens first.
        if (preg_match('/^border(?:-[trblxy])?-(0|2|4|8|\[.*\])$/', $utility) || preg_match('/^border(?:-[trblxy])?$/', $utility)) return 'border-width';
        if (preg_match('/^border-(solid|dashed|dotted|double|hidden|none)$/', $utility)) return 'border-style';
        return 'border-color';
    }
}
