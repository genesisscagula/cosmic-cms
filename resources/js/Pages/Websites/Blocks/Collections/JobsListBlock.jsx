import { EditableText } from "../Shared/EditableText";
import { RepeatableControls, RepeatableRemoveButton } from "../Shared/RepeatableControls";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { sparkTw } from "../Shared/sparkTailwindRuntime";

const JOB_PRESETS = [
    { title: "Senior designer", type: "Full-time", location: "New York, NY", description: "Help shape thoughtful digital experiences for ambitious teams and their customers.", button_label: "View role" },
    { title: "Project manager", type: "Full-time", location: "Remote", description: "Keep client work organized, moving clearly, and grounded in practical next steps.", button_label: "View role" },
    { title: "Growth strategist", type: "Flexible", location: "Hybrid", description: "Turn research, insight, and collaboration into clear opportunities for clients.", button_label: "View role" },
    { title: "Client partner", type: "Full-time", location: "London, UK", description: "Build trusted relationships and make every stage of delivery feel well supported.", button_label: "View role" },
];

export const JobsListSchema = {
    type: "jobs_list", title: "Jobs List", category: "Careers", purpose: "Share open roles in a polished, easy-to-scan list.",
    description: "Present current opportunities with editable details and actions.", tags: ["careers", "jobs", "hiring", "team"],
    defaults: { eyebrow: "Join our team", heading: "Do work that moves things forward.", text: "We are looking for thoughtful people who care about good work, clear communication, and shared progress.", jobs: JOB_PRESETS },
};

export function JobsListBlock({ block, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme, globalTheme);
    const data = { ...JobsListSchema.defaults, ...block, jobs: Array.isArray(block.jobs) && block.jobs.length ? block.jobs : JobsListSchema.defaults.jobs };
    const updateJob = (index, field, value) => onUpdate({ jobs: data.jobs.map((job, jobIndex) => jobIndex === index ? { ...job, [field]: value } : job) });
    const addJob = () => onUpdate({ jobs: [...data.jobs, { ...JOB_PRESETS[data.jobs.length % JOB_PRESETS.length] }] });
    const removeJob = (index) => { if (data.jobs.length > 1) onUpdate({ jobs: data.jobs.filter((_, jobIndex) => jobIndex !== index) }); };
    return <section className={sparkTw(block, "b10_1", `group/repeatable-section px-6 py-16 sm:px-8 lg:py-20 ${theme.bg} transition-colors duration-500`)}><div className={sparkTw(block, "b10_2", "mx-auto max-w-7xl")}><div className={sparkTw(block, "b10_3", "mb-10 max-w-2xl space-y-4 sm:mb-12")}>{data.eyebrow && <EditableText value={data.eyebrow} className={sparkTw(block, "b10_4", `block text-xs font-semibold uppercase tracking-[0.22em] ${theme.sub}`)} onSave={(eyebrow) => onUpdate({ eyebrow })} />}<EditableText value={data.heading} cosmicType="h2" className={sparkTw(block, "b10_5", `block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${theme.text}`)} onSave={(heading) => onUpdate({ heading })} />{data.text && <EditableText value={data.text} isTextArea className={sparkTw(block, "b10_6", `block max-w-xl text-base leading-7 ${theme.sub}`)} onSave={(text) => onUpdate({ text })} />}</div><div className={sparkTw(block, "b10_7", `overflow-hidden rounded-2xl border ${theme.border} ${theme.card}`)}>{data.jobs.map((job, index) => <article key={index} className={sparkTw(block, "b10_8", `group relative grid gap-5 p-6 pr-14 sm:p-7 sm:pr-16 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center ${index > 0 ? `border-t ${theme.border}` : ""}`)}><div><div className={sparkTw(block, "b10_9", "flex flex-wrap items-center gap-3")}><EditableText value={job.title} className={sparkTw(block, "b10_10", `block text-xl font-bold ${theme.text}`)} onSave={(title) => updateJob(index, "title", title)} /><EditableText value={job.type} className={sparkTw(block, "b10_11", `block rounded-full border px-2.5 py-1 text-xs font-semibold ${theme.border} ${theme.sub}`)} onSave={(type) => updateJob(index, "type", type)} /></div><EditableText value={job.location} className={sparkTw(block, "b10_12", `mt-2 block text-sm font-medium ${theme.sub}`)} onSave={(location) => updateJob(index, "location", location)} /><EditableText value={job.description} isTextArea className={sparkTw(block, "b10_13", `mt-3 block max-w-2xl text-sm leading-6 ${theme.sub}`)} onSave={(description) => updateJob(index, "description", description)} /></div><div className={sparkTw(block, "b10_14", "flex items-center gap-4")}><EditableText value={job.button_label} className={sparkTw(block, "b10_15", `block text-sm font-semibold ${theme.text}`)} onSave={(button_label) => updateJob(index, "button_label", button_label)} /></div><RepeatableRemoveButton hoverScope="item" overlay placement="row" label="Remove role" disabled={data.jobs.length <= 1} onRemove={() => removeJob(index)} /></article>)}</div><RepeatableControls onAdd={addJob} showRemove={false} addLabel="Add role" hoverScope="section" /></div></section>;
}
