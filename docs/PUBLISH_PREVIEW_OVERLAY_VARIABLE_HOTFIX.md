# Publish / Preview Deployment Hotfix

Cause:
`CmsHtmlCompiler` rendered `data-cosmic-primary-overlay-allowed` using `$overlayPrimaryAllowed` without initializing that variable.

Fix:
Initialize `$overlayPrimaryAllowed` from the already-authoritative `$overlayHeaderCompatible` flag before rendering the static header.

This restores PagePublisher -> PreviewDeploymentService publish flow without altering overlay-header behavior.
