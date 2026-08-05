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
            'description' => 'Manages websites, content, publishing, insights, and clients without billing or ownership access.',
            'permissions' => [
                'workspace.view', 'websites.create', 'websites.view', 'websites.edit', 'websites.delete',
                'websites.duplicate', 'pages.edit', 'pages.publish', 'ai.use', 'sparks.manage',
                'analytics.view', 'leads.view', 'leads.manage', 'sales.view', 'clients.manage',
            ],
        ],
        'editor' => [
            'label' => 'Editor',
            'description' => 'Creates and edits website content, uses AI tools, and saves drafts.',
            'permissions' => [
                'workspace.view', 'websites.view', 'websites.edit', 'pages.edit', 'ai.use', 'sparks.use',
            ],
        ],
        'client' => [
            'label' => 'Client',
            'description' => 'Reviews assigned work and approved reporting without editing workspace content.',
            'permissions' => ['workspace.view', 'websites.view', 'analytics.view', 'leads.view'],
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
