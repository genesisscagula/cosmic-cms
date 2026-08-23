<?php

return [
    'platform_owner_email' => env('COSMIC_PLATFORM_OWNER_EMAIL', 'genesisscagula@gmail.com'),
    'platform_owner_plan' => env('COSMIC_PLATFORM_OWNER_PLAN', 'agency_pro'),
    'agency_workspace_name' => env('COSMIC_AGENCY_WORKSPACE_NAME', 'CosmicReact'),

    // Every public trial receives an isolated staged Website owned by this
    // account until verified payment provisioning transfers its full page set.
    'trial_website_owner_email' => env(
        'TRIAL_WEBSITE_OWNER_EMAIL',
        env('COSMIC_PLATFORM_OWNER_EMAIL', 'genesisscagula@gmail.com')
    ),

    // Shared website used by the public /start trial flow. Configure this per environment.
    'trial_website_id' => (int) env('TRIAL_WEBSITE_ID', 1),
];
