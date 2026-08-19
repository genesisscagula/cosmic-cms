import { colorFamilies } from "./colorFamilies";

const LIGHT_THEME_KEYS = new Set(["white", "stone", "light", "soft", "sky", "cream"]);

export function getSectionBackgroundClass(themeKey, treatment = "subtle") {
    const key = colorFamilies[themeKey] ? themeKey : "midnight";
    if (LIGHT_THEME_KEYS.has(key)) return colorFamilies[key]?.bg || colorFamilies.white.bg;
    const mode = treatment === "deep" ? "deep" : "subtle";
    return `${colorFamilies[key]?.bg || colorFamilies.midnight.bg} cosmic-theme-gradient cosmic-theme-gradient--${key} cosmic-theme-gradient--${mode}`;
}

function withPremiumSectionBackground(family, key, treatment = "subtle") {
    if (!family || LIGHT_THEME_KEYS.has(key)) return family;
    return { ...family, bg: getSectionBackgroundClass(key, treatment), solidBg: family.bg, themeKey: key };
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
        return colorFamilies.white;
    }

    // Surface section
    if (theme === "surface") {
        return colorFamilies.stone;
    }

    // Accent (for now use the site's primary color)
    if (theme === "accent") {
        return withPremiumSectionBackground(colorFamilies[primary] || colorFamilies.midnight, primary);
    }

    // If theme is already a real color family
    if (theme && colorFamilies[theme]) {
        return withPremiumSectionBackground(colorFamilies[theme], theme);
    }

    // Fallback
    return withPremiumSectionBackground(colorFamilies[primary] || colorFamilies.midnight, primary);

}
