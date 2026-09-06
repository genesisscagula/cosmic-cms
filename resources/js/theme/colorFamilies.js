import themeCatalog from "../../theme/theme-families.json";
import { resolveSemanticPalette } from "./semanticPalette";

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

    const baseKey = customTheme.base_family && colorFamilies[customTheme.base_family]
        ? customTheme.base_family
        : 'midnight';
    const base = colorFamilies[baseKey] || colorFamilies.midnight;
    const semantic = resolveSemanticPalette('my-brand', {
        custom_brand_theme: { ...customTheme, base_family: baseKey },
    }, baseKey);
    const normalized = {
        ...semantic,
        sourceColor: semantic.source_color,
        primaryHover: semantic.primary_hover,
        primarySoft: semantic.primary_soft,
        brandSurface: semantic.brand_surface,
        background: semantic.primary,
        surfaceMuted: semantic.surface_alt,
        text: semantic.body,
        surfaceText: semantic.body,
        borderStrong: semantic.border_strong,
        buttonPrimary: semantic.button_primary,
        buttonText: semantic.button_text,
        buttonSecondary: semantic.button_secondary,
        buttonSecondaryText: semantic.button_secondary_text,
        onPrimary: semantic.on_primary,
        onSecondary: semantic.on_secondary,
        onAccent: semantic.on_accent,
        onSurface: semantic.on_surface,
        onDark: semantic.on_dark,
        gradient: (() => {
            const nested = semantic.gradient;
            const glow = nested.glow;
            return {
                from: nested.from,
                via: nested.via,
                to: nested.to,
                glow,
                // Keep generated/custom brand gradients inside the final brand palette.
                // Inheriting these alpha colors from the base family could leak a
                // violet/green glow after Luna generated a completely different accent.
                glowSoft: hexToRgba(glow, 0.20, base.gradient?.glowSoft || 'rgba(124, 58, 237, 0.20)'),
                glowStrong: hexToRgba(glow, 0.38, base.gradient?.glowStrong || 'rgba(124, 58, 237, 0.38)'),
                angle: Number(nested.angle || 120),
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
        root.style.setProperty('--cosmic-brand-surface', normalized.brandSurface);
        root.style.setProperty('--cosmic-bg-white', normalized.white);
        root.style.setProperty('--cosmic-bg-surface', normalized.surface);
        root.style.setProperty('--cosmic-bg-surface-alt', normalized.surfaceMuted);
        root.style.setProperty('--cosmic-bg-primary', normalized.primary);
        root.style.setProperty('--cosmic-bg-primary-surface', normalized.brandSurface);
        root.style.setProperty('--cosmic-bg-accent', normalized.accent);
        root.style.setProperty('--cosmic-color-dark', normalized.dark);
        root.style.setProperty('--cosmic-color-heading', normalized.heading);
        root.style.setProperty('--cosmic-color-body', normalized.surfaceText);
        root.style.setProperty('--cosmic-color-muted', normalized.muted);
        root.style.setProperty('--cosmic-color-border', normalized.border);
        root.style.setProperty('--cosmic-color-border-strong', normalized.borderStrong);
        root.style.setProperty('--cosmic-color-surface', normalized.surface);
        root.style.setProperty('--cosmic-color-surface-alt', normalized.surfaceMuted);
        root.style.setProperty('--cosmic-color-page', normalized.page);
        root.style.setProperty('--cosmic-color-on-primary', normalized.onPrimary);
        root.style.setProperty('--cosmic-color-on-secondary', normalized.onSecondary);
        root.style.setProperty('--cosmic-color-on-accent', normalized.onAccent);
        root.style.setProperty('--cosmic-color-on-surface', normalized.onSurface);
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
