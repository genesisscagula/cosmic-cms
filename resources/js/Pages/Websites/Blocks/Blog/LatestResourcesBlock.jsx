import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { RepeatableControls, RepeatableRemoveButton, cloneLast, removeAt } from "../Shared/RepeatableControls";

export const LatestResourcesSchema = {
    type: "latest_resources",
    title: "Latest Resources",
    category: "Content",
    sparkGroup: "Blog-Latest-Resources",
    freeSparkCount: 3,
    purpose: "Surface useful resources after a blog collection.",
    description: "A flexible editorial resource section.",
    tags: ["resources", "guides", "blog", "content"],
    defaults: {
        layout_variant: "resources-01",
        eyebrow: "Keep exploring",
        heading: "Latest resources",
        text: "Helpful next reads for visitors who want to learn more.",
        resources: [
            { eyebrow: "Guide", title: "A practical checklist for your next step", text: "A concise starting point for making a clearer, more confident decision.", cta_label: "Read the guide", cta_url: "#" },
            { eyebrow: "Resource", title: "Questions worth asking before you begin", text: "Use this focused resource to prepare for a better conversation with your team.", cta_label: "Explore resource", cta_url: "#" },
        ],
    },
};

export function LatestResourcesBlock({ block, onUpdate, globalTheme }) {
    const data = {
        ...LatestResourcesSchema.defaults,
        ...block,
        resources: Array.isArray(block.resources) && block.resources.length ? block.resources.slice(0, 6) : LatestResourcesSchema.defaults.resources,
    };
    const variant = data.layout_variant || "resources-01";
    const selectedTheme = block.theme && block.theme !== "auto" ? block.theme : (block.resolvedTheme || "white");
    const theme = getEffectiveTheme(selectedTheme, globalTheme);
    const updateResource = (index, key, value) => onUpdate({ resources: data.resources.map((resource, resourceIndex) => resourceIndex === index ? { ...resource, [key]: value } : resource) });
    const updateResourceButton = (index, cta_label, cta_url) => onUpdate({ resources: data.resources.map((resource, resourceIndex) => resourceIndex === index ? { ...resource, cta_label, cta_url } : resource) });

    const card = (resource, index, compact = false) => (
        <article key={index} className={`group relative border ${theme.border} ${theme.card} shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg ${compact ? "grid gap-4 rounded-2xl p-6 sm:grid-cols-[130px_1fr]" : "rounded-2xl p-7 sm:p-8"}`}>
            <RepeatableRemoveButton
                overlay
                onRemove={() => onUpdate({ resources: removeAt(data.resources, index, 1) })}
                disabled={data.resources.length <= 1}
                label="Remove resource"
            />
            {compact && <div className={`flex min-h-28 items-center justify-center rounded-xl ${theme.bg} text-3xl font-black ${theme.sub}`}>0{index + 1}</div>}
            <div>
                <EditableText value={resource.eyebrow} className={`block text-[11px] font-semibold uppercase tracking-[0.22em] ${theme.sub}`} onSave={(eyebrow) => updateResource(index, "eyebrow", eyebrow)} />
                <EditableText value={resource.title} className={`mt-4 block text-2xl font-bold leading-tight tracking-tight ${theme.text}`} onSave={(title) => updateResource(index, "title", title)} />
                <EditableText value={resource.text} isTextArea className={`mt-4 block text-sm leading-6 ${theme.sub}`} onSave={(text) => updateResource(index, "text", text)} />
                <EditableButton label={resource.cta_label} url={resource.cta_url} className={`mt-7 inline-flex text-sm font-semibold ${theme.text} underline underline-offset-4 transition`} onSave={(cta_label, cta_url) => updateResourceButton(index, cta_label, cta_url)} />
            </div>
        </article>
    );

    return (
        <section className={`group/repeatable-section ${theme.bg} ${theme.text} px-6 py-16 transition-colors duration-500 sm:px-8 lg:px-12 lg:py-24`}>
            <div className="mx-auto max-w-7xl">
                <div className={variant === "resources-02" ? "mx-auto max-w-3xl text-center" : variant === "resources-03" ? "grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-start" : ""}>
                    <div className="max-w-3xl">
                        <EditableText value={data.eyebrow} className={`block text-xs font-semibold uppercase tracking-[0.28em] ${theme.sub}`} onSave={(eyebrow) => onUpdate({ eyebrow })} />
                        <EditableText value={data.heading} className={`mt-4 block text-4xl font-bold leading-[1.05] tracking-tight ${theme.text} sm:text-5xl lg:text-[3.75rem]`} onSave={(heading) => onUpdate({ heading })} />
                        <EditableText value={data.text} isTextArea className={`mt-5 block max-w-2xl text-base leading-7 ${theme.sub} ${variant === "resources-02" ? "mx-auto" : ""}`} onSave={(text) => onUpdate({ text })} />
                    </div>
                    {variant === "resources-03" && <div className="grid gap-4">{data.resources.map((resource, index) => card(resource, index, true))}</div>}
                </div>
                {variant !== "resources-03" && <div className={`mt-10 grid gap-5 ${variant === "resources-02" ? "mx-auto max-w-4xl" : "md:grid-cols-2"}`}>{data.resources.map((resource, index) => card(resource, index, variant === "resources-02"))}</div>}
                <RepeatableControls
                    onAdd={() => data.resources.length < 6 && onUpdate({ resources: cloneLast(data.resources, LatestResourcesSchema.defaults.resources[0]) })}
                    onRemove={() => onUpdate({ resources: removeAt(data.resources, data.resources.length - 1, 1) })}
                    canAdd={data.resources.length < 6}
                    canRemove={data.resources.length > 1}
                    addLabel="Add resource"
                    removeLabel="Remove last resource"
                    showRemove={false}
                />
            </div>
        </section>
    );
}
