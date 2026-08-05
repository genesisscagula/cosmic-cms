import { colorFamilies } from "./colorFamilies";

export function getEffectiveTheme(theme, globalTheme) {
    const normalizedGlobalTheme = typeof globalTheme === 'string'
        ? { primary: globalTheme }
        : (globalTheme || {});
    const primary = normalizedGlobalTheme.primary || 'midnight';

    // Block wants the site's primary color
    if (theme === "primary") {
        return colorFamilies[primary] || colorFamilies.midnight;
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
        return colorFamilies[primary] || colorFamilies.midnight;
    }

    // If theme is already a real color family
    if (theme && colorFamilies[theme]) {
        return colorFamilies[theme];
    }

    // Fallback
    return colorFamilies[primary] || colorFamilies.midnight;

}
