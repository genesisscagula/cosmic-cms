import themeCatalog from '../../theme/theme-families.json';

const LEGACY_PALETTES = {
    light: { background: '#FEFEFD', surface: '#F8F8F7', accent: '#4F46E5', text: '#0F172A' },
    soft: { background: '#F7F7F5', surface: '#FFFFFF', accent: '#78716C', text: '#0F172A' },
    sky: { background: '#E0F2FE', surface: '#FFFFFF', accent: '#0284C7', text: '#0F172A' },
    cream: { background: '#FFF7ED', surface: '#FFFFFF', accent: '#C2410C', text: '#0F172A' },
    'slate-light': { background: '#475569', surface: '#64748B', accent: '#CBD5E1', text: '#FFFFFF' },
    'slate-950': { background: '#0F172A', surface: '#1E293B', accent: '#94A3B8', text: '#FFFFFF' },
};

export const normalizeHex = (value, fallback = null) => {
    const normalized = String(value || '').trim().toUpperCase();
    if (/^#[0-9A-F]{6}$/.test(normalized)) return normalized;
    if (fallback === null) return null;
    const safeFallback = String(fallback || '').trim().toUpperCase();
    return /^#[0-9A-F]{6}$/.test(safeFallback) ? safeFallback : null;
};

const rgb = (hex) => {
    const value = normalizeHex(hex, '#243447').slice(1);
    return [0, 2, 4].map((offset) => Number.parseInt(value.slice(offset, offset + 2), 16));
};

export const mixHex = (foreground, background, foregroundWeight) => {
    const front = rgb(foreground);
    const back = rgb(background);
    const weight = Math.max(0, Math.min(1, Number(foregroundWeight)));
    return `#${front.map((channel, index) => Math.round((channel * weight) + (back[index] * (1 - weight)))
        .toString(16).padStart(2, '0')).join('')}`.toUpperCase();
};

export const colorLuminance = (hex) => rgb(hex)
    .map((channel) => channel / 255)
    .map((value) => value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4)
    .reduce((sum, value, index) => sum + (value * [0.2126, 0.7152, 0.0722][index]), 0);

export const contrastRatio = (foreground, background) => {
    const values = [colorLuminance(foreground), colorLuminance(background)].sort((a, b) => b - a);
    return (values[0] + 0.05) / (values[1] + 0.05);
};

export const readableForeground = (background, preferred = null, minimumRatio = 4.5) => {
    const safeBackground = normalizeHex(background, '#FFFFFF');
    const safePreferred = normalizeHex(preferred);
    if (safePreferred && contrastRatio(safePreferred, safeBackground) >= minimumRatio) return safePreferred;
    return [safePreferred, '#FFFFFF', '#F8FAFC', '#0F172A', '#111827', '#000000']
        .filter(Boolean)
        .sort((left, right) => contrastRatio(right, safeBackground) - contrastRatio(left, safeBackground))[0] || '#0F172A';
};

const valueHex = (source, keys, fallback) => {
    for (const key of keys) {
        const value = normalizeHex(source?.[key]);
        if (value) return value;
    }
    return fallback === null ? null : normalizeHex(fallback, '#243447');
};

const ensureContrast = (foreground, background, minimumRatio = 4.5) => {
    const safeForeground = normalizeHex(foreground, '#0F172A');
    return contrastRatio(safeForeground, background) >= minimumRatio
        ? safeForeground
        : readableForeground(background, safeForeground, minimumRatio);
};

/** Browser twin of App\Services\ThemeColorResolver. */
export function resolveSemanticPalette(themeOrHex, settings = {}, fallbackTheme = 'midnight') {
    const requested = String(themeOrHex || '').trim();
    let familyKey = requested || fallbackTheme;
    let family = {};
    let source = {};
    const customHex = normalizeHex(requested);
    const families = themeCatalog.families || {};

    if (customHex) {
        familyKey = 'custom-hex';
        source = { primary: customHex, background: customHex };
    } else if (familyKey === 'my-brand') {
        const customTheme = settings?.custom_brand_theme || {};
        source = settings?.brand_palette || customTheme?.palette || {};
        const baseKey = customTheme?.base_family || settings?.base_family || fallbackTheme;
        family = families[baseKey] || families[fallbackTheme] || {};
    } else {
        family = families[familyKey] || families[fallbackTheme] || {};
        source = family?.palette || LEGACY_PALETTES[familyKey] || {};
    }

    const fallbackPalette = families[fallbackTheme]?.palette || families.midnight?.palette || {};
    // Keep the browser contract identical to ThemeColorResolver: a custom HEX
    // seeds a fresh family and must not inherit Midnight's secondary/accent.
    const raw = customHex
        ? { ...source }
        : { ...fallbackPalette, ...(family?.palette || {}), ...source };
    const rawGradient = source?.gradient || family?.gradient || {};
    const primary = valueHex(raw, ['primary', 'background', 'sourceColor', 'source_color'], '#243447');
    const brandSurface = valueHex(raw, ['secondary', 'brand_surface', 'brandSurface', 'surface'], mixHex(primary, '#FFFFFF', 0.82));
    const secondary = valueHex(raw, ['secondary'], brandSurface);
    const accent = valueHex(raw, ['accent', 'tertiary'], valueHex(rawGradient, ['glow'], secondary));
    // Preserve the old family behavior: PRIMARY stays exact and WHITE is
    // literal white. Only SURFACE and SURFACE_ALT are generated as light
    // primary-matching tints. This keeps branding cohesive without changing
    // section composition or introducing automatic dark treatments.
    const white = '#FFFFFF';
    const surface = mixHex(primary, white, 0.06);
    const surfaceAlt = mixHex(primary, white, 0.12);
    const page = white;
    const dark = valueHex(raw, ['dark'], '#0F172A');
    const onPrimary = readableForeground(primary, valueHex(raw, ['on_primary', 'onPrimary', 'button_text', 'buttonText', 'text'], null));
    const onSecondary = readableForeground(secondary, valueHex(raw, ['on_secondary', 'onSecondary', 'text'], null));
    const onAccent = readableForeground(accent, valueHex(raw, ['on_accent', 'onAccent'], null));
    const onSurface = readableForeground(surface, valueHex(raw, ['surface_text', 'surfaceText', 'body'], null));
    const heading = ensureContrast(valueHex(raw, ['heading'], primary), surface);
    const body = ensureContrast(valueHex(raw, ['body', 'surface_text', 'surfaceText'], onSurface), surface);
    const muted = ensureContrast(valueHex(raw, ['muted'], mixHex(body, surface, 0.68)), surface);
    const buttonPrimary = valueHex(raw, ['button_primary', 'buttonPrimary'], primary);
    const buttonSecondary = valueHex(raw, ['button_secondary', 'buttonSecondary'], surfaceAlt);
    const palette = {
        family: familyKey,
        source_color: valueHex(raw, ['source_color', 'sourceColor'], primary),
        primary,
        primary_hover: valueHex(raw, ['primary_hover', 'primaryHover', 'buttonHover'], mixHex('#000000', primary, 0.12)),
        primary_soft: valueHex(raw, ['primary_soft', 'primarySoft'], mixHex(primary, surface, 0.12)),
        secondary, accent, background: primary, brand_surface: brandSurface,
        page, surface, surface_alt: surfaceAlt, white, dark, heading, body, text: body, muted,
        border: valueHex(raw, ['border'], mixHex(body, surface, 0.18)),
        border_strong: valueHex(raw, ['border_strong', 'borderStrong'], mixHex(body, surface, 0.32)),
        on_primary: onPrimary, on_secondary: onSecondary, on_accent: onAccent, on_surface: onSurface,
        on_dark: readableForeground(dark, valueHex(raw, ['on_dark', 'onDark', 'text'], null)),
        button_primary: buttonPrimary,
        button_text: readableForeground(buttonPrimary, valueHex(raw, ['button_text', 'buttonText', 'on_primary', 'onPrimary'], null)),
        button_secondary: buttonSecondary,
        button_secondary_text: readableForeground(buttonSecondary, valueHex(raw, ['button_secondary_text', 'buttonSecondaryText', 'heading'], null)),
        success: valueHex(raw, ['success'], '#237A57'), warning: valueHex(raw, ['warning'], '#A86D22'), error: valueHex(raw, ['error'], '#B44949'),
        gradient: {
            from: valueHex(rawGradient, ['from'], mixHex('#000000', primary, 0.58)),
            via: valueHex(rawGradient, ['via'], mixHex('#000000', primary, 0.40)),
            to: valueHex(rawGradient, ['to'], mixHex(accent, primary, 0.28)),
            glow: valueHex(rawGradient, ['glow'], accent),
            angle: Math.max(0, Math.min(360, Number.parseInt(rawGradient?.angle || 120, 10))),
        },
    };

    return {
        ...palette,
        sourceColor: palette.source_color, primaryHover: palette.primary_hover, primarySoft: palette.primary_soft,
        brandSurface: palette.brand_surface, surfaceMuted: palette.surface_alt, surfaceText: palette.body,
        borderStrong: palette.border_strong, onPrimary: palette.on_primary, onSecondary: palette.on_secondary,
        onAccent: palette.on_accent, onSurface: palette.on_surface, onDark: palette.on_dark,
        buttonPrimary: palette.button_primary, buttonText: palette.button_text,
        buttonSecondary: palette.button_secondary, buttonSecondaryText: palette.button_secondary_text,
    };
}
