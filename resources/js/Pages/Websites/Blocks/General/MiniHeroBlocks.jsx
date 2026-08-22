import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";
import { usePage } from "@inertiajs/react";
import { getEffectiveTheme } from "../../../../theme/Theme";

const sharedDefaults = {
    eyebrow: "Explore more",
    heading: "A focused page for what matters next",
    text: "Use a compact hero to introduce this page without taking over the whole screen.",
    button_label: "Explore",
    button_url: "#",
    image_url: "/storage/cms-images/background/background-2.avif",
    image_alt: "Featured page image",
};

export const MiniHeroMinimalSchema = {
    type: "mini_hero_minimal",
    title: "Mini Hero Minimal",
    category: "Mini Heroes",
    purpose: "A compact left-aligned hero for shop, blog, service, archive, and inner pages.",
    defaults: { ...sharedDefaults },
};

export const MiniHeroSplitSchema = {
    type: "mini_hero_split",
    title: "Mini Hero Split Image",
    category: "Mini Heroes",
    purpose: "A compact split hero with supporting image for visual inner pages and storefronts.",
    defaults: { ...sharedDefaults, eyebrow: "Discover the collection", heading: "Designed for everyday essentials" },
};

export const MiniHeroPromoSchema = {
    type: "mini_hero_promo",
    title: "Mini Hero Promo",
    category: "Mini Heroes",
    purpose: "A promotional mini hero with a premium accent card and clear call to action.",
    defaults: { ...sharedDefaults, eyebrow: "Featured now", heading: "Something worth discovering", button_label: "Shop now" },
};

function themeFor(block, globalTheme) {
    const selected = block?.theme && block.theme !== "auto" ? block.theme : (block?.resolvedTheme || "primary");
    return getEffectiveTheme(selected, globalTheme);
}

function CTA({ data, theme }) {
    if (!data.button_label) return null;
    return (
        <a
            href={data.button_url || "#"}
            className={`mt-7 inline-flex min-h-11 items-center justify-center rounded-xl border px-5 py-2.5 text-sm font-bold transition hover:-translate-y-0.5 ${theme.card} ${theme.text} ${theme.border}`}
            onClick={(event) => event.preventDefault()}
        >
            {data.button_label}
        </a>
    );
}

export function MiniHeroMinimalBlock({ block, onUpdate, globalTheme }) {
    const data = { ...MiniHeroMinimalSchema.defaults, ...block };
    const theme = themeFor(data, globalTheme);

    return (
        <section className={`relative overflow-hidden border-b px-6 py-14 sm:px-8 sm:py-16 lg:px-12 lg:py-20 ${theme.bg} ${theme.border}`}>
            <div className={`pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full opacity-10 blur-3xl ${theme.card}`} />
            <div className="relative mx-auto max-w-7xl">
                <div className="max-w-3xl">
                    <EditableText value={data.eyebrow} className={`block text-xs font-bold uppercase tracking-[0.24em] ${theme.sub}`} onSave={(eyebrow) => onUpdate({ eyebrow })} />
                    <EditableText value={data.heading} cosmicType="h2" className={`mt-3 block text-4xl font-semibold leading-[1.02] tracking-tight sm:text-5xl ${theme.text}`} onSave={(heading) => onUpdate({ heading })} />
                    <EditableText value={data.text} isTextArea className={`mt-4 block max-w-2xl text-base leading-7 ${theme.sub}`} onSave={(text) => onUpdate({ text })} />
                    <CTA data={data} theme={theme} />
                </div>
            </div>
        </section>
    );
}

export function MiniHeroSplitBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const data = { ...MiniHeroSplitSchema.defaults, ...block };
    const theme = themeFor(data, globalTheme);
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;

    return (
        <section className={`border-b px-6 py-10 sm:px-8 sm:py-12 lg:px-12 lg:py-14 ${theme.bg} ${theme.border}`}>
            <div className="mx-auto grid max-w-7xl items-center gap-8 lg:grid-cols-[1.05fr_.95fr] lg:gap-12">
                <div className="max-w-2xl">
                    <EditableText value={data.eyebrow} className={`block text-xs font-bold uppercase tracking-[0.24em] ${theme.sub}`} onSave={(eyebrow) => onUpdate({ eyebrow })} />
                    <EditableText value={data.heading} cosmicType="h2" className={`mt-3 block text-4xl font-semibold leading-[1.02] tracking-tight sm:text-5xl ${theme.text}`} onSave={(heading) => onUpdate({ heading })} />
                    <EditableText value={data.text} isTextArea className={`mt-4 block text-base leading-7 ${theme.sub}`} onSave={(text) => onUpdate({ text })} />
                    <CTA data={data} theme={theme} />
                </div>
                <div className={`overflow-hidden rounded-[1.75rem] border p-2 shadow-xl ${theme.card} ${theme.border}`}>
                    <EditableImage websiteId={websiteId} blockIndex={blockIndex} src={data.image_url} className="h-56 w-full rounded-[1.35rem] object-cover sm:h-64 lg:h-72" imageQuery={data.image_alt || data.heading} blockType={block.type} onSave={(image_url) => onUpdate({ image_url })} />
                </div>
            </div>
        </section>
    );
}

export function MiniHeroPromoBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const data = { ...MiniHeroPromoSchema.defaults, ...block };
    const theme = themeFor(data, globalTheme);
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;

    return (
        <section className={`border-b px-6 py-10 sm:px-8 lg:px-12 lg:py-12 ${theme.bg} ${theme.border}`}>
            <div className="mx-auto max-w-7xl">
                <div className={`relative overflow-hidden rounded-[2rem] border p-7 shadow-xl sm:p-9 lg:p-11 ${theme.card} ${theme.border}`}>
                    <div className="absolute inset-y-0 right-0 hidden w-[38%] lg:block">
                        <EditableImage websiteId={websiteId} blockIndex={blockIndex} src={data.image_url} showOverlay={false} isBackground className="h-full w-full object-cover opacity-20" imageQuery={data.heading} blockType={block.type} onSave={(image_url) => onUpdate({ image_url })} />
                        <div className={`absolute inset-0 bg-gradient-to-r from-transparent to-transparent`} />
                    </div>
                    <div className="relative max-w-3xl">
                        <EditableText value={data.eyebrow} className={`block text-xs font-bold uppercase tracking-[0.24em] ${theme.sub}`} onSave={(eyebrow) => onUpdate({ eyebrow })} />
                        <EditableText value={data.heading} cosmicType="h2" className={`mt-3 block text-3xl font-semibold leading-[1.04] tracking-tight sm:text-4xl lg:text-5xl ${theme.text}`} onSave={(heading) => onUpdate({ heading })} />
                        <EditableText value={data.text} isTextArea className={`mt-4 block max-w-2xl text-base leading-7 ${theme.sub}`} onSave={(text) => onUpdate({ text })} />
                        <CTA data={data} theme={theme} />
                    </div>
                </div>
            </div>
        </section>
    );
}
