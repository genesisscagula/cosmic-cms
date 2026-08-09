<?php

return [
    'version' => '1.2',

    'product' => [
        'name' => 'Cosmic CMS',
        'website' => 'https://www.cosmiccms.com',
        'start_url' => 'https://www.cosmiccms.com/start',
        'pricing_url' => 'https://www.cosmiccms.com/pricing',
        'summary' => 'Cosmic CMS is an AI-assisted website builder that creates an editable business website starting point from a prompt.',
    ],

    // Curated statements here must describe behavior already implemented in the app.
    // Dynamic prices, limits and credit costs are assembled from their canonical registries.
    'verified_facts' => [
        'Generated websites remain editable in the Cosmic CMS visual builder.',
        'Cosmic CMS uses reusable website sections called Sparks.',
        'The builder supports website-wide theme customization plus header and footer editing when the active plan permits those capabilities.',
        'Cosmic CMS supports contact forms on the personal plans currently configured in the application.',
        'Personal plan capabilities and limits differ by tier; use the live plan data supplied in this knowledge context.',
        'Static HTML export is enabled on the currently configured Starter, Growth, and Pro personal plans.',
        'The Pro personal plan currently includes custom-domain capability; do not imply custom-domain support for another tier unless the live plan data says so.',
        'Agency plans are separate from personal plans and have their own website, template, Spark, analytics, leads, team, and agency-tool limits.',
        'The public trial begins at /start and uses Guest Cosmic Credits for supported trial customization actions.',
    ],

    'terminology' => [
        'Sparks' => 'Reusable website sections/layouts available in Cosmic CMS.',
        'Guest Cosmic Credits' => 'Credits assigned to the public trial for supported customization actions before signup.',
        'Owned Sparks' => 'Sparks attached to a customer account/library under the applicable plan rules.',
    ],

    'answer_boundaries' => [
        'Do not promise a ranking position, traffic result, lead volume, revenue result, or delivery timeline.',
        'Do not invent discounts, refunds, promotional offers, integrations, hosting terms, domain-registration terms, or support response times.',
        'Do not claim a feature is available on a plan unless the supplied live capability data supports it.',
        'For account-specific billing, payment failures, private account data, or a request requiring human review, say the Cosmic CMS team needs to review it.',
    ],
];
