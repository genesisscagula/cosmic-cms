<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HeroSparkHeadingSemanticsTest extends TestCase
{
    #[Test]
    public function editable_hero_headings_use_the_h1_typography_contract(): void
    {
        $files = glob(resource_path('js/Pages/Websites/Blocks/Hero/*.jsx')) ?: [];
        $offenders = [];

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            preg_match_all('/<EditableText\b[\s\S]*?\/>/', $source, $matches);

            foreach (array_filter($matches[0] ?? [], fn (string $tag) => preg_match('/value=\{[^}]*\bheading\b[^}]*\}/', $tag)) as $heading) {
                if (! str_contains($heading, 'cosmicType="h1"')) {
                    $offenders[] = basename($file);
                    break;
                }
            }
        }

        $this->assertSame([], $offenders, 'Hero heading(s) missing cosmicType="h1": '.implode(', ', $offenders));
    }
}
