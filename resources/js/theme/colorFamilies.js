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
        sourceColor: safeHex(palette.sourceColor || palette.source_color, safeHex(palette.primary, '#243447')),
        primary: safeHex(palette.primary, safeHex(palette.background, '#243447')),
        primaryHover: safeHex(palette.primaryHover || palette.primary_hover, safeHex(palette.primary, '#243447')),
        primarySoft: safeHex(palette.primarySoft || palette.primary_soft, '#E2E8F0'),
        secondary: safeHex(palette.secondary, '#475569'),
        accent: safeHex(palette.accent, '#60A5FA'),
        background: safeHex(palette.background, safeHex(palette.primary, '#243447')),
        surface: safeHex(palette.surface, '#FFFFFF'),
        surfaceMuted: safeHex(palette.surfaceMuted || palette.surface_muted || palette.surface_alt, '#F1F5F9'),
        heading: safeHex(palette.heading, safeHex(palette.primary, '#243447')),
        text: safeHex(palette.text || palette.body, '#0F172A'),
        muted: safeHex(palette.muted, '#64748B'),
        surfaceText: safeHex(palette.surfaceText || palette.surface_text || palette.text, '#0F172A'),
        buttonPrimary: safeHex(palette.buttonPrimary || palette.button_primary, safeHex(palette.primary, '#243447')),
        buttonText: safeHex(palette.buttonText || palette.button_text || palette.onPrimary || palette.on_primary, '#FFFFFF'),
        buttonSecondary: safeHex(palette.buttonSecondary || palette.button_secondary, '#E2E8F0'),
        buttonSecondaryText: safeHex(palette.buttonSecondaryText || palette.button_secondary_text, safeHex(palette.primary, '#243447')),
        success: safeHex(palette.success, '#237A57'),
        warning: safeHex(palette.warning, '#A86D22'),
        error: safeHex(palette.error, '#B44949'),
        onPrimary: safeHex(palette.onPrimary || palette.on_primary || palette.buttonText || palette.button_text, '#FFFFFF'),
        onDark: safeHex(palette.onDark || palette.on_dark, '#FFFFFF'),
        border: safeHex(palette.border, '#CBD5E1'),
        gradient: (() => {
            const nested = palette.gradient && typeof palette.gradient === 'object' ? palette.gradient : {};
            const glow = safeHex(nested.glow || palette.gradient_glow, safeHex(palette.accent, base.gradient?.glow || '#7C3AED'));
            return {
                from: safeHex(nested.from || palette.gradient_from, base.gradient?.from || '#071426'),
                via: safeHex(nested.via || palette.gradient_via, base.gradient?.via || '#111936'),
                to: safeHex(nested.to || palette.gradient_to, base.gradient?.to || '#28164D'),
                glow,
                // Keep generated/custom brand gradients inside the final brand palette.
                // Inheriting these alpha colors from the base family could leak a
                // violet/green glow after Luna generated a completely different accent.
                glowSoft: hexToRgba(glow, 0.20, base.gradient?.glowSoft || 'rgba(124, 58, 237, 0.20)'),
                glowStrong: hexToRgba(glow, 0.38, base.gradient?.glowStrong || 'rgba(124, 58, 237, 0.38)'),
                angle: Number(nested.angle || palette.gradient_angle || base.gradient?.angle || 120),
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
        root.style.setProperty('--cosmic-brand-primary', normalized.primary);
        root.style.setProperty('--cosmic-brand-secondary', normalized.secondary);
        root.style.setProperty('--cosmic-color-heading', normalized.primary);
        root.style.setProperty('--cosmic-color-body', normalized.surfaceText);
        root.style.setProperty('--cosmic-color-muted', normalized.muted);
        root.style.setProperty('--cosmic-color-border', normalized.border);
        root.style.setProperty('--cosmic-color-surface', normalized.surface);
        root.style.setProperty('--cosmic-color-page', normalized.background);
        root.style.setProperty('--cosmic-color-on-primary', normalized.onPrimary);
        root.style.setProperty('--cosmic-color-on-dark', normalized.onDark);
        root.style.setProperty('--cosmic-color-h1', normalized.heading);
        root.style.setProperty('--cosmic-color-h2', normalized.heading);
        root.style.setProperty('--cosmic-color-h3', normalized.heading);
        root.style.setProperty('--cosmic-color-h4', normalized.heading);
        root.style.setProperty('--cosmic-color-h5', normalized.heading);
        root.style.setProperty('--cosmic-color-h6', normalized.heading);
        root.style.setProperty('--cosmic-color-success', normalized.success);
        root.style.setProperty('--cosmic-color-warning', normalized.warning);
        root.style.setProperty('--cosmic-color-error', normalized.error);
        root.style.setProperty('--cosmic-button-primary-bg', normalized.buttonPrimary);
        root.style.setProperty('--cosmic-button-primary-text', normalized.buttonText);
        root.style.setProperty('--cosmic-button-secondary-bg', normalized.buttonSecondary);
        root.style.setProperty('--cosmic-button-secondary-text', normalized.buttonSecondaryText);
        root.style.setProperty('--cosmic-link-color', normalized.primary);
        root.style.setProperty('--cosmic-link-hover', normalized.primaryHover);

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
