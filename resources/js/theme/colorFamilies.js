import themeCatalog from "../../theme/theme-families.json";

export const colorFamilies = themeCatalog.families;
export const CUSTOM_BRAND_THEME_ID = 'my-brand';

const safeHex = (value, fallback) => /^#[0-9A-Fa-f]{6}$/.test(String(value || '')) ? String(value).toUpperCase() : fallback;
const hexToRgba = (hex, alpha, fallback) => {
    const value = safeHex(hex, '');
    if (!value) return fallback;
    const int = Number.parseInt(value.slice(1), 16);
    const r = (int >> 16) & 255;
    const g = (int >> 8) & 255;
    const b = int & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

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
        surfaceText: safeHex(palette.surface_text, safeHex(palette.text, '#F8FAFC')),
        buttonText: safeHex(palette.button_text, '#FFFFFF'),
        border: safeHex(palette.border, '#475569'),
        gradient: (() => {
            const glow = safeHex(palette.gradient_glow, safeHex(palette.accent, base.gradient?.glow || '#7C3AED'));
            return {
                from: safeHex(palette.gradient_from, base.gradient?.from || '#071426'),
                via: safeHex(palette.gradient_via, base.gradient?.via || '#111936'),
                to: safeHex(palette.gradient_to, base.gradient?.to || '#28164D'),
                glow,
                // Keep generated/custom brand gradients inside the final brand palette.
                // Inheriting these alpha colors from the base family could leak a
                // violet/green glow after Luna generated a completely different accent.
                glowSoft: hexToRgba(glow, 0.20, base.gradient?.glowSoft || 'rgba(124, 58, 237, 0.20)'),
                glowStrong: hexToRgba(glow, 0.38, base.gradient?.glowStrong || 'rgba(124, 58, 237, 0.38)'),
                angle: Number(palette.gradient_angle || base.gradient?.angle || 120),
            };
        })(),
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
        gradient: normalized.gradient,
    };

    if (typeof document !== 'undefined') {
        const root = document.documentElement;
        root.style.setProperty('--cosmic-brand-bg', normalized.background);
        root.style.setProperty('--cosmic-brand-surface', normalized.surface);
        root.style.setProperty('--cosmic-brand-text', normalized.text);
        root.style.setProperty('--cosmic-brand-muted', normalized.muted);
        root.style.setProperty('--cosmic-brand-surface-text', normalized.surfaceText);
        root.style.setProperty('--cosmic-brand-button-text', normalized.buttonText);
        root.style.setProperty('--cosmic-brand-border', normalized.border);
        root.style.setProperty('--cosmic-brand-accent', normalized.accent);
        root.style.setProperty('--cosmic-gradient-from', normalized.gradient.from);
        root.style.setProperty('--cosmic-gradient-via', normalized.gradient.via);
        root.style.setProperty('--cosmic-gradient-to', normalized.gradient.to);
        root.style.setProperty('--cosmic-gradient-glow', normalized.gradient.glow);
        root.style.setProperty('--cosmic-gradient-glow-soft', normalized.gradient.glowSoft);
        root.style.setProperty('--cosmic-gradient-glow-strong', normalized.gradient.glowStrong);
        root.style.setProperty('--cosmic-gradient-angle', `${normalized.gradient.angle}deg`);
    }

    return colorFamilies[CUSTOM_BRAND_THEME_ID];
}
