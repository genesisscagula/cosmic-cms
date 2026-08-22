const clamp = (value, min, max, fallback) => {
    const number = Number(value);
    return Number.isFinite(number) ? Math.max(min, Math.min(max, number)) : fallback;
};

export const cosmicBackgroundVars = (family = {}, settings = {}) => {
    const palette = family?.palette || {};
    const gradient = palette?.gradient || family?.gradient || {};
    const angle = clamp(settings.gradient_angle ?? gradient.angle, 0, 360, 135);

    return {
        "--cosmic-bg-surface": String(settings.surface || "#FFFFFF"),
        "--cosmic-bg-primary": String(settings.primary || palette.background || palette.primary || "#243447"),
        "--cosmic-bg-primary-surface": String(settings.primary_surface || palette.surface || palette.background || "#30475E"),
        "--cosmic-bg-accent": String(settings.accent || palette.accent || gradient.glow || "#60A5FA"),
        "--cosmic-bg-gradient-from": String(settings.gradient_from || gradient.from || palette.background || palette.primary || "#243447"),
        "--cosmic-bg-gradient-via": String(settings.gradient_via || gradient.via || palette.surface || palette.background || "#30475E"),
        "--cosmic-bg-gradient-to": String(settings.gradient_to || gradient.to || palette.background || palette.primary || "#243447"),
        "--cosmic-bg-gradient-glow": String(settings.gradient_glow || gradient.glow || palette.accent || "#60A5FA"),
        "--cosmic-bg-gradient-angle": `${angle}deg`,
        "--cosmic-bg-overlay-light-strong": String(clamp(settings.overlay_light_strong, .35, .98, .88)),
        "--cosmic-bg-overlay-light-soft": String(clamp(settings.overlay_light_soft, .20, .95, .62)),
        "--cosmic-bg-overlay-primary-strong": String(clamp(settings.overlay_primary_strong, .35, .98, .88)),
        "--cosmic-bg-overlay-primary-soft": String(clamp(settings.overlay_primary_soft, .25, .95, .82)),
        "--cosmic-bg-overlay-cinematic-strong": String(clamp(settings.overlay_cinematic_strong, .45, .99, .92)),
        "--cosmic-bg-overlay-cinematic-soft": String(clamp(settings.overlay_cinematic_soft, .35, .98, .72)),
    };
};

export const cosmicLocalBackgroundVars = (settings = {}) => {
    const allowed = {
        overlay_light_strong: "--cosmic-local-overlay-light-strong",
        overlay_light_soft: "--cosmic-local-overlay-light-soft",
        overlay_primary_strong: "--cosmic-local-overlay-primary-strong",
        overlay_primary_soft: "--cosmic-local-overlay-primary-soft",
        overlay_cinematic_strong: "--cosmic-local-overlay-cinematic-strong",
        overlay_cinematic_soft: "--cosmic-local-overlay-cinematic-soft",
        gradient_angle: "--cosmic-local-gradient-angle",
        gradient_from: "--cosmic-local-gradient-from",
        gradient_via: "--cosmic-local-gradient-via",
        gradient_to: "--cosmic-local-gradient-to",
        gradient_glow: "--cosmic-local-gradient-glow",
    };

    return Object.fromEntries(
        Object.entries(allowed)
            .filter(([key]) => settings[key] !== undefined && settings[key] !== null && settings[key] !== "")
            .map(([key, variable]) => {
                const value = key === "gradient_angle" && Number.isFinite(Number(settings[key]))
                    ? `${clamp(settings[key], 0, 360, 135)}deg`
                    : String(settings[key]);
                return [variable, value];
            })
    );
};

export const cosmicOverlayForState = ({
    state = "light",
    cinematic = false,
    family = {},
    settings = {},
    local = {},
} = {}) => {
    const palette = family?.palette || {};
    const gradient = palette?.gradient || family?.gradient || {};

    const base = String(local.gradient_from || settings.gradient_from || gradient.from || palette.background || palette.primary || "#243447");
    const via = String(local.gradient_via || settings.gradient_via || gradient.via || palette.surface || palette.background || base);
    const glow = String(local.gradient_glow || settings.gradient_glow || gradient.glow || palette.accent || via);
    const angle = clamp(local.gradient_angle ?? settings.gradient_angle ?? gradient.angle, 0, 360, 135);

    if (String(state).toLowerCase() !== "primary") {
        const strong = clamp(local.overlay_light_strong ?? settings.overlay_light_strong, .35, .98, .88);
        const soft = clamp(local.overlay_light_soft ?? settings.overlay_light_soft, .20, .95, .62);
        return `linear-gradient(${angle}deg, rgba(255,255,255,${strong}), rgba(255,255,255,${soft}))`;
    }

    if (cinematic) {
        const strong = clamp(local.overlay_cinematic_strong ?? settings.overlay_cinematic_strong, .45, .99, .92);
        const soft = clamp(local.overlay_cinematic_soft ?? settings.overlay_cinematic_soft, .35, .98, .72);
        return `linear-gradient(${angle}deg, color-mix(in srgb, ${base} ${Math.round(strong*100)}%, #020617), color-mix(in srgb, ${via} ${Math.round(soft*100)}%, ${glow}))`;
    }

    const strong = clamp(local.overlay_primary_strong ?? settings.overlay_primary_strong, .35, .98, .88);
    const soft = clamp(local.overlay_primary_soft ?? settings.overlay_primary_soft, .25, .95, .82);
    return `linear-gradient(${angle}deg, color-mix(in srgb, ${base} ${Math.round(strong*100)}%, #020617), color-mix(in srgb, ${via} ${Math.round(soft*100)}%, ${glow}))`;
};
