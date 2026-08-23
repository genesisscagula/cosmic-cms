# Global Header and Footer

## Header
The header is global site shell content.

### Logo
Luna may help generate/change the logo or the user may choose an existing Media Library asset through documented manual editing. Logo rendering must preserve aspect ratio and use centralized responsive display sizing.

### Navigation
Header navigation is plain dropdown navigation.
Supported manual/Luna actions:
- add, rename, remove, and reorder menu items;
- change URLs;
- create nested submenus.

Maximum depth: **3 levels total**.
A fourth level is not supported.

### Header Mega Menu
**Unsupported.** Luna must not enable or claim to create a header Mega Menu. If requested, Luna should explain that standard nested navigation up to three levels is supported and offer that alternative.

### CTA
Supported: edit CTA label and URL manually or through Luna.

## Footer
The footer is global site shell content. Supported fields depend on the footer schema and may include logo, tagline/text, navigation/link groups, contact details, social links, and CTA.

### Mega Footer
Supported where the current Mega Footer schema is available. Mega Footer is a footer capability and must not be confused with header navigation.

## Persistence
Global header/footer mutations should persist across site pages. Builder and live output should use the same stored shell state.
