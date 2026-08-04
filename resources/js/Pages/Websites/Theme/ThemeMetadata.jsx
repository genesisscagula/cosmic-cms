import themeCatalog from "../../../../theme/theme-families.json";

const fallbackNames = {
    dark: "Dark Matter",
    violet: "Aurora Violet",
};

const classHex = (value, fallback) => value?.match(/#([0-9a-f]{6})/i)?.[0] || fallback;

const orderedIds = themeCatalog.themeOrder || Object.keys(themeCatalog.families);

const themeMetadata = orderedIds
    .filter((id) => themeCatalog.families[id])
    .map((id) => {
        const family = themeCatalog.families[id];
        const palette = family.palette || {};

        return {
            id,
            name: family.name || fallbackNames[id] || id.replace(/(^|-)(\w)/g, (_, prefix, letter) => `${prefix ? " " : ""}${letter.toUpperCase()}`),
            category: family.category || "Professional",
            description: family.description || "A flexible Cosmic color family.",
            featured: (themeCatalog.featuredFamilies || []).includes(id),
            colors: [
                palette.background || classHex(family.bg, "#18181b"),
                palette.surface || classHex(family.card, "#27272a"),
                palette.accent || "#a78bfa",
                palette.text || "#f8fafc",
            ],
            styles: {
                button: family.buttonStyle || "rounded",
                card: family.cardStyle || "soft",
                hero: family.heroStyle || "centered",
                cta: family.ctaStyle || "solid",
            },
        };
    });

export default themeMetadata;
