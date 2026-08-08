import { colorFamilies } from "./colorFamilies";

const LIGHT_MEDIA_THEMES = new Set(["white", "surface", "stone"]);
const NEUTRAL_DARK_THEMES = new Set([
    "midnight", "obsidian", "void", "charcoal", "asphalt", "navy", "slate", "dark",
]);
const SLATE_950 = "#020617";

function normalizeHex(hex) {
    if (typeof hex !== "string") return null;
    const value = hex.trim();
    if (/^#[0-9a-f]{6}$/i.test(value)) return value;
    if (/^#[0-9a-f]{3}$/i.test(value)) {
        return `#${value[1]}${value[1]}${value[2]}${value[2]}${value[3]}${value[3]}`;
    }
    return null;
}

function blendHex(primaryHex, neutralHex = SLATE_950, primaryWeight = 0.72) {
    const primary = normalizeHex(primaryHex) || "#243447";
    const neutral = normalizeHex(neutralHex) || SLATE_950;
    const weight = Math.max(0, Math.min(1, Number(primaryWeight) || 0));

    const channel = (hex, offset) => parseInt(hex.slice(offset, offset + 2), 16);
    const mixed = [1, 3, 5].map((offset) => Math.round(
        channel(primary, offset) * weight + channel(neutral, offset) * (1 - weight)
    ));

    return `#${mixed.map((value) => value.toString(16).padStart(2, "0")).join("")}`;
}

export function resolveMediaOverlay(globalTheme, resolvedTheme) {
    const normalizedGlobalTheme = typeof globalTheme === "string"
        ? { primary: globalTheme }
        : (globalTheme || {});
    const sectionTheme = resolvedTheme || "primary";
    const isLight = LIGHT_MEDIA_THEMES.has(sectionTheme);

    if (isLight) {
        return {
            isLight: true,
            themeKey: sectionTheme,
            overlayColor: "#ffffff",
            primaryWeight: 1,
        };
    }

    const themeKey = colorFamilies[sectionTheme]
        ? sectionTheme
        : (normalizedGlobalTheme.primary || "midnight");
    const family = colorFamilies[themeKey] || colorFamilies.midnight;
    const primaryHex = family?.palette?.background || "#243447";
    const primaryWeight = NEUTRAL_DARK_THEMES.has(themeKey) ? 0.35 : 0.72;

    return {
        isLight: false,
        themeKey,
        overlayColor: blendHex(primaryHex, SLATE_950, primaryWeight),
        primaryWeight,
    };
}

export function effectiveMediaOverlayOpacity(configuredOpacity, { isLight, lightMinimum = 88 } = {}) {
    const configured = Math.max(0, Math.min(100, Number(configuredOpacity) || 0));
    if (isLight) return Math.max(lightMinimum, configured);
    return Math.max(32, Math.min(56, Math.round(configured * 0.72)));
}
