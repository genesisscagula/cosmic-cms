<?php

return [
    'platform_owner_email' => env('COSMIC_PLATFORM_OWNER_EMAIL', 'genesisscagula@gmail.com'),
    'platform_owner_plan' => env('COSMIC_PLATFORM_OWNER_PLAN', 'agency_pro'),
    'agency_workspace_name' => env('COSMIC_AGENCY_WORKSPACE_NAME', 'CosmicReact'),

    // Shared website used by the public /start trial flow. Configure this per environment.
    'trial_website_id' => (int) env('TRIAL_WEBSITE_ID', 1),
];
