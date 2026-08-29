<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Batch 5: selected-Spark AI Flex skill policy.
 *
 * Keeps contextual Luna powerful but incremental: classify the user's request,
 * describe the legal skill surface to the model, then reject obvious blast-
 * radius violations before the strict Spark schema validator persists anything.
 */
final class LunaAiFlexEditPolicyService
{
    public function directive(): string
    {
        return <<<'PROMPT'
AI FLEX SELECTED-SPARK SKILL CONTRACT
The current Spark is already selected. Your job is to TWEAK IT, not search for or replace it.
Apply the smallest mutation that satisfies the request and preserve everything else.

Supported visual skills through EXISTING editable paths and EXISTING Tailwind slots only:
- Micro styling: border width/color, border radius, shadow, opacity, card/surface treatment.
- Spacing/geometry: padding, margin, gap, width/max-width/min-height, alignment, ordering, positioning and controlled overlap.
- Typography: size, weight, line-height, tracking and text alignment without rewriting words unless asked.
- Layout: grid/flex columns, asymmetric emphasis, featured items, media/content proportions and safe structural composition within the current renderer.
- Responsive: desktop/tablet/mobile columns, widths, gaps, padding, order, position, min-height and overflow strategy. Preserve existing responsive variants unless the user asks to change them.
- Per-item targeting: singular requests such as "second card", "this testimonial" or a clicked item MUST prefer exact item-specific slots/paths and preserve siblings.
- Background/media presentation: background image/video fields when they already exist, overlays, object-fit/object-position, aspect ratio and media placement. Never invent a URL.
- Effects: hover/state utilities, restrained transitions, glass/surface depth and visual emphasis using only existing safe slots/classes.

Mutation discipline:
- "reduce the radius" => radius-related slot(s) only; do not rewrite text, change colors, grid, media or section type.
- "remove the shadow" => shadow-related slot(s) only.
- "increase spacing between cards" => gap/spacing slot(s) only.
- "make only the second card featured" => exact second-item slots/paths only when available.
- "on mobile stack these cards" => mobile responsive utilities only; preserve desktop unless explicitly requested.
- "make this more premium" may touch several visual slots, but still KEEP the current Spark/layout and content.
- Content-only requests must not emit Tailwind patches.
- Design-only requests must not rewrite headings, body copy, labels, URLs or repeater content.
- A follow-up such as "smaller", "a little more", "reduce more", "remove that" modifies the prior working state incrementally; never restart from the original design.
- For luna_custom_section / AI Flex, structured editable paths are first-class. Use existing visual_style.*, style_overrides.*, and elements.*.style.* paths even when there are no Tailwind inventory slots. Do not reject a valid AI Flex edit merely because tailwind_patch is empty.
- Section spacing requests should prefer existing visual_style.section_padding_y, visual_style.section_padding_x, or visual_style.content_gap. Exact nested-element requests should prefer the matching existing elements.N.style.* path.
- Repeated AI Flex edits must mutate the current popup draft, preserving all unrelated design/content. Do not silently regenerate the section or fall back to the source Spark unless the user explicitly asks for a redesign/rebuild.
- If the exact request is impossible with the supplied paths/slots, return empty patches instead of approximating with unrelated mutations.
PROMPT;
    }

    /**
     * Reject obvious scope violations before schema expansion/validation.
     * This is intentionally conservative: the strict schema validators remain
     * authoritative for class/path legality.
     */
    public function guard(string $request, array $decoded, array $elementContext = []): array
    {
        $editablePatch = is_array($decoded['editable_patch'] ?? null) ? $decoded['editable_patch'] : [];
        $tailwindPatch = is_array($decoded['tailwind_patch'] ?? null) ? $decoded['tailwind_patch'] : [];
        $q = Str::lower(trim($request));

        $contentIntent = Str::contains($q, [
            'rewrite','reword','wording','copy','headline','heading','title','description','paragraph','text','label','cta text',
            'shorten','expand the copy','tone','grammar','spelling','rename','change the words','change wording',
        ]);
        $designIntent = Str::contains($q, [
            'border','radius','rounded','rounding','shadow','spacing','padding','margin','gap','width','height','layout','grid','column',
            'align','position','overlap','responsive','mobile','tablet','desktop','background','gradient','overlay','opacity','glass',
            'hover','transition','premium','modern','polished','cleaner','visual','font size','font weight','line height','tracking',
            'image position','object fit','aspect ratio','video background','media position','featured card','featured item',
            'bigger','smaller','larger','reduce size','increase size','make it wider','make it narrower',
        ]);

        $explicitMixed = $contentIntent && $designIntent;
        if ($contentIntent && ! $designIntent && $tailwindPatch !== []) {
            return ['ok'=>false,'reason'=>'content_only_tailwind_mutation','details'=>['tailwind_slots'=>array_keys($tailwindPatch)]];
        }

        if ($designIntent && ! $contentIntent && ! $explicitMixed) {
            $contentPaths = array_values(array_filter(array_keys($editablePatch), fn($path) => $this->looksLikeContentPath((string)$path)));
            if ($contentPaths !== []) {
                return ['ok'=>false,'reason'=>'design_only_content_mutation','details'=>['editable_paths'=>$contentPaths]];
            }
        }

        // Micro edits should stay micro. Plural/broad requests are allowed a
        // larger patch surface; exact clicked-item requests get the tightest cap.
        if ($this->isMicroVisualRequest($q)) {
            $itemTargeted = isset($elementContext['itemIndex']) || isset($elementContext['item_index']) || isset($elementContext['target_item_index']);
            $plural = Str::contains($q, ['all ','every ','buttons','cards','items','services','testimonials','faqs','features','plans']);
            $maxTailwind = $plural ? 12 : ($itemTargeted ? 6 : 8);
            $maxEditable = $plural ? 8 : 4;
            if (count($tailwindPatch) > $maxTailwind || count($editablePatch) > $maxEditable) {
                return [
                    'ok'=>false,
                    'reason'=>'micro_edit_blast_radius_exceeded',
                    'details'=>[
                        'tailwind_count'=>count($tailwindPatch),'tailwind_max'=>$maxTailwind,
                        'editable_count'=>count($editablePatch),'editable_max'=>$maxEditable,
                    ],
                ];
            }
        }

        return ['ok'=>true,'reason'=>null];
    }

    private function looksLikeContentPath(string $path): bool
    {
        $leaf = Str::lower(Str::afterLast($path, '.'));
        // Media/background URLs are visual assets, not copy. They may be
        // changed by an explicit background/media design request.
        if (Str::contains($leaf, ['image_url','video_url','media_url','background_image','background_video','poster_url'])) {
            return false;
        }
        if (in_array($leaf, ['heading','heading_accent_text','eyebrow','title','text','description','label','primary_label','secondary_label','url','primary_url','secondary_url','alt'], true)) {
            return true;
        }
        return (bool) preg_match('/(?:^|_)(heading|title|text|copy|description|label|url|eyebrow|caption)(?:$|_)/i', $leaf);
    }

    private function isMicroVisualRequest(string $q): bool
    {
        if (Str::contains($q, ['redesign','rebuild','completely','entire section','whole section','make this more premium','make this premium','more visually interesting'])) {
            return false;
        }
        return Str::contains($q, [
            'border','radius','rounded','rounding','shadow','padding','margin','gap','spacing','opacity','font size','font weight',
            'line height','tracking','align','width','height','object fit','object position','aspect ratio','mobile','tablet','desktop',
        ]);
    }
}
