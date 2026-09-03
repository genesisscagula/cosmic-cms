export const HEADER_VARIANTS = [
    { id: 'classic_header', name: 'White + Primary CTA', description: 'White header, original/colored logo and a primary CTA.', tone: 'dark', background: 'white', layout: 'standard', cta: 'primary' },
    { id: 'primary_header', name: 'Primary Contrast', description: 'Primary background, light logo/navigation and a white CTA.', tone: 'light', background: 'primary', layout: 'standard', cta: 'white' },
    { id: 'split_navigation_header', name: 'Center Logo + CTA', description: 'Centered logo with navigation split left/right and a CTA.', tone: 'dark', background: 'white', layout: 'split', cta: 'primary' },
    { id: 'centered_header', name: 'Center Logo', description: 'Centered logo with navigation split left/right and no CTA.', tone: 'dark', background: 'white', layout: 'split', cta: 'none' },
    { id: 'overlay_hero_header', name: 'Overlay Hero', description: 'Transparent overlay with a light logo and navigation over the hero.', tone: 'light', background: 'overlay', layout: 'standard', cta: 'white' },
    { id: 'overlay_centered_header', name: 'Overlay Center Logo', description: 'Transparent hero overlay with centered light logo and split navigation.', tone: 'light', background: 'overlay', layout: 'split', cta: 'none' },
    { id: 'secondary_header', name: 'Secondary Surface', description: 'Theme-matched secondary shell, light or dark depending on the color family.', tone: 'auto', background: 'secondary', layout: 'standard', cta: 'primary' },
];

export const FOOTER_VARIANTS = [
    { id: 'classic', name: 'White Mega', description: 'Clean white mega footer with brand, CTA and multi-column navigation.', background: 'white', layout: 'split', cta: true },
    { id: 'primary', name: 'Primary Mega', description: 'Primary color footer with light branding and a white conversion CTA.', background: 'primary', layout: 'split', cta: true },
    { id: 'centered_cta', name: 'Centered + CTA', description: 'Centered brand statement and CTA with navigation columns below.', background: 'white', layout: 'centered', cta: true },
    { id: 'centered', name: 'Centered Minimal', description: 'Centered brand and navigation without a footer CTA.', background: 'white', layout: 'centered', cta: false },
    { id: 'split', name: 'Split Editorial', description: 'Editorial split between brand/contact details and navigation.', background: 'surface', layout: 'editorial', cta: true },
    { id: 'brand', name: 'Primary Brand', description: 'Large brand-led composition on the primary color family.', background: 'primary', layout: 'brand', cta: true },
    { id: 'secondary', name: 'Secondary Surface', description: 'Theme-matched secondary background that can resolve light or dark.', background: 'secondary', layout: 'split', cta: true },
];


const HEADER_VARIANT_IDS = new Set(HEADER_VARIANTS.map((item) => item.id));
const FOOTER_VARIANT_IDS = new Set(FOOTER_VARIANTS.map((item) => item.id));
const HEADER_LEGACY_ALIASES = {
    glassmorphism_header: 'classic_header',
    dark_cyan_header: 'classic_header',
    floating_glass_header: 'classic_header',
    minimal_header: 'classic_header',
};
const FOOTER_LEGACY_ALIASES = {
    contact: 'centered_cta',
    newsletter: 'centered_cta',
    cta: 'centered_cta',
    detailed: 'classic',
};

export const footerBackgroundForVariant = (variant) => {
    if (['primary', 'brand'].includes(variant)) return 'primary';
    if (variant === 'split') return 'surface';
    if (variant === 'secondary') return 'secondary';
    return 'white';
};

export const normalizeHeaderVariantState = (header = {}) => {
    const source = header && typeof header === 'object' ? header : {};
    const requested = String(source.type || 'classic_header');
    const wasLegacy = Object.prototype.hasOwnProperty.call(HEADER_LEGACY_ALIASES, requested);
    let type = HEADER_LEGACY_ALIASES[requested] || requested;
    if (!HEADER_VARIANT_IDS.has(type)) type = 'classic_header';
    if (wasLegacy && source.overlay_header_on_banner) type = 'overlay_hero_header';
    return {
        ...source,
        type,
        overlay_header_on_banner: ['overlay_hero_header', 'overlay_centered_header'].includes(type),
        allow_light_logo_filter: source.allow_light_logo_filter !== false,
    };
};

export const normalizeFooterVariantState = (footer = {}) => {
    const source = footer && typeof footer === 'object' ? footer : {};
    const mega = source.mega_footer && typeof source.mega_footer === 'object' ? source.mega_footer : {};
    let variant = FOOTER_LEGACY_ALIASES[String(mega.variant || 'classic')] || String(mega.variant || 'classic');
    if (!FOOTER_VARIANT_IDS.has(variant)) variant = 'classic';
    return {
        ...source,
        type: 'minimal_footer',
        mega_enabled: true,
        allow_light_logo_filter: source.allow_light_logo_filter !== false,
        mega_footer: {
            ...mega,
            enabled: true,
            variant,
            theme: footerBackgroundForVariant(variant),
        },
    };
};

export const LIGHT_LOGO_HEADER_VARIANTS = new Set(['primary_header', 'overlay_hero_header', 'overlay_centered_header']);
export const LIGHT_LOGO_FOOTER_VARIANTS = new Set(['primary', 'brand']);

export const headerVariantById = (id) => HEADER_VARIANTS.find((item) => item.id === id) || HEADER_VARIANTS[0];
export const footerVariantById = (id) => FOOTER_VARIANTS.find((item) => item.id === id) || FOOTER_VARIANTS[0];

export const resolveShellSurface = (mode, palette = {}) => {
    if (mode === 'primary') {
        return {
            background: palette.primary || '#243447',
            text: palette.onPrimary || palette.on_primary || '#FFFFFF',
            muted: palette.onPrimary || palette.on_primary || '#FFFFFF',
            border: 'color-mix(in srgb, currentColor 18%, transparent)',
            tone: 'light',
        };
    }
    if (mode === 'secondary') {
        const tone = palette.shellSecondaryTone || palette.shell_secondary_tone || 'dark';
        return {
            background: palette.shellSecondary || palette.shell_secondary || palette.secondary || '#30475E',
            text: palette.shellSecondaryText || palette.shell_secondary_text || (tone === 'light' ? '#0F172A' : '#FFFFFF'),
            muted: palette.shellSecondaryMuted || palette.shell_secondary_muted || (tone === 'light' ? '#64748B' : 'rgba(255,255,255,.72)'),
            border: palette.shellSecondaryBorder || palette.shell_secondary_border || (tone === 'light' ? '#E2E8F0' : 'rgba(255,255,255,.16)'),
            tone: tone === 'light' ? 'dark' : 'light',
        };
    }
    if (mode === 'surface') {
        return {
            background: palette.surface || '#F8FAFC',
            text: palette.heading || palette.onSurface || palette.on_surface || '#0F172A',
            muted: palette.muted || '#64748B',
            border: palette.border || '#E2E8F0',
            tone: 'dark',
        };
    }
    return {
        background: '#FFFFFF',
        text: palette.heading || palette.onSurface || palette.on_surface || '#0F172A',
        muted: palette.muted || '#64748B',
        border: palette.border || '#E2E8F0',
        tone: 'dark',
    };
};
