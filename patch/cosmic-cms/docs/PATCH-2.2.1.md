# Patch 2.2.1 — Workspace and Client Access

## Summary

Patch 2.2.1 introduces the first multi-account foundation for Cosmic CMS. Trial customers who register are attached to the CosmicReact workspace as restricted client accounts. They can access only websites assigned to their own user account and do not receive the full Cosmic CMS owner dashboard.

## Account structure

```text
Platform owner
└── CosmicReact workspace
    ├── Client A → assigned Website A
    ├── Client B → assigned Website B
    └── Client C → assigned Website C
```

The configured platform owner is:

```env
COSMIC_PLATFORM_OWNER_EMAIL=genesisscagula@gmail.com
COSMIC_AGENCY_WORKSPACE_NAME=CosmicReact
```

Both values may be changed in `.env`.

## Added

- `workspaces` table
- `workspace_user` membership table
- `users.account_type`
- `websites.workspace_id`
- `Workspace` model and relationships
- restricted client dashboard
- workspace-aware website authorization
- automatic workspace assignment during registration

## Registration behavior

### Trial registration

A user registering from a purchased/claimed trial becomes a `client` account.

- The user joins the platform owner's CosmicReact workspace.
- The purchased website remains assigned to that client.
- The platform owner can access the website through workspace ownership.
- The client can access only their assigned website.
- The client does not see the main owner dashboard.

### Normal registration

A user registering without a trial becomes a standalone `customer` and receives their own workspace.

## Authorization rules

### Platform owner

- Can see all websites inside owned workspaces.
- Can edit and delete those websites.
- Keeps the full Cosmic CMS dashboard.

### Client

- Can view and edit websites where `websites.user_id` matches their user ID.
- Cannot see other clients or websites.
- Cannot delete the website itself.
- Receives the restricted `My Websites` dashboard.

### Customer

- Owns a separate workspace.
- Sees only websites created under their own account.

## Commands

```bash
php artisan migrate
php artisan optimize:clear
npm run build
```

For an existing local database, the migration automatically:

1. Creates a workspace for every existing user.
2. Marks the configured owner email as `platform_owner`.
3. Moves each existing website into its owner's workspace.

## Test checklist

1. Log in as `genesisscagula@gmail.com`.
2. Confirm the full dashboard is visible.
3. Generate a trial in Incognito.
4. Select a plan and register using a new email.
5. Confirm the generated homepage and four empty pages are preserved.
6. Confirm the new client cannot access the full main dashboard.
7. Confirm the client sees only their assigned website.
8. Log back in as the platform owner and confirm the client website is visible.
9. Confirm another client cannot access the website URL directly.

## Security note

The restricted dashboard is not only a visual lock. Website policies enforce ownership on the server, so manually entering another website or page URL returns an authorization error.

## Future patches

This workspace foundation is ready for:

- inviting additional client users
- account transfers
- agency staff roles
- billing per workspace
- site limits per plan
- Sales Hub client management
- separate owner, admin, editor, and client permissions
