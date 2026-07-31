/**
 * Cosmic CMS v2.7.4 free Blog Sparks.
 * Patch 1 registers the designs and payload metadata. Patch 2 connects these
 * groups to the section-specific Change Layout picker.
 */
export const FREE_BLOG_SPARKS = [
    ...["mini-header-01", "mini-header-02", "mini-header-03"].map((layout_variant, index) => ({
        id: layout_variant,
        group: "Blog-Mini-Header",
        title: `Blog Mini Header ${index + 1}`,
        description: ["Clean left-aligned intro", "Centered editorial intro", "Split heading and summary"][index],
        preview: ["left", "center", "split"][index],
        isFree: true,
        payload: { type: "blog_mini_hero", theme: "primary", layout_variant },
    })),
    ...["blog-cards-01", "blog-cards-02", "blog-cards-03"].map((layout_variant, index) => ({
        id: layout_variant,
        group: "Blog-Cards",
        title: `Blog Cards ${index + 1}`,
        description: ["Balanced featured story and grid", "Editorial split with two-column cards", "Wide feature with magazine grid"][index],
        preview: ["balanced", "split", "magazine"][index],
        isFree: true,
        payload: { type: "blog_hub", theme: "editorial", show_intro: false, layout_variant },
    })),
    ...["newsletter-01", "newsletter-02", "newsletter-03"].map((layout_variant, index) => ({
        id: layout_variant,
        group: "Blog-News-Letter",
        title: `Blog Newsletter ${index + 1}`,
        description: ["Horizontal signup panel", "Centered signup card", "Split copy and form"][index],
        preview: ["horizontal", "center", "split"][index],
        isFree: true,
        payload: { type: "newsletter_cta", theme: "primary", layout_variant },
    })),
    ...["resources-01", "resources-02", "resources-03"].map((layout_variant, index) => ({
        id: layout_variant,
        group: "Blog-Latest-Resources",
        title: `Blog Latest Resources ${index + 1}`,
        description: ["Two resource cards", "Centered compact resources", "Intro with stacked resources"][index],
        preview: ["cards", "compact", "stacked"][index],
        isFree: true,
        payload: { type: "latest_resources", theme: "white", layout_variant },
    })),
];

export const BLOG_SPARK_GROUPS = Object.freeze({
    blog_mini_hero: "Blog-Mini-Header",
    blog_hub: "Blog-Cards",
    newsletter_cta: "Blog-News-Letter",
    latest_resources: "Blog-Latest-Resources",
});
