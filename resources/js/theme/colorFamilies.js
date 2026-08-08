import themeCatalog from "../../theme/theme-families.json";

export const colorFamilies = themeCatalog.families;
export const CUSTOM_BRAND_THEME_ID = 'my-brand';

const safeHex = (value, fallback) => /^#[0-9A-Fa-f]{6}$/.test(String(value || '')) ? String(value).toUpperCase() : fallback;

export function installCustomBrandTheme(customTheme) {
    if (!customTheme || typeof customTheme !== 'object') return null;

    const palette = customTheme.palette || {};
    const baseKey = customTheme.base_family && colorFamilies[customTheme.base_family]
        ? customTheme.base_family
        : 'midnight';
    const base = colorFamilies[baseKey] || colorFamilies.midnight;
    const normalized = {
        primary: safeHex(palette.primary, safeHex(palette.background, '#243447')),
        secondary: safeHex(palette.secondary, '#475569'),
        accent: safeHex(palette.accent, '#60A5FA'),
        background: safeHex(palette.background, safeHex(palette.primary, '#243447')),
        surface: safeHex(palette.surface, '#30475E'),
        text: safeHex(palette.text, '#F8FAFC'),
        muted: safeHex(palette.muted, '#CBD5E1'),
        border: safeHex(palette.border, '#475569'),
    };

    colorFamilies[CUSTOM_BRAND_THEME_ID] = {
        ...base,
        name: customTheme.name || 'My Brand Theme',
        category: 'Brand',
        description: customTheme.description || 'A custom Cosmic color family generated from your logo.',
        bg: 'cosmic-brand-bg',
        card: 'cosmic-brand-surface',
        surface: 'cosmic-brand-surface',
        text: 'cosmic-brand-text',
        sub: 'cosmic-brand-muted',
        border: 'cosmic-brand-border',
        palette: normalized,
    };

    if (typeof document !== 'undefined') {
        const root = document.documentElement;
        root.style.setProperty('--cosmic-brand-bg', normalized.background);
        root.style.setProperty('--cosmic-brand-surface', normalized.surface);
        root.style.setProperty('--cosmic-brand-text', normalized.text);
        root.style.setProperty('--cosmic-brand-muted', normalized.muted);
        root.style.setProperty('--cosmic-brand-border', normalized.border);
        root.style.setProperty('--cosmic-brand-accent', normalized.accent);
    }

    return colorFamilies[CUSTOM_BRAND_THEME_ID];
}
