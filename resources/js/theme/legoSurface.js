const normalizeSurface = (value) => ['auto','white','surface','slate','primary'].includes(String(value || '').toLowerCase())
    ? String(value || '').toLowerCase()
    : 'auto';

export const resolveLegoSectionSurface = (configured = 'auto', resolvedTheme = 'white') => {
    const mode = normalizeSurface(configured);
    if (mode !== 'auto') return mode;
    const theme = String(resolvedTheme || 'white').toLowerCase();
    if (theme === 'primary') return 'primary';
    if (theme === 'surface' || theme === 'surface_alt') return 'surface';
    if (theme === 'slate' || theme === 'dark' || theme === 'neutral_dark') return 'slate';
    return 'white';
};

export const legoSurfaceVars = (surface, palette = {}) => {
    const resolved = resolveLegoSectionSurface(surface, 'white');
    const primary = palette.primary || '#243447';
    const white = palette.white || '#FFFFFF';
    const surfaceBg = palette.surface || '#F8FAFC';
    const dark = palette.dark || '#0F172A';
    const heading = palette.heading || palette.on_surface || palette.onSurface || '#0F172A';
    const body = palette.body || palette.text || palette.on_surface || palette.onSurface || '#334155';
    const muted = palette.muted || '#64748B';
    const onPrimary = palette.on_primary || palette.onPrimary || '#FFFFFF';
    const onDark = palette.on_dark || palette.onDark || '#F8FAFC';
    const buttonPrimary = palette.button_primary || palette.buttonPrimary || primary;
    const buttonText = palette.button_text || palette.buttonText || onPrimary;
    const border = palette.border || '#E2E8F0';

    if (resolved === 'primary') return {
        '--cosmic-lego-section-bg': primary,
        '--cosmic-lego-heading': onPrimary,
        '--cosmic-lego-body': `color-mix(in srgb, ${onPrimary} 82%, transparent)`,
        '--cosmic-lego-muted': `color-mix(in srgb, ${onPrimary} 64%, transparent)`,
        '--cosmic-lego-icon': onPrimary,
        '--cosmic-lego-card-bg': `color-mix(in srgb, ${primary} 84%, ${white} 16%)`,
        '--cosmic-lego-card-heading': onPrimary,
        '--cosmic-lego-card-text': `color-mix(in srgb, ${onPrimary} 80%, transparent)`,
        '--cosmic-lego-border': `color-mix(in srgb, ${onPrimary} 22%, transparent)`,
        '--cosmic-lego-button-bg': white,
        '--cosmic-lego-button-text': primary,
    };

    if (resolved === 'slate') return {
        '--cosmic-lego-section-bg': dark,
        '--cosmic-lego-heading': onDark,
        '--cosmic-lego-body': `color-mix(in srgb, ${onDark} 82%, transparent)`,
        '--cosmic-lego-muted': `color-mix(in srgb, ${onDark} 64%, transparent)`,
        '--cosmic-lego-icon': palette.accent || primary,
        '--cosmic-lego-card-bg': `color-mix(in srgb, ${dark} 82%, ${white} 18%)`,
        '--cosmic-lego-card-heading': onDark,
        '--cosmic-lego-card-text': `color-mix(in srgb, ${onDark} 80%, transparent)`,
        '--cosmic-lego-border': `color-mix(in srgb, ${onDark} 20%, transparent)`,
        '--cosmic-lego-button-bg': buttonPrimary,
        '--cosmic-lego-button-text': buttonText,
    };

    if (resolved === 'surface') return {
        '--cosmic-lego-section-bg': surfaceBg,
        '--cosmic-lego-heading': heading,
        '--cosmic-lego-body': body,
        '--cosmic-lego-muted': muted,
        '--cosmic-lego-icon': primary,
        '--cosmic-lego-card-bg': white,
        '--cosmic-lego-card-heading': heading,
        '--cosmic-lego-card-text': body,
        '--cosmic-lego-border': border,
        '--cosmic-lego-button-bg': buttonPrimary,
        '--cosmic-lego-button-text': buttonText,
    };

    return {
        '--cosmic-lego-section-bg': white,
        '--cosmic-lego-heading': heading,
        '--cosmic-lego-body': body,
        '--cosmic-lego-muted': muted,
        '--cosmic-lego-icon': primary,
        '--cosmic-lego-card-bg': surfaceBg,
        '--cosmic-lego-card-heading': heading,
        '--cosmic-lego-card-text': body,
        '--cosmic-lego-border': border,
        '--cosmic-lego-button-bg': buttonPrimary,
        '--cosmic-lego-button-text': buttonText,
    };
};
