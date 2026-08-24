<?php

namespace App\Services;

use App\AI\Generators\ContentGenerator;

/**
 * Backwards-compatible facade retained for existing controller/service wiring.
 * Luna now plans from PageTemplateCatalog metadata and generates through the
 * audited selected-Spark schema pipeline instead of inventing category layouts.
 */
class LunaCategoryPageService
{
    public function __construct(
        private readonly LunaTemplatePlannerService $templatePlanner,
        private readonly ContentGenerator $contentGenerator,
    ) {
    }

    public function plan(string $prompt): array
    {
        return $this->templatePlanner->plan($prompt)['sections'];
    }

    public function planDetailed(string $prompt, ?array $allowedTemplateKeys = null): array
    {
        return $this->templatePlanner->plan($prompt, $allowedTemplateKeys);
    }

    public function generate(string $prompt, array $sections): array
    {
        return $this->contentGenerator->generate($prompt, $sections)['blocks'];
    }
}
