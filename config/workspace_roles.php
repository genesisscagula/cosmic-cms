<?php

return [
    'roles' => [
        'owner' => [
            'label' => 'Owner',
            'description' => 'Full workspace control, including billing, team, websites, and ownership.',
            'permissions' => ['*'],
        ],
        'admin' => [
            'label' => 'Admin',
            'description' => 'Full control of assigned websites, including pages, posts, commerce, media, publishing, and website deletion.',
            'permissions' => [
                'workspace.view', 'websites.create', 'websites.view', 'websites.edit', 'websites.delete',
                'websites.duplicate', 'pages.edit', 'pages.publish', 'ai.use', 'sparks.manage',
                'analytics.view', 'leads.view', 'leads.manage', 'sales.view', 'clients.manage',
            ],
        ],
        'editor' => [
            'label' => 'Editor',
            'description' => 'Builder-only access to assigned websites for editing existing text, images, buttons, saving, and publishing.',
            'permissions' => [
                'workspace.view', 'websites.view', 'pages.edit', 'pages.publish',
            ],
        ],
    ],
    'permission_labels' => [
        'workspace.view' => 'View workspace', 'workspace.manage' => 'Manage workspace',
        'billing.manage' => 'Manage billing', 'team.manage' => 'Manage team',
        'websites.create' => 'Create websites', 'websites.view' => 'View websites',
        'websites.edit' => 'Edit websites', 'websites.delete' => 'Delete websites',
        'websites.duplicate' => 'Duplicate websites', 'pages.edit' => 'Edit pages',
        'pages.publish' => 'Publish pages', 'ai.use' => 'Use AI generation',
        'sparks.use' => 'Use Sparks', 'sparks.manage' => 'Manage Sparks',
        'analytics.view' => 'View analytics', 'leads.view' => 'View leads',
        'leads.manage' => 'Manage leads', 'sales.view' => 'View sales',
        'clients.manage' => 'Manage clients', 'workspace.transfer' => 'Transfer workspace ownership',
    ],
];
