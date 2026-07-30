import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";

export const LatestResourcesSchema = {
    type: "latest_resources",
    title: "Latest Resources",
    category: "Content",
    purpose: "Surface two useful resources after a blog collection.",
    description: "A compact two-card editorial resource section.",
    tags: ["resources", "guides", "blog", "content"],
    defaults: {
        eyebrow: "Keep exploring",
        heading: "Latest resources",
        text: "Helpful next reads for visitors who want to learn more.",
        resources: [
            { eyebrow: "Guide", title: "A practical checklist for your next step", text: "A concise starting point for making a clearer, more confident decision.", cta_label: "Read the guide", cta_url: "#" },
            { eyebrow: "Resource", title: "Questions worth asking before you begin", text: "Use this focused resource to prepare for a better conversation with your team.", cta_label: "Explore resource", cta_url: "#" },
        ],
    },
};

export function LatestResourcesBlock({ block, onUpdate }) {
    const data = {
        ...LatestResourcesSchema.defaults,
        ...block,
        resources: Array.isArray(block.resources) && block.resources.length
            ? block.resources.slice(0, 2)
            : LatestResourcesSchema.defaults.resources,
    };
    const updateResource = (index, key, value) => onUpdate({
        resources: data.resources.map((resource, resourceIndex) => resourceIndex === index ? { ...resource, [key]: value } : resource),
    });
    const updateResourceButton = (index, cta_label, cta_url) => onUpdate({
        resources: data.resources.map((resource, resourceIndex) => resourceIndex === index ? { ...resource, cta_label, cta_url } : resource),
    });

    return (
        <section className="bg-[#fcfcfb] px-6 py-16 sm:px-8 lg:px-12 lg:py-24">
            <div className="mx-auto max-w-7xl">
                <div className="max-w-3xl">
                    <EditableText value={data.eyebrow} className="block text-xs font-semibold uppercase tracking-[0.28em] text-slate-500" onSave={(eyebrow) => onUpdate({ eyebrow })} />
                    <EditableText value={data.heading} className="mt-4 block text-4xl font-bold leading-[1.05] tracking-tight text-slate-900 sm:text-5xl lg:text-[3.75rem]" onSave={(heading) => onUpdate({ heading })} />
                    <EditableText value={data.text} isTextArea className="mt-5 block max-w-2xl text-base leading-7 text-slate-600" onSave={(text) => onUpdate({ text })} />
                </div>
                <div className="mt-10 grid gap-5 md:grid-cols-2">
                    {data.resources.map((resource, index) => (
                        <article key={index} className="group rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg sm:p-8">
                            <EditableText value={resource.eyebrow} className="block text-[11px] font-semibold uppercase tracking-[0.22em] text-violet-700" onSave={(eyebrow) => updateResource(index, "eyebrow", eyebrow)} />
                            <EditableText value={resource.title} className="mt-4 block text-2xl font-bold leading-tight tracking-tight text-slate-900" onSave={(title) => updateResource(index, "title", title)} />
                            <EditableText value={resource.text} isTextArea className="mt-4 block text-sm leading-6 text-slate-600" onSave={(text) => updateResource(index, "text", text)} />
                            <EditableButton label={resource.cta_label} url={resource.cta_url} className="mt-7 inline-flex text-sm font-semibold text-slate-900 underline decoration-slate-300 underline-offset-4 transition group-hover:decoration-slate-900" onSave={(cta_label, cta_url) => updateResourceButton(index, cta_label, cta_url)} />
                        </article>
                    ))}
                </div>
            </div>
        </section>
    );
}
