import { usePage } from "@inertiajs/react";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { RepeatableControls, RepeatableRemoveButton } from "../Shared/RepeatableControls";
import { getEffectiveTheme } from "../../../../theme/Theme";

const STUDY_PRESETS = [
    { category: "Strategy", title: "A clearer digital path", summary: "A focused engagement that turned a complex challenge into a practical, confident next step.", result: "Built for measurable progress", image_url: "/storage/cms-images/background/background-1.avif", link_label: "View case study" },
    { category: "Design", title: "An experience made simpler", summary: "A thoughtful redesign that made important information easier to find and act on.", result: "Clarity at every step", image_url: "/storage/cms-images/background/background-2.avif", link_label: "View case study" },
    { category: "Growth", title: "A stronger launch foundation", summary: "A collaborative website project shaped around the customer journey and real business goals.", result: "Ready to grow with confidence", image_url: "/storage/cms-images/background/background-3.avif", link_label: "View case study" },
    { category: "Technology", title: "Systems that support scale", summary: "A flexible digital foundation designed to keep the team moving as needs evolve.", result: "Built for what comes next", image_url: "/storage/cms-images/background/background-5.avif", link_label: "View case study" },
];

export const CaseStudiesGridSchema = {
    type: "case_studies_grid",
    title: "Case Studies Grid",
    category: "Case studies",
    purpose: "Show selected client work in a polished, image-led collection.",
    description: "Present three focused examples with clear outcomes and editable details.",
    tags: ["case studies", "portfolio", "work", "results"],
    defaults: {
        eyebrow: "Selected work",
        heading: "Results that make the difference.",
        text: "A closer look at practical work shaped around clear goals, strong collaboration, and useful outcomes.",
        studies: STUDY_PRESETS.slice(0, 3),
    },
};

export function CaseStudiesGridBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme, globalTheme);
    const { website } = usePage().props;
    const data = {
        ...CaseStudiesGridSchema.defaults,
        ...block,
        studies: Array.isArray(block.studies) && block.studies.length ? block.studies : CaseStudiesGridSchema.defaults.studies,
    };
    const updateStudy = (index, field, value) => onUpdate({
        studies: data.studies.map((study, studyIndex) => studyIndex === index ? { ...study, [field]: value } : study),
    });
    const addStudy = () => onUpdate({ studies: [...data.studies, { ...STUDY_PRESETS[data.studies.length % STUDY_PRESETS.length] }] });
    const removeStudy = (index) => {
        if (data.studies.length > 1) onUpdate({ studies: data.studies.filter((_, studyIndex) => studyIndex !== index) });
    };

    return (
        <section className={`group/repeatable-section px-6 py-16 sm:px-8 lg:py-20 ${theme.bg} transition-colors duration-500`}>
            <div className="mx-auto max-w-7xl">
                <div className="mb-10 max-w-2xl space-y-4 sm:mb-12">
                    {data.eyebrow && <EditableText value={data.eyebrow} className={`block text-xs font-semibold uppercase tracking-[0.22em] ${theme.sub}`} onSave={(eyebrow) => onUpdate({ eyebrow })} />}
                    <EditableText value={data.heading} cosmicType="h2" className={`block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${theme.text}`} onSave={(heading) => onUpdate({ heading })} />
                    {data.text && <EditableText value={data.text} isTextArea className={`block max-w-xl text-base leading-7 ${theme.sub}`} onSave={(text) => onUpdate({ text })} />}
                </div>
                <div className="grid gap-5 lg:grid-cols-2">
                    {data.studies.map((study, index) => (
                        <article key={index} className={`group relative overflow-hidden rounded-3xl border ${theme.border} ${theme.card} ${index === 0 ? "lg:col-span-2 lg:grid lg:grid-cols-2" : ""}`}>
                            <EditableImage websiteId={website?.id} blockIndex={blockIndex} src={study.image_url || STUDY_PRESETS[index % STUDY_PRESETS.length].image_url} className={`w-full ${index === 0 ? "min-h-[18rem] lg:h-full" : "aspect-[16/10]"}`} onSave={(image_url) => updateStudy(index, "image_url", image_url)} />
                            <div className={`flex flex-col justify-center p-6 sm:p-8 ${index === 0 ? "lg:p-10" : ""}`}>
                                <EditableText value={study.category} className={`block text-xs font-semibold uppercase tracking-[0.22em] ${theme.sub}`} onSave={(category) => updateStudy(index, "category", category)} />
                                <EditableText value={study.title} className={`mt-4 block text-2xl font-bold leading-tight tracking-tight ${theme.text}`} onSave={(title) => updateStudy(index, "title", title)} />
                                <EditableText value={study.summary} isTextArea className={`mt-4 block text-sm leading-6 ${theme.sub}`} onSave={(summary) => updateStudy(index, "summary", summary)} />
                                <EditableText value={study.result} className={`mt-6 block text-sm font-semibold ${theme.text}`} onSave={(result) => updateStudy(index, "result", result)} />
                                <EditableText value={study.link_label} className={`mt-4 block text-sm font-semibold ${theme.text}`} onSave={(link_label) => updateStudy(index, "link_label", link_label)} />
                            </div>
                            <RepeatableRemoveButton hoverScope="card" overlay label="Remove case study" disabled={data.studies.length <= 1} onRemove={() => removeStudy(index)} />
                        </article>
                    ))}
                </div>
                <RepeatableControls onAdd={addStudy} showRemove={false} addLabel="Add case study" hoverScope="section" />
            </div>
        </section>
    );
}
