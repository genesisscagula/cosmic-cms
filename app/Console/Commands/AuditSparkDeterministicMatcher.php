<?php

namespace App\Console\Commands;

use App\Services\SparkIntentMatcherService;
use Illuminate\Console\Command;

class AuditSparkDeterministicMatcher extends Command
{
    protected $signature = 'cosmic:audit-spark-matcher {--strict : Exit non-zero when a fixture fails}';

    protected $description = 'Audit deterministic Spark matching without calling Luna/OpenAI.';

    public function handle(SparkIntentMatcherService $matcher): int
    {
        $fixtures = [
            ['premium fullscreen hero slider with background images', 'hero', 'slider', 'hero_'],
            ['cinematic hero with background video', 'hero', 'video', 'hero_'],
            ['restaurant atmosphere gallery', 'gallery', 'gallery', 'restaurant_'],
            ['real estate property listing grid', 'portfolio', null, 'realestate_'],
            ['premium testimonials carousel', 'testimonials', 'slider', 'testimonials_'],
            ['pricing comparison cards', 'pricing', null, 'pricing_'],
            ['frequently asked questions accordion', 'faq', null, 'faq_'],
            ['services bento grid', 'services', null, 'services_'],
        ];

        $failed = 0;
        foreach ($fixtures as [$prompt, $semantic, $media, $prefix]) {
            $analysis = $matcher->analyze($prompt);
            $results = $matcher->match($prompt, 5);
            $top = $results[0] ?? null;

            $ok = in_array($semantic, (array) ($analysis['semantic'] ?? []), true)
                && ($media === null || in_array($media, (array) ($analysis['media'] ?? []), true))
                && is_array($top)
                && str_starts_with((string) ($top['id'] ?? ''), $prefix)
                && (int) ($top['score'] ?? 0) > 0;

            $this->line(($ok ? '<info>PASS</info>' : '<error>FAIL</error>')." {$prompt} → ".($top['id'] ?? 'none').' ['.($top['confidence'] ?? 'none').']');
            if (! $ok) {
                $failed++;
            }
        }

        if ($failed === 0) {
            $this->info('Deterministic Spark matcher audit passed. No AI calls were used.');
            return self::SUCCESS;
        }

        $this->error("{$failed} deterministic matcher fixture(s) failed.");
        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
