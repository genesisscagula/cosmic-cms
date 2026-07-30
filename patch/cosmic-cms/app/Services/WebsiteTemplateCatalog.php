<?php

namespace App\Services;

/**
 * Small product-owned catalog. Templates are source definitions; instantiated
 * websites and pages continue to use the existing database records.
 */
class WebsiteTemplateCatalog
{
    public const AURORA_AGENCY = 'aurora-agency';
    public const MIDNIGHT_STUDIO = 'midnight-studio';
    public const OBSIDIAN_ATELIER = 'obsidian-atelier';
    public const FORGE_WORKS = 'forge-works';

    public function supports(?string $template): bool
    {
        return in_array($template, [
            self::AURORA_AGENCY,
            self::MIDNIGHT_STUDIO,
            self::OBSIDIAN_ATELIER,
            self::FORGE_WORKS,
        ], true);
    }

    public function websiteAttributes(string $template, string $websiteName): array
    {
        return match ($template) {
            self::AURORA_AGENCY, self::MIDNIGHT_STUDIO, self::OBSIDIAN_ATELIER, self::FORGE_WORKS => [
                'theme_settings' => [
                    'primary' => match ($template) {
                        self::MIDNIGHT_STUDIO => 'midnight',
                        self::OBSIDIAN_ATELIER => 'obsidian',
                        self::FORGE_WORKS => 'asphalt',
                        default => 'violet',
                    },
                    'secondary' => 'white',
                    'tertiary' => 'stone',
                    'auto' => true,
                ],
                'global_header' => [
                    'type' => 'glassmorphism_header',
                    'logo_text' => $websiteName,
                    'cta_label' => 'Start a project',
                    'cta_url' => '#',
                    'menu' => [
                        ['label' => 'Home', 'url' => '#'],
                        ['label' => 'Work', 'url' => '#work'],
                        ['label' => 'Services', 'url' => '#services'],
                        ['label' => 'Contact', 'url' => '#contact'],
                    ],
                ],
                'global_footer' => [
                    'type' => 'minimal_footer',
                    'logo_text' => $websiteName,
                    'copyright' => '© '.now()->year.'. All rights reserved.',
                ],
            ],
            default => [],
        };
    }

    public function pages(string $template): array
    {
        return match ($template) {
            self::AURORA_AGENCY, self::MIDNIGHT_STUDIO, self::OBSIDIAN_ATELIER, self::FORGE_WORKS => [[
                'title' => 'Home',
                'slug' => 'home',
                'status' => 'draft',
                'blocks' => $this->starterHomeBlocks($template),
            ]],
            default => [],
        };
    }

    private function starterHomeBlocks(string $template): array
    {
        $blocks = [
            [
                'type' => 'hero_background_image', 'theme' => 'auto',
                'tagline' => 'Independent creative studio',
                'heading' => 'Brands with a clearer point of view.',
                'text' => 'Aurora partners with ambitious teams to turn thoughtful strategy, identity, and digital experiences into work people remember.',
                'button_label' => 'Start a project', 'button_url' => '#contact',
                'image_url' => 'https://images.unsplash.com/photo-1558655146-9f40138edfeb?auto=format&fit=crop&w=2000&q=85',
                'overlayOpacity' => 48, 'textAlign' => 'center', 'height' => 'large',
            ],
            [
                'type' => 'feature_image_left', 'theme' => 'auto',
                'category' => 'Built for momentum',
                'heading' => 'A focused partner for your next chapter.',
                'text' => 'From an early idea to a confident launch, we bring strategy, design, and delivery into one practical working relationship.',
                'button_label' => 'See how we work', 'button_url' => '#process',
                'image_url' => 'https://images.unsplash.com/photo-1545235617-9465d2a55698?auto=format&fit=crop&w=1600&q=85',
            ],
            [
                'type' => 'services_bento', 'theme' => 'auto', 'tagline' => 'What we do',
                'heading' => 'The essentials for a brand that is ready to grow.',
                'description' => 'A compact, collaborative process for teams that need a strong foundation and a website that carries it forward.',
                'services' => [
                    ['icon' => '01', 'title' => 'Brand strategy', 'desc' => 'Clarify the story, audience, and decisions that give your brand direction.'],
                    ['icon' => '02', 'title' => 'Identity systems', 'desc' => 'Build a visual language that stays recognizable across every customer touchpoint.'],
                    ['icon' => '03', 'title' => 'Digital experiences', 'desc' => 'Create useful, polished websites that make the next step easy for your customers.'],
                ],
            ],
            [
                'type' => 'process_timeline', 'theme' => 'auto', 'category' => 'Our process',
                'heading' => 'Clear stages. Better decisions.',
                'text' => 'We keep the work visible from the first conversation through launch, so progress never feels like a black box.',
                'steps' => [
                    ['number' => '01', 'title' => 'Discover', 'text' => 'Align on goals, audience, and the opportunity in front of you.'],
                    ['number' => '02', 'title' => 'Define', 'text' => 'Turn the strongest ideas into a clear creative direction.'],
                    ['number' => '03', 'title' => 'Design', 'text' => 'Shape the system, pages, and details that bring the direction to life.'],
                    ['number' => '04', 'title' => 'Launch', 'text' => 'Refine the final experience and prepare your team to move forward.'],
                ],
            ],
            [
                'type' => 'testimonials_carousel', 'theme' => 'auto', 'tagline' => 'Client notes',
                'heading' => 'A template ready for your proof.',
                'text' => 'Replace these starter testimonials with approved customer feedback before publishing your site.',
                'testimonials' => [
                    ['avatar' => '/storage/cms-images/avatars/avatar-1.jpg', 'name' => 'Client name', 'company' => 'Client company', 'quote' => 'Add an approved client quote that speaks to the outcome of working together.', 'rating' => 5],
                    ['avatar' => '/storage/cms-images/avatars/avatar-2.jpg', 'name' => 'Client name', 'company' => 'Client company', 'quote' => 'Use this space for a concise, specific testimonial from a real customer.', 'rating' => 5],
                    ['avatar' => '/storage/cms-images/avatars/avatar-3.jpg', 'name' => 'Client name', 'company' => 'Client company', 'quote' => 'Keep social proof factual, approved, and relevant to the service you offer.', 'rating' => 5],
                ],
            ],
            [
                'type' => 'hero_centered_cta', 'theme' => 'auto', 'tagline' => 'Start a conversation',
                'heading' => 'Have a project in motion?',
                'text' => 'Tell us where you are today and what you want the next version of your brand to make possible.',
                'button_label' => 'Get in touch', 'button_url' => '#contact',
            ],
            [
                'type' => 'pricing_cards', 'theme' => 'auto', 'tagline' => 'Ways to work together',
                'heading' => 'Engagements that fit the work.',
                'text' => 'Starter packages are editable. Replace scope, pricing, and terms with your approved offer before publishing.',
                'plans' => [
                    ['badge' => '', 'featured' => false, 'title' => 'Foundation', 'price' => 'Custom', 'period' => '', 'description' => 'A focused starting point for teams clarifying their brand direction.', 'button_label' => 'Ask about scope', 'button_url' => '#contact', 'features' => [['text' => 'Discovery workshop'], ['text' => 'Core positioning'], ['text' => 'Creative direction'], ['text' => 'Practical next-step plan'], ['text' => 'Editable project scope']]],
                    ['badge' => 'Popular', 'featured' => true, 'title' => 'Identity + web', 'price' => 'Custom', 'period' => '', 'description' => 'A complete brand and website engagement for a meaningful next launch.', 'button_label' => 'Plan a project', 'button_url' => '#contact', 'features' => [['text' => 'Brand strategy'], ['text' => 'Visual identity'], ['text' => 'Website design'], ['text' => 'Reusable page system'], ['text' => 'Launch support']]],
                    ['badge' => '', 'featured' => false, 'title' => 'Ongoing partner', 'price' => 'Custom', 'period' => '', 'description' => 'Flexible senior creative support for teams with regular work in motion.', 'button_label' => 'Start a conversation', 'button_url' => '#contact', 'features' => [['text' => 'Priority creative support'], ['text' => 'Campaign and page updates'], ['text' => 'Design system stewardship'], ['text' => 'Flexible monthly rhythm'], ['text' => 'Clear planning cadence']]],
                ],
            ],
            [
                'type' => 'stats_modern', 'theme' => 'auto', 'eyebrow' => 'Made for editing',
                'heading' => 'A starter site that remains yours.',
                'text' => 'Every line, image, offer, and metric in this template is editable in the Builder.',
                'metrics' => [
                    ['value' => '100%', 'label' => 'Editable content', 'description' => 'Make the starter copy sound like your brand.'],
                    ['value' => '8', 'label' => 'Reusable sections', 'description' => 'A balanced homepage built from existing blocks.'],
                    ['value' => '1', 'label' => 'Shared theme', 'description' => 'Apply a consistent violet-led visual system.'],
                    ['value' => '0', 'label' => 'Required code edits', 'description' => 'Update the experience directly in the Builder.'],
                ],
            ],
        ];

        $copy = match ($template) {
            self::MIDNIGHT_STUDIO => [
                'tagline' => 'Strategic creative studio',
                'heading' => 'Make the next version of your business unmistakable.',
                'text' => 'Meridian combines clear strategy, purposeful design, and strong digital execution for teams ready to move with confidence.',
                'category' => 'Designed for clarity',
                'featureHeading' => 'The work gets simpler when the direction is clear.',
            ],
            self::OBSIDIAN_ATELIER => [
                'tagline' => 'Independent luxury atelier',
                'heading' => 'A quieter, more considered way to stand apart.',
                'text' => 'Noir Atelier shapes refined brand experiences for founders who value restraint, detail, and lasting distinction.',
                'category' => 'Made with intention',
                'featureHeading' => 'Every detail should feel like it belongs.',
            ],
            self::FORGE_WORKS => [
                'tagline' => 'Industrial design and build',
                'heading' => 'Built for the work that keeps moving.',
                'text' => 'Forge Works helps practical businesses communicate their capability, craft, and value with a stronger digital foundation.',
                'category' => 'Built for progress',
                'featureHeading' => 'A clear system for complex work.',
            ],
            default => [
                'tagline' => 'Independent creative studio',
                'heading' => 'Brands with a clearer point of view.',
                'text' => 'Aurora partners with ambitious teams to turn thoughtful strategy, identity, and digital experiences into work people remember.',
                'category' => 'Built for momentum',
                'featureHeading' => 'A focused partner for your next chapter.',
            ],
        };

        $blocks[0]['tagline'] = $copy['tagline'];
        $blocks[0]['heading'] = $copy['heading'];
        $blocks[0]['text'] = $copy['text'];
        $blocks[1]['category'] = $copy['category'];
        $blocks[1]['heading'] = $copy['featureHeading'];

        return $blocks;
    }
}
