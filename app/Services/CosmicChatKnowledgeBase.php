<?php

namespace App\Services;

use App\Cosmic\Pricing\ActionPricing;
use App\Cosmic\Pricing\CreditPackageRegistry;

class CosmicChatKnowledgeBase
{
    public function context(): array
    {
        $knowledge = config('cosmic-chat-knowledge', []);

        return [
            'knowledge_version' => $knowledge['version'] ?? '1.2',
            'product' => $knowledge['product'] ?? [],
            'verified_product_facts' => $knowledge['verified_facts'] ?? [],
            'terminology' => $knowledge['terminology'] ?? [],
            'plans' => $this->plans(),
            'credit_packages' => $this->creditPackages(),
            'account_credit_action_costs' => ActionPricing::all(),
            'trial' => $this->trial(),
            'spark_collections' => $this->sparkCollections(),
            'answer_boundaries' => $knowledge['answer_boundaries'] ?? [],
        ];
    }

    private function plans(): array
    {
        return collect(config('cosmic-plans', []))
            ->map(function (array $plan, string $key): array {
                $capabilities = $plan['capabilities'] ?? [];

                return [
                    'key' => $key,
                    'name' => $plan['label'] ?? $key,
                    'family' => $plan['family'] ?? null,
                    'tier' => $plan['tier'] ?? null,
                    'price_usd' => $plan['price_usd'] ?? null,
                    'included_credits' => $plan['credits'] ?? null,
                    'description' => $plan['description'] ?? null,
                    'limits' => [
                        'max_sites' => $capabilities['max_sites'] ?? null,
                        'max_pages_per_site' => $capabilities['max_pages_per_site'] ?? null,
                        'max_sparks_per_site' => $capabilities['max_sparks_per_site'] ?? null,
                        'owned_sparks_limit' => $capabilities['max_owned_sparks'] ?? null,
                        'template_limit' => $capabilities['template_limit'] ?? null,
                        'team_members' => $capabilities['team_members'] ?? null,
                    ],
                    'access' => [
                        'template_access_level' => $capabilities['template_access_level'] ?? null,
                        'spark_access_level' => $capabilities['spark_access_level'] ?? null,
                        'seo_level' => $capabilities['seo_level'] ?? null,
                        'analytics_level' => $capabilities['analytics_level'] ?? null,
                        'leads_level' => $capabilities['leads_level'] ?? null,
                        'sales_level' => $capabilities['sales_level'] ?? null,
                    ],
                    'features' => $this->enabledCapabilities($capabilities),
                ];
            })
            ->values()
            ->all();
    }

    private function enabledCapabilities(array $capabilities): array
    {
        return collect($capabilities)
            ->filter(fn ($value) => $value === true)
            ->keys()
            ->values()
            ->all();
    }

    private function creditPackages(): array
    {
        return collect(CreditPackageRegistry::all())
            ->map(fn (array $package, string $key): array => [
                'key' => $key,
                'name' => $package['label'] ?? $key,
                'credits' => $package['credits'] ?? null,
                'price_usd' => $package['price_usd'] ?? null,
                'price_php' => $package['price_php'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function trial(): array
    {
        return [
            'start_url' => config('cosmic-chat-knowledge.product.start_url', 'https://www.cosmiccms.com/start'),
            'starting_guest_credits' => TrialCreditService::STARTING_BALANCE,
            'trial_action_costs' => [
                'page_style' => TrialCreditService::PAGE_STYLE,
                'regenerate_page' => TrialCreditService::REGENERATE_PAGE,
                'generate_logo' => TrialCreditService::GENERATE_LOGO,
                'match_logo_to_theme' => TrialCreditService::MATCH_LOGO_TO_THEME,
                'match_theme_to_logo' => TrialCreditService::MATCH_THEME_TO_LOGO,
            ],
        ];
    }

    private function sparkCollections(): array
    {
        return collect(config('cosmic-sparks.collections', []))
            ->map(fn (array $collection, string $key): array => [
                'key' => $key,
                'name' => $collection['label'] ?? $key,
                'access_level' => $collection['access_level'] ?? null,
                'description' => $collection['description'] ?? null,
            ])
            ->values()
            ->all();
    }
}
