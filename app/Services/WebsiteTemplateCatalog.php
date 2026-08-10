<?php

namespace App\Services;

/**
 * Product-owned template catalog. Patch 6.1.3 adds curated Spark recipes to the starter
 * catalog across Cosmic's primary industries while keeping the templates
 * source-controlled and compatible with the existing Builder block schema.
 */
class WebsiteTemplateCatalog
{
    private const TEMPLATES = [
        'aurora-agency' => ['theme' => 'violet', 'industry' => 'creative agency', 'tagline' => 'Independent creative agency', 'heading' => 'Brands with a clearer point of view.', 'text' => 'Turn thoughtful strategy, identity, and digital experiences into work people remember.', 'services' => ['Brand strategy', 'Identity systems', 'Digital experiences']],
        'summit-consulting' => ['theme' => 'navy', 'industry' => 'business consulting', 'tagline' => 'Practical business consulting', 'heading' => 'Clear decisions for the next stage of growth.', 'text' => 'Align your team, priorities, and execution around a practical path forward.', 'services' => ['Business strategy', 'Operational planning', 'Growth advisory']],
        'nova-startup' => ['theme' => 'indigo', 'industry' => 'startup', 'tagline' => 'Built for ambitious startups', 'heading' => 'Turn a strong idea into a product people understand.', 'text' => 'Communicate your value clearly, build early trust, and give customers an easy next step.', 'services' => ['Product positioning', 'Launch strategy', 'Growth systems']],
        'midnight-studio' => ['theme' => 'midnight', 'industry' => 'professional studio', 'tagline' => 'Strategic creative studio', 'heading' => 'Make the next version of your business unmistakable.', 'text' => 'Clear strategy, purposeful design, and strong digital execution for teams ready to move.', 'services' => ['Strategy', 'Creative direction', 'Digital delivery']],
        'table-tide' => ['theme' => 'terracotta', 'industry' => 'restaurant', 'tagline' => 'Seasonal food, genuine hospitality', 'heading' => 'A table worth gathering around.', 'text' => 'Share your menu, story, and dining experience with guests before they arrive.', 'services' => ['Seasonal menu', 'Private dining', 'Reservations']],
        'ember-kitchen' => ['theme' => 'espresso', 'industry' => 'restaurant', 'tagline' => 'Modern dining over an open flame', 'heading' => 'Bold flavour. Warm hospitality.', 'text' => 'A rich restaurant starter for signature dishes, private bookings, and memorable evenings.', 'services' => ['Dinner menu', 'Chef experiences', 'Private events']],
        'olive-hearth' => ['theme' => 'olive', 'industry' => 'restaurant', 'tagline' => 'Mediterranean food made locally', 'heading' => 'Simple ingredients, generously shared.', 'text' => 'Bring your restaurant story, seasonal plates, and neighbourhood hospitality together.', 'services' => ['Lunch and dinner', 'Group dining', 'Local produce']],
        'morning-brew' => ['theme' => 'coffee', 'industry' => 'coffee shop', 'tagline' => 'Your neighbourhood coffee stop', 'heading' => 'Better mornings start here.', 'text' => 'Showcase signature drinks, fresh food, and the atmosphere that keeps regulars returning.', 'services' => ['Specialty coffee', 'Fresh pastries', 'Local pickup']],
        'roast-lab' => ['theme' => 'espresso', 'industry' => 'coffee roaster', 'tagline' => 'Specialty coffee roasted with purpose', 'heading' => 'Find the roast that fits your ritual.', 'text' => 'Introduce your beans, subscriptions, and wholesale program with clarity.', 'services' => ['Single origins', 'Subscriptions', 'Wholesale coffee']],
        'buildcore' => ['theme' => 'asphalt', 'industry' => 'construction', 'tagline' => 'Commercial construction specialists', 'heading' => 'Built properly from the ground up.', 'text' => 'Present your capabilities, completed work, and dependable delivery process.', 'services' => ['Commercial builds', 'Project management', 'Fit-outs']],
        'skyline-builders' => ['theme' => 'stone', 'industry' => 'residential construction', 'tagline' => 'Homes designed around real life', 'heading' => 'Build a home that feels entirely yours.', 'text' => 'Show your craftsmanship, building process, and completed residential projects.', 'services' => ['Custom homes', 'Renovations', 'Design and build']],
        'forge-works' => ['theme' => 'charcoal', 'industry' => 'industrial services', 'tagline' => 'Industrial design and build', 'heading' => 'Built for the work that keeps moving.', 'text' => 'Communicate capability, safety, and dependable delivery for complex work.', 'services' => ['Fabrication', 'Installation', 'Maintenance']],
        'carepoint' => ['theme' => 'ocean', 'industry' => 'medical clinic', 'tagline' => 'Practical care, close to home', 'heading' => 'Healthcare that starts by listening.', 'text' => 'Help patients understand your services, practitioners, and appointment options.', 'services' => ['General care', 'Preventive health', 'Appointments']],
        'mednova' => ['theme' => 'teal', 'industry' => 'specialist medical practice', 'tagline' => 'Specialist care with a clearer path', 'heading' => 'Expertise patients can feel confident in.', 'text' => 'Explain specialist services, referral pathways, and what patients can expect.', 'services' => ['Specialist consults', 'Diagnostics', 'Ongoing care']],
        'smile-studio' => ['theme' => 'sapphire', 'industry' => 'dental clinic', 'tagline' => 'Modern dentistry, thoughtfully delivered', 'heading' => 'Feel better about your next dental visit.', 'text' => 'Build trust around treatments, your team, and simple consultation bookings.', 'services' => ['General dentistry', 'Cosmetic treatments', 'Emergency care']],
        'iron-gym' => ['theme' => 'ruby', 'industry' => 'gym', 'tagline' => 'Train with purpose', 'heading' => 'Stronger starts with showing up.', 'text' => 'Promote memberships, coaching, facilities, and a community built around progress.', 'services' => ['Gym memberships', 'Personal coaching', 'Group training']],
        'motion-studio' => ['theme' => 'plum', 'industry' => 'boutique fitness studio', 'tagline' => 'Movement that meets you where you are', 'heading' => 'Find a class you will want to return to.', 'text' => 'Introduce classes, instructors, and an easy first-session offer.', 'services' => ['Studio classes', 'Private sessions', 'Intro offers']],
        'haven-estates' => ['theme' => 'emerald', 'industry' => 'real estate', 'tagline' => 'Local property expertise', 'heading' => 'Move forward with the right advice.', 'text' => 'Showcase quality listings, local knowledge, and appraisal opportunities.', 'services' => ['Property sales', 'Appraisals', 'Buyer guidance']],
        'prime-homes' => ['theme' => 'forest', 'industry' => 'property sales', 'tagline' => 'Exceptional homes, carefully represented', 'heading' => 'The right home deserves the right introduction.', 'text' => 'Present premium property, experienced agents, and local market knowledge.', 'services' => ['Premium listings', 'Market appraisals', 'Private inspections']],
        'learnhub' => ['theme' => 'indigo', 'industry' => 'online education', 'tagline' => 'Practical learning for real progress', 'heading' => 'Learn useful skills with a clear next step.', 'text' => 'Explain courses, learning outcomes, and how students can begin.', 'services' => ['Online courses', 'Mentor support', 'Certificates']],
        'bright-academy' => ['theme' => 'amber', 'industry' => 'academy', 'tagline' => 'A confident start for every learner', 'heading' => 'A place to learn, belong, and grow.', 'text' => 'Share programs, community values, and a welcoming admissions pathway.', 'services' => ['Academic programs', 'Student support', 'Admissions']],
        'cloudtech' => ['theme' => 'sapphire', 'industry' => 'technology services', 'tagline' => 'Technology that supports the way you work', 'heading' => 'Make complex systems feel manageable.', 'text' => 'Explain your solutions, technical capability, and customer support clearly.', 'services' => ['Cloud solutions', 'Managed services', 'Technical support']],
        'orbit-launch' => ['theme' => 'violet', 'industry' => 'saas', 'tagline' => 'A smarter way to move work forward', 'heading' => 'One product. A much simpler workflow.', 'text' => 'Position your software, highlight benefits, and convert interest into signups.', 'services' => ['Core platform', 'Integrations', 'Customer success']],
        'horizon-travel' => ['theme' => 'teal', 'industry' => 'travel agency', 'tagline' => 'Journeys planned around you', 'heading' => 'Go further without the planning stress.', 'text' => 'Showcase destinations, travel packages, and a clear inquiry process.', 'services' => ['Tailored itineraries', 'Group travel', 'Travel support']],
        'atlas-escape' => ['theme' => 'navy', 'industry' => 'luxury travel', 'tagline' => 'Private journeys, carefully considered', 'heading' => 'Travel designed around the experience you want.', 'text' => 'Present tailored escapes, premium stays, and high-touch trip planning.', 'services' => ['Private itineraries', 'Luxury stays', 'Concierge support']],
        'legacy-law' => ['theme' => 'navy', 'industry' => 'law firm', 'tagline' => 'Clear legal advice when it matters', 'heading' => 'Experience you can rely on.', 'text' => 'Communicate practice areas, professional credibility, and consultation options.', 'services' => ['Business law', 'Property matters', 'Dispute resolution']],
        'justice-partners' => ['theme' => 'charcoal', 'industry' => 'legal practice', 'tagline' => 'Practical counsel, clearly explained', 'heading' => 'A steady path through complex legal matters.', 'text' => 'Help prospective clients understand your experience and how to begin.', 'services' => ['Legal advice', 'Representation', 'Consultations']],
        'obsidian-atelier' => ['theme' => 'obsidian', 'industry' => 'luxury portfolio', 'tagline' => 'Independent luxury atelier', 'heading' => 'A quieter, more considered way to stand apart.', 'text' => 'Shape a refined portfolio for work defined by restraint, detail, and distinction.', 'services' => ['Creative direction', 'Identity design', 'Selected commissions']],
        'form-function' => ['theme' => 'slate', 'industry' => 'design portfolio', 'tagline' => 'Independent designer and maker', 'heading' => 'Useful ideas, carefully made.', 'text' => 'Present selected work, capabilities, and a clear path to commission new projects.', 'services' => ['Product design', 'Visual systems', 'Creative collaboration']],
    ];

    /**
     * Canonical template access levels used by plan entitlements.
     *
     * @return array<string, int>
     */
    public function accessLevels(): array
    {
        return (array) config('cosmic-templates.access_levels', []);
    }


    /** @return array<string, array<string, mixed>> */
    public function agencyCollections(): array
    {
        $allTemplates = array_keys(self::TEMPLATES);

        return collect((array) config('cosmic-templates.agency_collections', []))
            ->map(function (array $collection, string $key) use ($allTemplates) {
                $templates = (array) ($collection['templates'] ?? []);
                if (in_array('*', $templates, true)) {
                    $templates = $allTemplates;
                }

                return [
                    'key' => $key,
                    'label' => (string) ($collection['label'] ?? ucfirst(str_replace('_', ' ', $key))),
                    'description' => (string) ($collection['description'] ?? ''),
                    'minimum_plan' => (string) ($collection['minimum_plan'] ?? $key),
                    'templates' => array_values(array_filter($templates, fn ($template) => $this->supports($template))),
                    'template_count' => count(array_filter($templates, fn ($template) => $this->supports($template))),
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function agencyCollectionsForUser(\App\Models\User $user, PlanEntitlementService $entitlements): array
    {
        $accountLevel = (string) (($entitlements->summary($user)['capabilities']['template_access_level'] ?? 'starter'));
        $levels = $this->accessLevels();
        $accountRank = $levels[$accountLevel] ?? -1;

        return collect($this->agencyCollections())
            ->map(function (array $collection) use ($levels, $accountRank) {
                $required = (string) $collection['minimum_plan'];
                $locked = $accountRank < ($levels[$required] ?? PHP_INT_MAX);

                return [
                    ...$collection,
                    'locked' => false,
                    'lock_message' => null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Return normalized metadata for one template.
     *
     * @return array<string, mixed>|null
     */
    public function definition(string $template): ?array
    {
        if (! $this->supports($template)) {
            return null;
        }

        $profile = self::TEMPLATES[$template];
        $metadata = (array) config("cosmic-templates.templates.{$template}", []);

        return [
            'slug' => $template,
            'minimum_plan' => (string) ($metadata['minimum_plan'] ?? 'pro'),
            'industry' => (string) ($metadata['industry'] ?? $profile['industry'] ?? 'general'),
            'theme_family' => (string) ($metadata['theme_family'] ?? $profile['theme'] ?? 'midnight'),
            'page_count' => max(1, (int) ($metadata['page_count'] ?? 1)),
            'spark_collection' => (string) ($metadata['spark_collection'] ?? "{$template}-starter"),
            'preview_sparks' => $this->previewSparkTypes($template),
            'spark_count' => count($this->previewSparkTypes($template)),
            'is_featured' => (bool) ($metadata['is_featured'] ?? false),
            'is_premium' => (bool) ($metadata['is_premium'] ?? false),
            'collection' => (string) ($metadata['collection'] ?? 'personal'),
            'agency_collections' => collect($this->agencyCollections())
                ->filter(fn (array $collection) => in_array($template, $collection['templates'], true))
                ->keys()
                ->values()
                ->all(),
        ];
    }

    /**
     * Frontend-safe catalog metadata. Template content remains server-owned.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forClient(): array
    {
        return collect(array_keys(self::TEMPLATES))
            ->map(fn (string $template) => $this->definition($template))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Validate the source-controlled registry without crashing production requests.
     *
     * @return array<int, string>
     */
    public function validationErrors(): array
    {
        $levels = $this->accessLevels();
        $errors = [];

        foreach (array_keys(self::TEMPLATES) as $template) {
            $definition = $this->definition($template);

            if (! $definition) {
                $errors[] = "Template [{$template}] has no definition.";
                continue;
            }

            if (! array_key_exists($definition['minimum_plan'], $levels)) {
                $errors[] = "Template [{$template}] uses unknown access level [{$definition['minimum_plan']}].";
            }

            foreach (['industry', 'theme_family', 'spark_collection'] as $required) {
                if (trim((string) $definition[$required]) === '') {
                    $errors[] = "Template [{$template}] is missing [{$required}].";
                }
            }
        }

        foreach ($this->agencyCollections() as $key => $collection) {
            if (! array_key_exists($collection['minimum_plan'], $levels)) {
                $errors[] = "Agency template collection [{$key}] uses unknown access level [{$collection['minimum_plan']}].";
            }

            if ($collection['template_count'] < 1) {
                $errors[] = "Agency template collection [{$key}] has no valid templates.";
            }
        }

        return $errors;
    }

    public function supports(?string $template): bool
    {
        return is_string($template) && array_key_exists($template, self::TEMPLATES);
    }


    public function catalogIndex(string $template): ?int
    {
        $index = array_search($template, array_keys(self::TEMPLATES), true);

        return $index === false ? null : $index;
    }

    /** @return array<int, array<string, mixed>> */
    public function forUser(\App\Models\User $user, PlanEntitlementService $entitlements): array
    {
        return collect(array_keys(self::TEMPLATES))
            ->map(function (string $template) use ($user, $entitlements) {
                $definition = $this->definition($template);
                $decision = $entitlements->templateAccess($user, $template, $this);

                return $definition ? [
                    ...$definition,
                    'locked' => ! $decision['allowed'],
                    'lock_reason' => $decision['reason'],
                    'lock_message' => $decision['message'],
                    'upgrade_prompt' => $decision['upgrade'] ?? null,
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    public function entitlement(string $template): ?array
    {
        $keys = array_keys(self::TEMPLATES);
        $index = array_search($template, $keys, true);

        if ($index === false) {
            return null;
        }

        return [
            'catalog_index' => $index,
            'required_level' => $index < 5 ? 'starter' : ($index < 10 ? 'growth' : 'pro'),
        ];
    }

    public function websiteAttributes(string $template, string $websiteName): array
    {
        $profile = self::TEMPLATES[$template] ?? null;

        if (! $profile) {
            return [];
        }

        return [
            'theme_settings' => [
                'primary' => $profile['theme'],
                'secondary' => 'white',
                'tertiary' => 'stone',
                'auto' => true,
            ],
            'global_header' => [
                'type' => 'glassmorphism_header',
                'logo_text' => $websiteName,
                'cta_label' => $this->ctaLabel($profile['industry']),
                'cta_url' => '#contact',
                'menu' => [
                    ['label' => 'Home', 'url' => '#'],
                    ['label' => 'About', 'url' => '#about'],
                    ['label' => 'Services', 'url' => '#services'],
                    ['label' => 'Contact', 'url' => '#contact'],
                ],
            ],
            'global_footer' => [
                'type' => 'minimal_footer',
                'logo_text' => $websiteName,
                'copyright' => '© '.now()->year.'. All rights reserved.',
            ],
        ];
    }

    public function pages(string $template): array
    {
        if (! $this->supports($template)) {
            return [];
        }

        return [[
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'draft',
            'blocks' => $this->starterHomeBlocks(self::TEMPLATES[$template], $template),
        ]];
    }

    /**
     * Curated Spark composition per template family. Templates no longer use
     * one generic section order; each industry gets a purposeful customer flow.
     */
    private function starterHomeBlocks(array $profile, string $template): array
    {
        $services = array_map(
            fn (string $title, int $index) => [
                'icon' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'title' => $title,
                'desc' => 'Replace this starter description with the details, process, and value specific to your business.',
            ],
            $profile['services'],
            array_keys($profile['services'])
        );

        $family = $this->templateFamily($profile['industry']);
        $variant = $this->templateVariant($template);
        $blocks = [$this->heroSpark($profile, $variant)];

        foreach ($this->starterKitRecipe($template, $family, $variant) as $spark) {
            $blocks[] = match ($spark) {
                'story' => $this->storySpark($profile, $family, $variant),
                'services' => $this->servicesSpark($profile, $services, $family, $variant),
                'process' => $this->processSpark($family),
                'proof' => $this->proofSpark($family, $variant),
                'cta' => $this->ctaSpark($profile, $family, $variant),
            };
        }

        return $blocks;
    }

    private function templateVariant(string $template): int
    {
        $index = array_search($template, array_keys(self::TEMPLATES), true);

        return $index === false ? 0 : $index % 8;
    }

    private function previewSparkTypes(string $template): array
    {
        if (! $this->supports($template)) {
            return [];
        }

        return collect($this->starterHomeBlocks(self::TEMPLATES[$template], $template))
            ->pluck('type')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Small template-specific composition overrides keep Starter Kits visually
     * distinct while preserving the shared industry content generators.
     */
    private function starterKitRecipe(string $template, string $family, int $variant): array
    {
        $overrides = [
            'aurora-agency' => ['services', 'story', 'proof', 'process', 'cta'],
            'summit-consulting' => ['story', 'process', 'services', 'proof', 'cta'],
            'nova-startup' => ['services', 'proof', 'story', 'process', 'cta'],
            'midnight-studio' => ['story', 'services', 'process', 'proof', 'cta'],

            'table-tide' => ['story', 'services', 'proof', 'cta'],
            'ember-kitchen' => ['services', 'story', 'proof', 'cta'],
            'olive-hearth' => ['story', 'proof', 'services', 'cta'],
            'morning-brew' => ['services', 'story', 'proof', 'cta'],
            'roast-lab' => ['story', 'services', 'proof', 'cta'],

            'buildcore' => ['services', 'process', 'story', 'proof', 'cta'],
            'skyline-builders' => ['story', 'process', 'services', 'proof', 'cta'],
            'forge-works' => ['services', 'story', 'process', 'proof', 'cta'],

            'carepoint' => ['services', 'story', 'process', 'proof', 'cta'],
            'mednova' => ['story', 'services', 'proof', 'process', 'cta'],
            'smile-studio' => ['story', 'process', 'services', 'proof', 'cta'],

            'iron-gym' => ['services', 'process', 'proof', 'cta'],
            'motion-studio' => ['story', 'services', 'proof', 'cta'],

            'haven-estates' => ['story', 'services', 'proof', 'process', 'cta'],
            'prime-homes' => ['services', 'story', 'process', 'proof', 'cta'],

            'learnhub' => ['story', 'services', 'process', 'proof', 'cta'],
            'bright-academy' => ['services', 'story', 'proof', 'process', 'cta'],

            'cloudtech' => ['services', 'proof', 'story', 'process', 'cta'],
            'orbit-launch' => ['story', 'services', 'process', 'proof', 'cta'],

            'horizon-travel' => ['story', 'services', 'proof', 'cta'],
            'atlas-escape' => ['services', 'story', 'proof', 'cta'],

            'legacy-law' => ['story', 'services', 'process', 'proof', 'cta'],
            'justice-partners' => ['services', 'story', 'proof', 'process', 'cta'],

            'obsidian-atelier' => ['story', 'proof', 'services', 'cta'],
            'form-function' => ['services', 'story', 'proof', 'cta'],
        ];

        return $overrides[$template] ?? $this->sparkRecipe($family, $variant);
    }


    private function sparkRecipe(string $family, int $variant): array
    {
        $recipes = match ($family) {
            'restaurant', 'coffee' => [
                ['story', 'services', 'proof', 'cta'],
                ['services', 'story', 'proof', 'cta'],
                ['story', 'proof', 'services', 'cta'],
            ],
            'medical' => [
                ['services', 'story', 'process', 'proof', 'cta'],
                ['story', 'services', 'proof', 'process', 'cta'],
            ],
            'real-estate' => [
                ['story', 'services', 'proof', 'process', 'cta'],
                ['services', 'story', 'process', 'proof', 'cta'],
            ],
            'fitness' => [
                ['services', 'process', 'proof', 'cta'],
                ['story', 'services', 'proof', 'cta'],
            ],
            'education' => [
                ['story', 'services', 'process', 'proof', 'cta'],
                ['services', 'story', 'proof', 'process', 'cta'],
            ],
            'technology', 'saas' => [
                ['services', 'story', 'proof', 'process', 'cta'],
                ['story', 'services', 'process', 'proof', 'cta'],
                ['services', 'proof', 'story', 'cta'],
            ],
            'travel' => [
                ['story', 'services', 'proof', 'cta'],
                ['services', 'story', 'proof', 'cta'],
            ],
            'legal' => [
                ['story', 'services', 'process', 'proof', 'cta'],
                ['services', 'story', 'proof', 'process', 'cta'],
            ],
            'portfolio' => [
                ['story', 'services', 'proof', 'cta'],
                ['services', 'story', 'proof', 'cta'],
            ],
            default => [
                ['story', 'services', 'process', 'proof', 'cta'],
                ['services', 'story', 'proof', 'process', 'cta'],
                ['story', 'proof', 'services', 'process', 'cta'],
            ],
        };

        return $recipes[$variant % count($recipes)];
    }

    private function templateFamily(string $industry): string
    {
        return match (true) {
            str_contains($industry, 'restaurant') => 'restaurant',
            str_contains($industry, 'coffee') => 'coffee',
            str_contains($industry, 'medical'), str_contains($industry, 'dental') => 'medical',
            str_contains($industry, 'real estate'), str_contains($industry, 'property') => 'real-estate',
            str_contains($industry, 'gym'), str_contains($industry, 'fitness') => 'fitness',
            str_contains($industry, 'education'), str_contains($industry, 'academy') => 'education',
            str_contains($industry, 'technology') => 'technology',
            str_contains($industry, 'saas'), str_contains($industry, 'startup') => 'saas',
            str_contains($industry, 'travel') => 'travel',
            str_contains($industry, 'law'), str_contains($industry, 'legal') => 'legal',
            str_contains($industry, 'portfolio'), str_contains($industry, 'designer'), str_contains($industry, 'luxury') => 'portfolio',
            str_contains($industry, 'construction'), str_contains($industry, 'industrial') => 'construction',
            default => 'business',
        };
    }

    private function heroSpark(array $profile, int $variant): array
    {
        $base = [
            'theme' => 'auto',
            'tagline' => $profile['tagline'],
            'heading' => $profile['heading'],
            'text' => $profile['text'],
            'primary_label' => $this->ctaLabel($profile['industry']),
            'primary_url' => '#contact',
            'secondary_label' => 'Learn more',
            'secondary_url' => '#about',
            'image_url' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=2000&q=85',
        ];

        return match ($variant) {
            1 => [...$base, 'type' => 'hero_split_image', 'trust_line' => 'Trusted by growing teams', 'image_badge' => 'Established'],
            2 => [...$base, 'type' => 'hero_parallax', 'category' => $profile['tagline'], 'overlayOpacity' => 46, 'height' => 'large'],
            3 => [...$base, 'type' => 'hero_editorial_overlay', 'category' => $profile['tagline'], 'overlayOpacity' => 44, 'height' => 'large'],
            4 => [...$base, 'type' => 'hero_floating_cards', 'image_badge' => 'Featured', 'card_one_value' => '01', 'card_one_label' => 'Discover', 'card_two_value' => '02', 'card_two_label' => 'Plan', 'card_three_value' => '03', 'card_three_label' => 'Grow'],
            5 => [...$base, 'type' => 'hero_split_editorial', 'eyebrow' => $profile['tagline'], 'editorial_index' => '01', 'proof_value' => 'Built to convert', 'proof_label' => 'Cosmic starter', 'image_caption' => $profile['industry']],
            6 => [...$base, 'type' => 'hero_luxury_fullscreen', 'eyebrow' => $profile['tagline'], 'location_label' => ucfirst($profile['industry']), 'edition_label' => 'Cosmic Edition'],
            7 => [...$base, 'type' => 'hero_bento_premium', 'eyebrow' => $profile['tagline'], 'image_label' => 'Featured', 'metric_value' => 'Built fast', 'metric_label' => 'Starter kit', 'proof_title' => 'Ready to customize', 'proof_text' => 'Built from reusable Cosmic Sparks.', 'card_one_label' => 'Strategy', 'card_two_label' => 'Design', 'card_three_label' => 'Launch'],
            default => [
                'type' => 'hero_background_image', 'theme' => 'auto',
                'tagline' => $profile['tagline'], 'heading' => $profile['heading'], 'text' => $profile['text'],
                'button_label' => $this->ctaLabel($profile['industry']), 'button_url' => '#contact',
                'image_url' => $base['image_url'], 'overlayOpacity' => 48, 'textAlign' => 'center', 'height' => 'large',
            ],
        };
    }

    private function storySpark(array $profile, string $family, int $variant): array
    {
        $copy = match ($family) {
            'restaurant' => ['Our table', 'Food with a story behind every plate.', 'Introduce your ingredients, chef, atmosphere, and the kind of experience guests can expect.'],
            'coffee' => ['Our coffee', 'Roasted and served with intention.', 'Share your sourcing, roasting approach, and the daily ritual your customers return for.'],
            'medical' => ['Patient care', 'Care that feels clear from the beginning.', 'Introduce your practitioners, care philosophy, and what patients can expect at their first visit.'],
            'real-estate' => ['Local expertise', 'Property advice grounded in the market.', 'Explain your local knowledge, sales approach, and how you guide clients through major decisions.'],
            'fitness' => ['Your training space', 'Progress built around real people.', 'Introduce your coaches, training philosophy, and the supportive environment behind every session.'],
            'education' => ['Our approach', 'Learning designed for meaningful progress.', 'Explain how your programs, teachers, and student support create better learning outcomes.'],
            'technology', 'saas' => ['Why it works', 'Technology that removes unnecessary complexity.', 'Explain the problem you solve, how your product works, and why teams choose it.'],
            'travel' => ['Travel differently', 'Journeys shaped around the people taking them.', 'Show how your planning, local knowledge, and support make each trip more rewarding.'],
            'legal' => ['Our practice', 'Clear advice backed by experience.', 'Introduce your team, values, and the practical way you help clients move forward.'],
            'portfolio' => ['Selected practice', 'Thoughtful work, made with purpose.', 'Use this space to explain your creative point of view, process, and selected body of work.'],
            default => [ucfirst($profile['industry']), 'A strong foundation for the way your business works.', 'Explain your approach, experience, and the difference customers can expect.'],
        };

        return [
            'type' => $variant % 2 === 0 ? 'feature_image_left' : 'feature_image_right', 'theme' => 'auto', 'category' => $copy[0],
            'heading' => $copy[1], 'text' => $copy[2],
            'button_label' => 'Learn more', 'button_url' => '#about',
            'image_url' => 'https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=1600&q=85',
        ];
    }

    private function servicesSpark(array $profile, array $services, string $family, int $variant): array
    {
        [$tagline, $heading, $description] = match ($family) {
            'restaurant' => ['Menu highlights', 'The dishes guests come back for.', 'Present signature dishes, seasonal menus, and dining experiences.'],
            'coffee' => ['What we serve', 'Coffee for every kind of morning.', 'Feature your beans, signature drinks, food, subscriptions, or wholesale offering.'],
            'medical' => ['Care and services', 'The right care, clearly explained.', 'Help patients quickly understand services, treatment options, and how to book.'],
            'real-estate' => ['Property services', 'Guidance for every move.', 'Present sales, appraisals, buying support, and local market expertise.'],
            'fitness' => ['Ways to train', 'Choose the support that fits your goals.', 'Show memberships, classes, coaching, and beginner offers.'],
            'education' => ['Programs', 'Learning paths built for progress.', 'Present courses, programs, student support, and enrolment options.'],
            'technology', 'saas' => ['Core capabilities', 'Everything teams need to move faster.', 'Show the product capabilities, integrations, and support that create value.'],
            'travel' => ['Featured journeys', 'Travel experiences worth planning for.', 'Highlight destinations, packages, private itineraries, and planning services.'],
            'legal' => ['Practice areas', 'Practical support for important matters.', 'Help clients identify the legal service that matches their situation.'],
            'portfolio' => ['Capabilities', 'Selected services and collaborations.', 'Present your core disciplines, commissions, and ways to work together.'],
            default => ['What we offer', 'Services shaped around what customers need.', 'Replace the starter content with your real services, scope, and customer outcomes.'],
        };

        return ['type' => $variant % 3 === 0 ? 'services_cards' : 'services_bento', 'theme' => 'auto', 'tagline' => $tagline, 'heading' => $heading, 'description' => $description, 'services' => $services];
    }

    private function processSpark(string $family): array
    {
        $steps = match ($family) {
            'medical' => [['01','Book'],['02','Prepare'],['03','Consult'],['04','Follow up']],
            'real-estate' => [['01','Connect'],['02','Prepare'],['03','Market'],['04','Move']],
            'fitness' => [['01','Start'],['02','Assess'],['03','Train'],['04','Progress']],
            'education' => [['01','Explore'],['02','Enrol'],['03','Learn'],['04','Achieve']],
            'technology', 'saas' => [['01','Discover'],['02','Configure'],['03','Launch'],['04','Scale']],
            'legal' => [['01','Enquire'],['02','Review'],['03','Advise'],['04','Resolve']],
            default => [['01','Connect'],['02','Plan'],['03','Deliver'],['04','Support']],
        };

        return [
            'type' => 'process_timeline', 'theme' => 'auto', 'category' => 'How it works',
            'heading' => 'A clear path from first step to outcome.', 'text' => 'Set expectations and help customers understand what happens next.',
            'steps' => array_map(fn ($step) => ['number' => $step[0], 'title' => $step[1], 'text' => 'Replace this with a concise explanation of this stage.'], $steps),
        ];
    }

    private function proofSpark(string $family, int $variant): array
    {
        $heading = match ($family) {
            'restaurant', 'coffee' => 'Why guests keep coming back.',
            'medical' => 'Patient experiences that build confidence.',
            'real-estate' => 'Trusted for important property decisions.',
            'fitness' => 'Real progress from real members.',
            'education' => 'Outcomes students can be proud of.',
            'technology', 'saas' => 'Trusted by teams doing their best work.',
            'travel' => 'Journeys clients still talk about.',
            'legal' => 'Professional support clients can rely on.',
            'portfolio' => 'What collaborators say about the work.',
            default => 'Build trust with approved customer feedback.',
        };

        if ($variant % 3 === 1) {
            return [
                'type' => 'stats_modern', 'theme' => 'auto', 'tagline' => 'Proof at a glance', 'heading' => $heading,
                'stats' => [
                    ['value' => '10+', 'label' => 'Years experience'],
                    ['value' => '250+', 'label' => 'Customers served'],
                    ['value' => '4.9/5', 'label' => 'Average rating'],
                    ['value' => '24h', 'label' => 'Typical response'],
                ],
            ];
        }

        return [
            'type' => 'testimonials_carousel', 'theme' => 'auto', 'tagline' => 'Customer stories', 'heading' => $heading,
            'text' => 'Replace these placeholders with genuine, approved testimonials before publishing.',
            'testimonials' => [
                ['avatar' => '/storage/cms-images/avatars/avatar-1.jpg', 'name' => 'Customer name', 'company' => 'Customer company', 'quote' => 'Add a specific customer quote that reflects the experience and outcome.', 'rating' => 5],
                ['avatar' => '/storage/cms-images/avatars/avatar-2.jpg', 'name' => 'Customer name', 'company' => 'Customer company', 'quote' => 'Use real social proof that is concise, factual, and relevant.', 'rating' => 5],
                ['avatar' => '/storage/cms-images/avatars/avatar-3.jpg', 'name' => 'Customer name', 'company' => 'Customer company', 'quote' => 'Show prospective customers why people choose and recommend your business.', 'rating' => 5],
            ],
        ];
    }

    private function ctaSpark(array $profile, string $family, int $variant): array
    {
        $heading = match ($family) {
            'restaurant' => 'Ready to reserve your table?', 'coffee' => 'Make us part of your next morning.',
            'medical' => 'Ready to arrange your appointment?', 'real-estate' => 'Thinking about your next move?',
            'fitness' => 'Ready to begin training?', 'education' => 'Ready to take the next step in learning?',
            'technology', 'saas' => 'Ready to see how it works?', 'travel' => 'Ready to plan a better journey?',
            'legal' => 'Ready to discuss your matter?', 'portfolio' => 'Have a project worth making together?',
            default => 'Ready to start a conversation?',
        };

        return ['type' => $variant % 2 === 0 ? 'hero_centered_cta' : 'image_cta_banner', 'theme' => 'auto', 'tagline' => 'Take the next step', 'heading' => $heading, 'text' => 'Replace this copy with the most useful next step for your customers.', 'button_label' => $this->ctaLabel($profile['industry']), 'button_url' => '#contact', 'image_url' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1600&q=85'];
    }

    private function ctaLabel(string $industry): string
    {
        return match (true) {
            str_contains($industry, 'restaurant') => 'Book a table',
            str_contains($industry, 'medical'), str_contains($industry, 'dental') => 'Book an appointment',
            str_contains($industry, 'real estate'), str_contains($industry, 'property') => 'Request an appraisal',
            str_contains($industry, 'travel') => 'Plan a trip',
            str_contains($industry, 'education'), str_contains($industry, 'academy') => 'Enquire now',
            str_contains($industry, 'gym'), str_contains($industry, 'fitness') => 'Start training',
            str_contains($industry, 'law'), str_contains($industry, 'legal') => 'Book a consultation',
            str_contains($industry, 'construction'), str_contains($industry, 'industrial') => 'Request a quote',
            default => 'Start a conversation',
        };
    }
}
