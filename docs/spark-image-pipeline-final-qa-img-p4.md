# IMG-P4 — Final Spark Image Pipeline QA

Final regression scope:
- all 39 AI-supported Sparks
- top-level and nested image slots
- Slider Fade multiple images
- video poster images
- agency before/after images
- Team portraits
- Testimonial avatars
- Case Study nested images
- trial generation
- registered Builder generation
- page save/reload
- remote provider safety checks
- purchase/publish localization
- branding/logo exclusion

Final hardening:
- `avatar` fields now participate in MediaAssetSafetyService, MediaAssetLifecycleService, and MediaPackImageService.
- localized people imagery is no longer skipped merely because the destination field is named `avatar`.
- Page model stores `blocks` and `published_blocks` as arrays, so nested slot paths survive save/reload.
- provider URL scanning remains recursive, including nested Slider/Team/Testimonial structures.
