import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";

const common = {
    eyebrow: "LATEST STORIES",
    heading: "Fresh from our updates",
    text: "Explore the latest articles, events, projects, and updates.",
    content_type_slug: "blog",
    category: "",
    tag: "",
    sort: "newest",
    limit: 6,
    columns: 3,
};

export const ContentGridClassicSchema = { type:"content_grid_classic", title:"Content Loop · Grid", category:"Posts / Updates", purpose:"A clean dynamic grid for any structured content type.", defaults:{...common} };
export const ContentGridEditorialSchema = { type:"content_grid_editorial", title:"Content Loop · Editorial", category:"Posts / Updates", purpose:"An image-led editorial layout for stories, projects, and news.", defaults:{...common, heading:"Stories worth exploring", limit:5} };
export const ContentGridCompactSchema = { type:"content_grid_compact", title:"Content Loop · List", category:"Posts / Updates", purpose:"A compact list for larger archives and update feeds.", defaults:{...common, heading:"Latest updates", limit:8} };
export const ContentFeaturedSchema = { type:"content_featured_entry", title:"Featured Post", category:"Posts / Updates", purpose:"Highlight one featured or newest entry from a selected content type.", defaults:{...common, heading:"Featured story", limit:1, featured_only:true} };
export const ContentLatestSchema = { type:"content_latest_entries", title:"Latest Posts", category:"Posts / Updates", purpose:"Show the newest entries from a selected content type.", defaults:{...common, heading:"Latest posts", limit:4} };
export const ContentEventsSchema = { type:"content_events_grid", title:"Upcoming Events Grid", category:"Posts / Updates", purpose:"Show upcoming Events entries with dates, venue, and registration links.", defaults:{...common, content_type_slug:"events", eyebrow:"UPCOMING EVENTS", heading:"What’s coming up", sort:"event_date", limit:6, upcoming_only:true} };

const schemas = {
    content_grid_classic: ContentGridClassicSchema,
    content_grid_editorial: ContentGridEditorialSchema,
    content_grid_compact: ContentGridCompactSchema,
    content_featured_entry: ContentFeaturedSchema,
    content_latest_entries: ContentLatestSchema,
    content_events_grid: ContentEventsSchema,
};

const typeEntries = (contentWorkspace, slug) => {
    const types = contentWorkspace?.types || [];
    const type = types.find((item) => String(item.slug) === String(slug)) || types[0];
    return { type, entries: (type?.entries || []).filter((entry) => entry.status === "published") };
};

const parseDate = (value) => value ? new Date(value).getTime() : 0;
const filteredEntries = (block, contentWorkspace) => {
    const { type, entries } = typeEntries(contentWorkspace, block.content_type_slug);
    let result = [...entries];
    if (block.category) result = result.filter((entry) => String(entry.category || "") === String(block.category));
    if (block.tag) result = result.filter((entry) => (entry.tags || []).map(String).includes(String(block.tag)));
    if (block.featured_only) {
        const featured = result.filter((entry) => entry.is_featured);
        if (featured.length) result = featured;
    }
    if (block.upcoming_only || block.type === "content_events_grid") {
        const today = new Date(); today.setHours(0,0,0,0);
        result = result.filter((entry) => !entry.custom_fields?.start_date || parseDate(entry.custom_fields.start_date) >= today.getTime());
    }
    if (block.sort === "oldest") result.sort((a,b)=>parseDate(a.published_at)-parseDate(b.published_at));
    else if (block.sort === "title") result.sort((a,b)=>String(a.title).localeCompare(String(b.title)));
    else if (block.sort === "event_date") result.sort((a,b)=>parseDate(a.custom_fields?.start_date)-parseDate(b.custom_fields?.start_date));
    else result.sort((a,b)=>parseDate(b.published_at || b.updated_at)-parseDate(a.published_at || a.updated_at));
    return { type, entries: result.slice(0, Math.max(1, Number(block.limit || 6))) };
};

function SourceControls({ block, contentWorkspace, onUpdate, builderMode }) {
    if (!builderMode) return null;
    const types = contentWorkspace?.types || [];
    const { entries } = typeEntries(contentWorkspace, block.content_type_slug);
    const categories = [...new Set(entries.map((e)=>e.category).filter(Boolean))];
    const tags = [...new Set(entries.flatMap((e)=>e.tags || []).filter(Boolean))];
    const showColumns = ["content_grid_classic", "content_grid_editorial"].includes(block.type);
    return <div className="mb-5 grid gap-3 rounded-2xl border border-slate-300/30 bg-black/10 p-4 md:grid-cols-3 xl:grid-cols-6">
        <label className="text-xs font-bold">Content type<select value={block.content_type_slug || ""} onChange={(e)=>onUpdate({content_type_slug:e.target.value,category:"",tag:""})} className="mt-1 w-full rounded-lg border border-slate-400/30 bg-white px-3 py-2 text-slate-900">{types.map((t)=><option key={t.id} value={t.slug}>{t.name}</option>)}</select></label>
        <label className="text-xs font-bold">Category<select value={block.category || ""} onChange={(e)=>onUpdate({category:e.target.value})} className="mt-1 w-full rounded-lg border border-slate-400/30 bg-white px-3 py-2 text-slate-900"><option value="">All categories</option>{categories.map((v)=><option key={v}>{v}</option>)}</select></label>
        <label className="text-xs font-bold">Tag<select value={block.tag || ""} onChange={(e)=>onUpdate({tag:e.target.value})} className="mt-1 w-full rounded-lg border border-slate-400/30 bg-white px-3 py-2 text-slate-900"><option value="">All tags</option>{tags.map((v)=><option key={v}>{v}</option>)}</select></label>
        <label className="text-xs font-bold">Sort<select value={block.sort || "newest"} onChange={(e)=>onUpdate({sort:e.target.value})} className="mt-1 w-full rounded-lg border border-slate-400/30 bg-white px-3 py-2 text-slate-900"><option value="newest">Newest</option><option value="oldest">Oldest</option><option value="title">Title A–Z</option>{block.type === "content_events_grid" ? <option value="event_date">Event date</option> : null}</select></label>
        <label className="text-xs font-bold">Items<input type="number" min="1" max="24" value={block.limit || 6} onChange={(e)=>onUpdate({limit:Math.min(24,Math.max(1,Number(e.target.value || 1)))})} className="mt-1 w-full rounded-lg border border-slate-400/30 bg-white px-3 py-2 text-slate-900"/></label>
        {showColumns ? <label className="text-xs font-bold">Columns<select value={block.columns || 3} onChange={(e)=>onUpdate({columns:Number(e.target.value)})} className="mt-1 w-full rounded-lg border border-slate-400/30 bg-white px-3 py-2 text-slate-900"><option value={1}>1 column</option><option value={2}>2 columns</option><option value={3}>3 columns</option><option value={4}>4 columns</option></select></label> : <div/>}
    </div>;
}

const entryUrl = (type, entry) => entry?.url || `/${type?.slug || "updates"}/${entry.slug || ""}`;
const img = (entry) => entry.featured_image_url || "/storage/cms-images/background/background-1.avif";

function Intro({ data, theme, onUpdate }) {
    return <div className="mb-9 max-w-3xl"><EditableText value={data.eyebrow} className={`block text-xs font-bold uppercase tracking-[.24em] ${theme.sub}`} onSave={(eyebrow)=>onUpdate({eyebrow})}/><EditableText value={data.heading} className={`mt-3 block text-3xl font-bold tracking-tight sm:text-4xl ${theme.text}`} onSave={(heading)=>onUpdate({heading})}/><EditableText value={data.text} isTextArea className={`mt-3 block text-base leading-7 ${theme.sub}`} onSave={(text)=>onUpdate({text})}/></div>;
}

function Classic({ type, entries, theme, columns=3 }) { const gridClass = columns === 1 ? "grid gap-5" : columns === 2 ? "grid gap-5 md:grid-cols-2" : columns === 4 ? "grid gap-5 sm:grid-cols-2 xl:grid-cols-4" : "grid gap-5 sm:grid-cols-2 lg:grid-cols-3"; return <div className={gridClass}>{entries.map((entry)=><article key={entry.id} className={`overflow-hidden rounded-2xl border shadow-sm ${theme.card} ${theme.border}`}><a href={entryUrl(type,entry)}><img src={img(entry)} alt={entry.title} className="h-52 w-full object-cover"/></a><div className="p-5"><p className={`text-[11px] font-bold uppercase tracking-[.2em] ${theme.sub}`}>{entry.category || type?.singular_name || "Update"}</p><h3 className={`mt-3 text-xl font-bold ${theme.text}`}><a href={entryUrl(type,entry)}>{entry.title}</a></h3><p className={`mt-3 line-clamp-3 text-sm leading-6 ${theme.sub}`}>{entry.excerpt}</p><a href={entryUrl(type,entry)} className={`mt-5 inline-flex text-sm font-bold ${theme.text}`}>Read more →</a></div></article>)}</div>; }
function Editorial({ type, entries, theme }) { const [lead,...rest]=entries; if(!lead) return null; return <div className="grid gap-6 lg:grid-cols-2"><article className={`overflow-hidden rounded-3xl border ${theme.card} ${theme.border}`}><img src={img(lead)} alt={lead.title} className="h-72 w-full object-cover sm:h-96"/><div className="p-7"><p className={`text-xs font-bold uppercase tracking-[.2em] ${theme.sub}`}>{lead.category || type?.name}</p><h3 className={`mt-3 text-3xl font-bold ${theme.text}`}>{lead.title}</h3><p className={`mt-4 leading-7 ${theme.sub}`}>{lead.excerpt}</p><a href={entryUrl(type,lead)} className={`mt-6 inline-flex font-bold ${theme.text}`}>Explore story →</a></div></article><div className="grid gap-4">{rest.slice(0,4).map((entry)=><article key={entry.id} className={`grid grid-cols-[120px_1fr] gap-4 overflow-hidden rounded-2xl border p-3 ${theme.card} ${theme.border}`}><img src={img(entry)} alt="" className="h-full min-h-28 w-full rounded-xl object-cover"/><div className="py-2"><p className={`text-[10px] font-bold uppercase tracking-[.18em] ${theme.sub}`}>{entry.category || type?.name}</p><h3 className={`mt-2 text-lg font-bold ${theme.text}`}>{entry.title}</h3><a href={entryUrl(type,entry)} className={`mt-3 inline-flex text-sm font-bold ${theme.text}`}>Read →</a></div></article>)}</div></div>; }
function Compact({ type, entries, theme }) { return <div className={`divide-y rounded-2xl border ${theme.card} ${theme.border}`}>{entries.map((entry)=><article key={entry.id} className="grid gap-4 p-5 sm:grid-cols-[96px_1fr_auto] sm:items-center"><img src={img(entry)} alt="" className="h-20 w-24 rounded-xl object-cover"/><div><p className={`text-[10px] font-bold uppercase tracking-[.18em] ${theme.sub}`}>{entry.category || type?.name}</p><h3 className={`mt-1 text-lg font-bold ${theme.text}`}>{entry.title}</h3><p className={`mt-1 line-clamp-1 text-sm ${theme.sub}`}>{entry.excerpt}</p></div><a href={entryUrl(type,entry)} className={`text-sm font-bold ${theme.text}`}>View →</a></article>)}</div>; }
function EventCards({ type, entries, theme }) { return <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">{entries.map((entry)=>{ const date=entry.custom_fields?.start_date ? new Date(entry.custom_fields.start_date) : null; return <article key={entry.id} className={`rounded-2xl border p-6 ${theme.card} ${theme.border}`}><div className="flex gap-4"><div className={`min-w-16 rounded-xl border p-3 text-center ${theme.border}`}><div className={`text-xs font-bold uppercase ${theme.sub}`}>{date ? date.toLocaleString(undefined,{month:"short"}) : "EVENT"}</div><div className={`text-2xl font-bold ${theme.text}`}>{date ? date.getDate() : "—"}</div></div><div><p className={`text-xs font-bold uppercase tracking-[.18em] ${theme.sub}`}>{entry.custom_fields?.venue || "Upcoming event"}</p><h3 className={`mt-2 text-xl font-bold ${theme.text}`}>{entry.title}</h3></div></div><p className={`mt-4 text-sm leading-6 ${theme.sub}`}>{entry.excerpt}</p><div className={`mt-4 text-sm ${theme.sub}`}>{entry.custom_fields?.time || ""}{entry.custom_fields?.address ? ` · ${entry.custom_fields.address}` : ""}</div><a href={entry.custom_fields?.registration_url || entryUrl(type,entry)} className={`mt-5 inline-flex font-bold ${theme.text}`}>{entry.custom_fields?.registration_url ? "Register →" : "View event →"}</a></article>})}</div>; }

export function StructuredContentBlock({ block, onUpdate=()=>{}, globalTheme, contentWorkspace, builderMode=false }) {
    const schema=schemas[block.type] || ContentGridClassicSchema;
    const data={...schema.defaults,...block};
    const theme=getEffectiveTheme(data.resolvedTheme || data.theme || "primary", globalTheme);
    const {type,entries}=filteredEntries(data,contentWorkspace);
    const empty=<div className={`rounded-2xl border border-dashed p-8 text-center ${theme.border} ${theme.sub}`}>No published {type?.name?.toLowerCase() || "entries"} match this Spark yet.</div>;
    let body=empty;
    if(entries.length){
        if(data.type==="content_grid_editorial") body=<Editorial type={type} entries={entries} theme={theme}/>;
        else if(data.type==="content_grid_compact" || data.type==="content_latest_entries") body=<Compact type={type} entries={entries} theme={theme}/>;
        else if(data.type==="content_featured_entry") body=<Editorial type={type} entries={entries.slice(0,1)} theme={theme}/>;
        else if(data.type==="content_events_grid") body=<EventCards type={type} entries={entries} theme={theme}/>;
        else body=<Classic type={type} entries={entries} theme={theme} columns={Number(data.columns || 3)}/>;
    }
    return <section className={`px-6 py-16 sm:px-8 lg:px-12 lg:py-20 ${theme.bg}`}><div className="mx-auto max-w-7xl"><SourceControls block={data} contentWorkspace={contentWorkspace} onUpdate={onUpdate} builderMode={builderMode}/><Intro data={data} theme={theme} onUpdate={onUpdate}/>{body}</div></section>;
}

export const ContentGridClassicBlock=StructuredContentBlock;
export const ContentGridEditorialBlock=StructuredContentBlock;
export const ContentGridCompactBlock=StructuredContentBlock;
export const ContentFeaturedBlock=StructuredContentBlock;
export const ContentLatestBlock=StructuredContentBlock;
export const ContentEventsBlock=StructuredContentBlock;
