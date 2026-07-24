import { colorFamilies } from "./colorFamilies";

export function getEffectiveTheme(theme, globalTheme) {
    const primary = globalTheme?.primary || 'emerald';

    // Block wants the site's primary color
    if (theme === "primary") {
        return colorFamilies[primary] || colorFamilies.emerald;
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
        return colorFamilies[primary] || colorFamilies.emerald;
    }

    // If theme is already a real color family
    if (theme && colorFamilies[theme]) {
        return colorFamilies[theme];
    }

    // Fallback
    return colorFamilies[primary] || colorFamilies.emerald;

}
