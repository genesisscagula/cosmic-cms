<?php

namespace App\Services;

use App\Cosmic\Capabilities\CapabilityDecision;
use App\Cosmic\Capabilities\CapabilityEngine;
use App\Models\User;
use InvalidArgumentException;

final class PersonalPlanEntitlementService
{
    private const PERSONAL_PLANS = ['starter', 'growth', 'pro'];

    public function __construct(private readonly CapabilityEngine $capabilities)
    {
    }

    public function summary(User $user): array
    {
        $plan = $this->capabilities->plan($user);

        return [
            'is_personal_plan' => $plan->family() === 'personal',
            'plan_key' => $plan->key(),
            'tier' => $plan->tier(),
            'limits' => [
                'websites' => $plan->limit('max_sites'),
                'pages_per_site' => $plan->limit('max_pages_per_site'),
                'sparks_per_site' => $plan->limit('max_sparks_per_site'),
                'owned_sparks' => $plan->limit('max_owned_sparks'),
                'templates' => $plan->limit('template_limit'),
            ],
            'access' => [
                'templates' => $plan->limit('template_access_level', 'starter'),
                'sparks' => $plan->limit('spark_access_level', 'free'),
                'analytics' => $plan->limit('analytics_level', 'none'),
                'leads' => $plan->limit('leads_level', 'none'),
                'sales' => $plan->limit('sales_level', 'none'),
                'commerce' => $plan->limit('commerce_level', 'connector'),
                'blog' => $plan->limit('blog_level', 'none'),
                'seo' => $plan->limit('seo_level', 'none'),
                'support' => $plan->limit('support_level', 'standard'),
            ],
            'features' => $this->featureMap($user),
        ];
    }

    public function assertPersonal(User $user): void
    {
        $plan = $this->capabilities->plan($user);

        if ($plan->family() !== 'personal' || ! in_array($plan->key(), self::PERSONAL_PLANS, true)) {
            throw new InvalidArgumentException("Plan [{$plan->key()}] is not a Personal plan.");
        }
    }

    public function canCreatePage(User $user, int $currentPages, int $increment = 1): CapabilityDecision
    {
        return $this->capabilities->withinLimit($user, 'max_pages_per_site', $currentPages, $increment);
    }

    public function canActivateSpark(User $user, int $currentSparks, int $increment = 1): CapabilityDecision
    {
        return $this->capabilities->withinLimit($user, 'max_sparks_per_site', $currentSparks, $increment);
    }

    public function canOwnSpark(User $user, int $currentOwnedSparks, int $increment = 1): CapabilityDecision
    {
        return $this->capabilities->withinLimit($user, 'max_owned_sparks', $currentOwnedSparks, $increment);
    }

    public function canUseFeature(User $user, string $feature): CapabilityDecision
    {
        return $this->capabilities->decide($user, $feature, true);
    }

    private function featureMap(User $user): array
    {
        $features = [
            'ai_website_generation', 'ai_content_generation', 'ai_image_selection',
            'theme_customization', 'header_footer_builder', 'contact_forms',
            'landing_pages', 'lead_management', 'form_submission_management',
            'website_duplicate_draft', 'custom_scripts', 'custom_forms',
            'booking_ui_sparks', 'version_history', 'redirect_management',
            'priority_ai', 'branding_removed', 'custom_domain', 'export_static',
            'commerce_connector', 'commerce_store',
        ];

        return collect($features)
            ->mapWithKeys(fn (string $feature) => [$feature => $this->capabilities->allows($user, $feature)])
            ->all();
    }
}
