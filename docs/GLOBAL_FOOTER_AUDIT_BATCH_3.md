# Global Footer Audit — Batch 3

**PASS after patch**

## Individual Builder editing
- Footer logo: Edit + Luna
- Tagline: manual + Luna
- CTA: label/URL manual + Luna
- Columns: add/edit/remove/reorder
- Menu links: add/edit/remove/reorder
- Contact: email/phone/address
- Social links: add/edit/remove
- Privacy / Terms
- Copyright
- Mega Footer enable/disable

All manual footer operations are 0 credits.

## Stable paths
Examples:
- `footer.logo_image_url`
- `footer.mega_footer.tagline`
- `footer.mega_footer.cta`
- `footer.mega_footer.columns.0.items.0`
- `footer.contact.email`
- `footer.social_links.0`
- `footer.privacy`
- `footer.terms`
- `footer.copyright`

## Luna
Footer element targets now carry exact stable paths. Both planner flows receive CURRENT FOOTER JSON. Server-side footer normalization preserves unrelated fields and caps columns/items/social links.

## Export
Static footer compiler renders contact/social data, and PagePublisher rewrites internal footer URLs for static deployment.
