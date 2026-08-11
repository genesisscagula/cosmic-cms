<?php

namespace App\Services;

use App\Cosmic\Capabilities\CapabilityDecision;
use App\Cosmic\Capabilities\CapabilityEngine;
use App\Models\User;
use InvalidArgumentException;

final class AgencyPlanEntitlementService
{
    private const AGENCY_PLANS = ['agency_starter', 'agency_growth', 'agency_pro'];

    public function __construct(private readonly CapabilityEngine $capabilities)
    {
    }

    public function summary(User $user): array
    {
        $plan = $this->capabilities->plan($user);

        return [
            'is_agency_plan' => $plan->family() === 'agency',
            'plan_key' => $plan->key(),
            'tier' => $plan->tier(),
            'limits' => [
                'websites' => $plan->limit('max_sites'),
                'pages_per_site' => $plan->limit('max_pages_per_site'),
                'sparks_per_site' => $plan->limit('max_sparks_per_site'),
                'owned_sparks' => $plan->limit('max_owned_sparks'),
                'templates' => $plan->limit('template_limit'),
                'marketplace_previews' => $plan->limit('marketplace_preview_limit'),
                'team_members' => $plan->limit('team_members', 0),
            ],
            'access' => [
                'templates' => $plan->limit('template_access_level', 'agency_starter'),
                'sparks' => $plan->limit('spark_access_level', 'agency_starter'),
                'analytics' => $plan->limit('analytics_level', 'none'),
                'leads' => $plan->limit('leads_level', 'none'),
                'sales' => $plan->limit('sales_level', 'none'),
                'commerce' => $plan->limit('commerce_level', 'connector'),
                'white_label' => $plan->limit('white_label_level', 'none'),
            ],
            'features' => $this->featureMap($user),
        ];
    }

    public function assertAgency(User $user): void
    {
        $plan = $this->capabilities->plan($user);
        if ($plan->family() !== 'agency' || ! in_array($plan->key(), self::AGENCY_PLANS, true)) {
            throw new InvalidArgumentException("Plan [{$plan->key()}] is not an Agency plan.");
        }
    }

    public function canCreateWebsite(User $user, int $currentWebsites, int $increment = 1): CapabilityDecision
    {
        return $this->capabilities->withinLimit($user, 'max_sites', $currentWebsites, $increment);
    }

    public function canAddTeamMember(User $user, int $currentMembers, int $increment = 1): CapabilityDecision
    {
        return $this->capabilities->withinLimit($user, 'team_members', $currentMembers, $increment);
    }

    public function canPreviewMarketplaceSpark(User $user, int $catalogIndex): bool
    {
        $limit = $this->capabilities->value($user, 'marketplace_preview_limit');
        return $limit === null || $catalogIndex < (int) $limit;
    }

    public function canUseFeature(User $user, string $feature): CapabilityDecision
    {
        return $this->capabilities->decide($user, $feature, true);
    }

    private function featureMap(User $user): array
    {
        $features = [
            'website_clone', 'client_preview_links', 'website_search_filter',
            'per_website_lead_inbox', 'per_website_analytics_summary', 'agency_workspace',
            'activity_history', 'agency_insights', 'aggregated_analytics',
            'aggregated_leads', 'aggregated_sales', 'website_date_filtering',
            'client_handoff', 'ownership_transfer', 'shared_assets', 'shared_sparks',
            'shared_templates', 'custom_preview_branding', 'bulk_actions', 'bulk_export',
            'client_accounts', 'revenue_reporting', 'conversion_reporting',
            'lead_source_reporting', 'sales_funnel_reporting', 'ai_insights_foundation',
            'advanced_white_label', 'branded_reports', 'granular_permissions',
            'api_access', 'webhooks', 'priority_ai', 'advanced_publication_history',
            'early_access', 'commerce_connector', 'commerce_store',
        ];

        return collect($features)
            ->mapWithKeys(fn (string $feature) => [$feature => $this->capabilities->allows($user, $feature)])
            ->all();
    }
}
