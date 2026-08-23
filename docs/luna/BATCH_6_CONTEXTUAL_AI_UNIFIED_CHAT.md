# Batch 6 — Contextual AI Icon Unified Chat

**PASS — static integration QA**

## New interaction
Contextual AI icons no longer create the legacy green context-message bubble/card.

Flow:
1. User clicks a supported ✦ target.
2. The normal Luna chat opens.
3. The selected target is attached as chat context.
4. Luna shows a short `looking at …` typing state.
5. A normal Luna assistant bubble invites the user to describe the change.
6. `Edit manually · 0 credits` remains available inside the same chat.

## Supported contextual targets
- page sections;
- repeater/cards where detected;
- headings/text/labels/buttons/images;
- Header Logo;
- Header Navigation;
- Header CTA;
- Site Header;
- Site Footer;
- Footer Logo and documented footer targets.

## Important behavior
- Clicking ✦ alone does not execute a mutation.
- Context is not a separate conversation or assistant.
- Old `role: context` green messages are no longer produced.
- The message renderer no longer has a special emerald context-message style.
- The manual-edit entry is a small violet/neutral action instead of an automatically displayed green card.
- Existing page/section action requests still go through the documentation-grounded Luna runtime.
- Manual edits remain 0 credits when they do not invoke AI/API services.

## Manual shell hotfix
Header CTA direct editing now writes the global header CTA label/URL instead of trying to map the CTA into a page Spark. Footer scalar targets can also persist through their documented global-footer field path.
