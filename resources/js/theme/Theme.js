import { colorFamilies } from "./colorFamilies";

const LIGHT_THEME_KEYS = new Set(["white", "stone", "light", "soft", "sky", "cream"]);

export function getSectionBackgroundClass(themeKey, treatment = "subtle") {
    const key = colorFamilies[themeKey] ? themeKey : "midnight";
    if (LIGHT_THEME_KEYS.has(key)) return colorFamilies[key]?.bg || colorFamilies.white.bg;
    const mode = treatment === "deep" ? "deep" : "subtle";
    return `${colorFamilies[key]?.bg || colorFamilies.midnight.bg} cosmic-theme-gradient cosmic-theme-gradient--${key} cosmic-theme-gradient--${mode}`;
}

function withPremiumSectionBackground(family, key, treatment = "subtle") {
    if (!family) return family;
    // The effective theme contract must stay surface-neutral: Builder, preview,
    // export and live all receive the family's exact background. Sparks that are
    // intentionally gradient-based opt in through getSectionBackgroundClass().
    return { ...family, solidBg: family.bg, themeKey: key };
}

function withSemanticTreatment(family, key, backgroundClass, foreground = "surface") {
    const onColor = foreground === "accent" ? "accent" : "surface";
    return {
        ...family,
        bg: backgroundClass,
        solidBg: backgroundClass,
        card: foreground === "accent" ? "cosmic-bg-accent-soft" : "cosmic-bg-surface",
        surface: foreground === "accent" ? "cosmic-bg-accent-soft" : "cosmic-bg-surface",
        text: `cosmic-text-on-${onColor}`,
        sub: `cosmic-text-on-${onColor}-muted`,
        border: `cosmic-border-on-${onColor}`,
        themeKey: key,
    };
}


export function getEffectiveTheme(theme, globalTheme) {
    const normalizedGlobalTheme = typeof globalTheme === 'string'
        ? { primary: globalTheme }
        : (globalTheme || {});
    const primary = normalizedGlobalTheme.primary || 'midnight';

    // Block wants the site's primary color
    if (theme === "primary") {
        return withPremiumSectionBackground(colorFamilies[primary] || colorFamilies.midnight, primary);
    }

    // White section
    if (theme === "white") {
        return withSemanticTreatment(colorFamilies.white, "white", "cosmic-bg-white");
    }

    // Surface section
    if (theme === "surface") {
        return withSemanticTreatment(colorFamilies.stone, "surface", "cosmic-bg-surface");
    }

    // Accent is a first-class semantic treatment, not a second spelling of primary.
    if (theme === "accent") {
        return withSemanticTreatment(colorFamilies[primary] || colorFamilies.midnight, "accent", "cosmic-bg-accent", "accent");
    }

    // If theme is already a real color family
    if (theme && colorFamilies[theme]) {
        return withPremiumSectionBackground(colorFamilies[theme], theme);
    }

    // Fallback
    return withPremiumSectionBackground(colorFamilies[primary] || colorFamilies.midnight, primary);

}

/**
 * Resolve a section's outer foreground separately from the foreground used by
 * nested surface cards. A PRIMARY section needs on-primary copy, while its
 * white/surface cards still need on-surface copy. Keeping both roles explicit
 * prevents family text tokens from being reused on the wrong background.
 */
export function getSectionSurfaceThemes(theme, globalTheme) {
    const section = getEffectiveTheme(theme, globalTheme);

    if (theme !== "primary") {
        return { section, surface: section, isPrimary: false };
    }

    return {
        isPrimary: true,
        section: {
            ...section,
            text: "text-white",
            sub: "text-white/75",
            border: "border-white/20",
        },
        surface: getEffectiveTheme("white", globalTheme),
    };
}
