<?php

namespace Tests\Unit;

use App\Services\IndustryResolver;
use Tests\TestCase;

class IndustryResolverTest extends TestCase
{
    public function test_explicit_technology_industry_survives_a_multi_line_page_prompt(): void
    {
        $prompt = <<<'PROMPT'
Build one page in an existing multi-page website.

Business Name: Cosmic React
Industry: Technology & IT Services
Original user request: Create a premium, modern, responsive website with relevant imagery.
PROMPT;

        $this->assertSame('technology', app(IndustryResolver::class)->resolve($prompt));
    }

    public function test_responsive_does_not_partially_match_the_spa_alias(): void
    {
        $prompt = 'Create a premium responsive website for a Technology & IT Services business.';

        $this->assertSame('technology', app(IndustryResolver::class)->resolve($prompt));
    }

    public function test_standalone_spa_phrase_still_resolves_to_salon(): void
    {
        $this->assertSame('salon', app(IndustryResolver::class)->resolve('Premium spa website'));
    }
}
