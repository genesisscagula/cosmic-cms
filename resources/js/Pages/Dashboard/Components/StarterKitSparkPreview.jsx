import themeMetadata from "../../Websites/Theme/ThemeMetadata";
import { BlockRegistry as SparkPreviewRegistry } from "../../Websites/Components/SparkRegistry";
import { BlockRegistry as BuilderBlockRegistry } from "../../Websites/BlockRegistry";

const sparkLabels = {
    hero_background_image: "Image Hero",
    hero_split_image: "Split Hero",
    hero_parallax: "Parallax Hero",
    hero_editorial_overlay: "Editorial Hero",
    hero_floating_cards: "Floating Hero",
    hero_split_editorial: "Split Editorial",
    hero_luxury_fullscreen: "Luxury Hero",
    hero_bento_premium: "Bento Hero",
    feature_image_left: "Story Left",
    feature_image_right: "Story Right",
    services_cards: "Service Cards",
    services_bento: "Services Bento",
    process_timeline: "Process",
    testimonials_carousel: "Testimonials",
    stats_modern: "Stats",
    hero_centered_cta: "Centered CTA",
    image_cta_banner: "Image CTA",
};

const sparkPreviewRegistry = new Map(
    SparkPreviewRegistry.map((item) => [item.type, item]),
);

const starterKitPreviewCycle = ["primary", "white", "surface", "white", "primary", "surface"];

function starterKitThemeFamily(template) {
    return template?.themeFamily
        || template?.theme_family
        || template?.themeId
        || "midnight";
}

export function ActualStarterKitSpark({ type, index, template }) {
    const registry = sparkPreviewRegistry.get(type);
    const Preview = registry?.preview;

    if (!Preview) return null;

    return (
        <Preview
            {...(registry.payload || {})}
            previewVariant={starterKitPreviewCycle[index % starterKitPreviewCycle.length]}
            websiteTheme={starterKitThemeFamily(template)}
        />
    );
}


function cloneValue(value) {
    if (typeof structuredClone === "function") return structuredClone(value);
    return JSON.parse(JSON.stringify(value));
}

function ActualStarterKitSection({ type, index, template }) {
    const builderEntry = BuilderBlockRegistry[type];
    const Component = builderEntry?.component;
    const previewEntry = sparkPreviewRegistry.get(type);

    if (!Component) {
        return <ActualStarterKitSpark type={type} index={index} template={template} />;
    }

    const resolvedTheme = starterKitPreviewCycle[index % starterKitPreviewCycle.length];
    const block = {
        ...(cloneValue(builderEntry?.schema?.defaults || {})),
        ...(cloneValue(previewEntry?.payload || {})),
        type,
        theme: resolvedTheme,
        resolvedTheme,
    };

    return (
        <Component
            block={block}
            blockIndex={index}
            globalTheme={{
                primary: starterKitThemeFamily(template),
                secondary: "white",
                tertiary: "surface",
                auto: true,
            }}
            onUpdate={() => {}}
            blogPosts={[]}
            blogWebsiteId={null}
            blogPageId={null}
            onBlogPostCreated={() => {}}
            onBlogPostUpdated={() => {}}
            onBlogPostDeleted={() => {}}
        />
    );
}


function sparkKind(type = "") {
    if (type.startsWith("hero_")) return "hero";
    if (type.startsWith("feature_")) return "feature";
    if (type.startsWith("services_")) return "services";
    if (type === "process_timeline") return "process";
    if (type === "testimonials_carousel") return "testimonials";
    if (type === "stats_modern") return "stats";
    if (type === "image_cta_banner") return "cta-image";
    return "section";
}

function MiniSpark({ type, primary, surface, text, compact = false }) {
    const kind = sparkKind(type);
    const base = compact ? "h-full" : "h-16";

    if (kind === "hero") {
        const split = type.includes("split");
        const bento = type.includes("bento") || type.includes("floating");
        return (
            <div className={`${compact ? "h-full" : "h-24"} overflow-hidden rounded-md border border-white/10 p-1.5`} style={{ background: `linear-gradient(135deg, ${primary}, ${surface})` }}>
                {split ? <div className="grid h-full grid-cols-2 gap-1"><div className="space-y-1"><div className="h-1 w-2/3 rounded bg-white/75"/><div className="h-1.5 w-5/6 rounded bg-white/90"/><div className="h-1 w-full rounded bg-white/35"/></div><div className="rounded bg-white/15"/></div>
                    : bento ? <div className="grid h-full grid-cols-3 gap-1"><div className="col-span-2 space-y-1"><div className="h-1 w-1/2 rounded bg-white/70"/><div className="h-1.5 w-4/5 rounded bg-white/90"/></div><div className="rounded bg-white/15"/><div className="rounded bg-white/10"/><div className="col-span-2 rounded bg-white/10"/></div>
                    : <div className="flex h-full flex-col justify-center"><div className="h-1 w-1/3 rounded bg-white/65"/><div className="mt-1 h-1.5 w-3/5 rounded bg-white/90"/><div className="mt-1 h-1 w-4/5 rounded bg-white/35"/></div>}
            </div>
        );
    }

    if (kind === "feature") return <div className={`${base} grid grid-cols-2 gap-1 rounded-md border border-white/10 bg-white/[0.035] p-1`}><div className={`${type.endsWith("right") ? "order-2" : ""} rounded bg-white/10`}/><div className="flex flex-col justify-center gap-1"><span className="h-1 w-3/4 rounded bg-white/25"/><span className="h-1 w-full rounded bg-white/10"/><span className="h-1 w-4/5 rounded bg-white/10"/></div></div>;
    if (kind === "services") return <div className={`${base} grid grid-cols-3 gap-1 rounded-md border border-white/10 bg-white/[0.025] p-1`}>{[0,1,2].map((i)=><span key={i} className={`rounded ${type.includes("bento") && i===0 ? "col-span-2" : ""}`} style={{ backgroundColor: `${primary}28` }}/>)}</div>;
    if (kind === "process") return <div className={`${base} flex items-center gap-1 rounded-md border border-white/10 bg-white/[0.025] px-1`}>{[0,1,2,3].map((i)=><span key={i} className="flex-1 border-t border-dashed border-white/20"><i className="-mt-1 block h-2 w-2 rounded-full bg-white/35"/></span>)}</div>;
    if (kind === "testimonials") return <div className={`${base} grid grid-cols-3 gap-1 rounded-md border border-white/10 bg-white/[0.025] p-1`}>{[0,1,2].map((i)=><span key={i} className="rounded border border-white/10 bg-white/[0.04] p-1"><i className="block h-1 w-full rounded bg-white/10"/><i className="mt-1 block h-1 w-2/3 rounded bg-white/10"/></span>)}</div>;
    if (kind === "stats") return <div className={`${base} grid grid-cols-4 gap-1 rounded-md border border-white/10 bg-white/[0.025] p-1`}>{[0,1,2,3].map((i)=><span key={i} className="flex flex-col items-center justify-center rounded bg-white/[0.04]"><i className="h-1.5 w-1/2 rounded" style={{ backgroundColor: `${text}55` }}/><i className="mt-1 h-1 w-2/3 rounded bg-white/10"/></span>)}</div>;
    return <div className={`${base} rounded-md border border-white/10 p-1.5`} style={{ background: kind === "cta-image" ? `linear-gradient(90deg, ${surface}, ${primary})` : "rgba(255,255,255,.025)" }}><div className="mx-auto h-1 w-1/2 rounded bg-white/55"/><div className="mx-auto mt-1 h-1 w-2/3 rounded bg-white/15"/></div>;
}

export default function StarterKitSparkPreview({ template, compact = false, showLabels = false }) {
    const fixedThemeFamily = starterKitThemeFamily(template);
    const theme = themeMetadata.find((item) => item.id === fixedThemeFamily) || themeMetadata.find((item) => item.id === "midnight") || themeMetadata[0];
    const [primary, surface, text] = theme?.colors || ["#243447", "#30475E", "#F8FAFC"];
    const sparks = template.previewSparks?.length ? template.previewSparks : ["hero_background_image", "feature_image_left", "services_bento", "testimonials_carousel", "hero_centered_cta"];

    if (compact) {
        const visibleSparks = sparks.slice(0, 7);
        const scale = visibleSparks.length >= 7 ? 0.145 : visibleSparks.length >= 6 ? 0.16 : 0.18;
        const virtualWidth = `${100 / scale}%`;

        return (
            <div
                className="h-full min-h-0 overflow-hidden rounded-lg bg-white"
                aria-label={`${template.name || "Starter kit"} actual Spark composition preview`}
            >
                <div
                    className="origin-top-left"
                    style={{
                        width: virtualWidth,
                        transform: `scale(${scale})`,
                    }}
                >
                    {visibleSparks.map((type, index) => (
                        <div key={`${type}-${index}`} className="w-full overflow-hidden">
                            <ActualStarterKitSpark
                                type={type}
                                index={index}
                                template={template}
                            />
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div className="w-full">
            {sparks.map((type, index) => (
                <section key={`${type}-${index}`} className="w-full overflow-hidden">
                    {showLabels && (
                        <div className="flex items-center justify-between border-y border-slate-200 bg-white px-4 py-2 text-[9px] font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                            <span>{sparkLabels[type] || type.replaceAll("_", " ")}</span>
                            <span>{String(index + 1).padStart(2, "0")}</span>
                        </div>
                    )}
                    <div className="pointer-events-none w-full">
                        <ActualStarterKitSection type={type} index={index} template={template} />
                    </div>
                </section>
            ))}
        </div>
    );
}

export function starterKitCompositionLabels(template, limit = 3) {
    const sparks = template?.previewSparks || [];
    return sparks.slice(0, limit).map((type) => sparkLabels[type] || String(type).replaceAll("_", " "));
}

export { sparkLabels };
