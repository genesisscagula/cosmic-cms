import themeCatalog from "../../../../theme/theme-families.json";

const themeDetails = [
    ["midnight", "Midnight Blue", "Professional"],
    ["obsidian", "Obsidian Black", "Luxury"],
    ["terracotta", "Terracotta", "Warm"],
    ["asphalt", "Asphalt Grey", "Industrial"],
    ["espresso", "Espresso Brown", "Elegant"],
    ["navy", "Classic Navy", "Corporate"],
    ["void", "Void Deep Blue", "Technology"],
    ["emerald", "Emerald Forest", "Fresh"],
    ["coffee", "Coffee Bean", "Hospitality"],
    ["rose", "Rose Bloom", "Beauty"],
    ["indigo", "Royal Indigo", "Creative"],
    ["amber", "Golden Amber", "Warm"],
    ["charcoal", "Charcoal Grey", "Minimal"],
    ["violet", "Deep Violet", "Creative"],
    ["teal", "Coastal Teal", "Fresh"],
    ["ruby", "Ruby Red", "Bold"],
    ["forest", "Moss Forest", "Nature"],
    ["sapphire", "Sapphire Blue", "Professional"],
    ["plum", "Royal Plum", "Luxury"],
    ["olive", "Olive Grove", "Organic"],
    ["ocean", "Ocean Blue", "Professional"],
    ["slate", "Slate", "Technology"],
];

const colorFromClass = (value, fallback) => value?.match(/#([0-9a-f]{6})/i)?.[0] || fallback;

const themeMetadata = themeDetails.map(([id, name, category]) => {
    const family = themeCatalog.families[id];

    return {
        id,
        name,
        category,
        // Preview colors come from the same shared family that Builder and
        // CmsHtmlCompiler use, preventing a template/picker mismatch.
        colors: [
            colorFromClass(family?.bg, "#18181b"),
            colorFromClass(family?.card, "#27272a"),
            "#f8fafc",
        ],
    };
});

export default themeMetadata;
