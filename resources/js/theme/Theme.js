import { colorFamilies } from "./colorFamilies";


export function getEffectiveTheme(theme, globalTheme) {

    // If block has its own theme
    if (theme && colorFamilies[theme]) {
        return colorFamilies[theme];
    }

    // If builder passes global theme object directly
    if (globalTheme && typeof globalTheme === "object" && globalTheme.bg) {
        return globalTheme;
    }

    // If builder passes theme key
    if (typeof globalTheme === "string" && colorFamilies[globalTheme]) {
        return colorFamilies[globalTheme];
    }

    // Default theme
    return colorFamilies.dark || Object.values(colorFamilies)[0];

}