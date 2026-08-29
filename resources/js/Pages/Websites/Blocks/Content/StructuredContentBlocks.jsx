import { useEffect, useMemo, useState } from "react";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";

import { sparkTw, sparkTwPath } from "../Shared/sparkTailwindRuntime";
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
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState({});
    const types = contentWorkspace?.types || [];
    const sourceType = types.find((item) => String(item.slug) === String(block.content_type_slug)) || types[0];
    const draftType = types.find((item) => String(item.slug) === String(draft.content_type_slug)) || sourceType;
    const sourceEntries = draftType?.entries || [];
    const categories = useMemo(() => [...new Set(sourceEntries.filter((entry) => entry.status === "published").map((entry) => entry.category).filter(Boolean))], [draftType]);
    const tags = useMemo(() => [...new Set(sourceEntries.filter((entry) => entry.status === "published").flatMap((entry) => entry.tags || []).filter(Boolean))], [draftType]);
    const showColumns = ["content_grid_classic", "content_grid_editorial"].includes(block.type);

    useEffect(() => {
        setDraft({
            content_type_slug: block.content_type_slug || sourceType?.slug || "",
            category: block.category || "",
            tag: block.tag || "",
            sort: block.sort || "newest",
            limit: Number(block.limit || 6),
            columns: Number(block.columns || 3),
        });
    }, [block.content_type_slug, block.category, block.tag, block.sort, block.limit, block.columns, sourceType?.slug]);

    if (!builderMode) return null;

    const typeLabel = types.find((item) => String(item.slug) === String(block.content_type_slug))?.name || sourceType?.name || "Content";
    const sortLabels = { newest: "Newest", oldest: "Oldest", title: "Title A–Z", event_date: "Event date" };
    const summary = [typeLabel, block.category || null, block.tag ? `#${block.tag}` : null, sortLabels[block.sort || "newest"], `${Number(block.limit || 6)} ${Number(block.limit || 6) === 1 ? "item" : "items"}`].filter(Boolean);

    const resetDraft = () => setDraft({
        content_type_slug: block.content_type_slug || sourceType?.slug || "",
        category: "",
        tag: "",
        sort: block.type === "content_events_grid" ? "event_date" : "newest",
        limit: block.type === "content_featured_entry" ? 1 : 6,
        columns: 3,
    });

    const applyDraft = () => {
        onUpdate({
            content_type_slug: draft.content_type_slug,
            category: draft.category || "",
            tag: draft.tag || "",
            sort: draft.sort || "newest",
            limit: Math.min(24, Math.max(1, Number(draft.limit || 1))),
            ...(showColumns ? { columns: Number(draft.columns || 3) } : {}),
        });
        setOpen(false);
    };

    return <div id="cosmic-content-filter-controls" className="cosmic-content-filter-controls mb-8">
        <div className="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white/95 p-2.5 shadow-sm backdrop-blur">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                className="cosmic-content-filter-trigger inline-flex min-h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
            >
                <span aria-hidden="true">⚙</span>
                Edit filters
                <span className={`text-[10px] transition-transform ${open ? "rotate-180" : ""}`} aria-hidden="true">⌄</span>
            </button>
            <div className="flex min-w-0 flex-1 flex-wrap items-center gap-1.5 px-1">
                {summary.map((item, index) => <span key={`${item}-${index}`} className="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">{item}</span>)}
            </div>
        </div>

        {open ? <div className="cosmic-content-filter-panel mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10">
            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                <div>
                    <div className="text-sm font-bold text-slate-900">Content filters</div>
                    <p className="mt-0.5 text-xs leading-5 text-slate-500">Choose what this dynamic Spark should show. Changes apply only when you click Apply filters.</p>
                </div>
                <button type="button" onClick={() => setOpen(false)} className="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">Close</button>
            </div>
            <div className={`grid gap-4 p-5 sm:grid-cols-2 ${showColumns ? "xl:grid-cols-3" : "xl:grid-cols-5"}`}>
                <label className="text-[11px] font-bold uppercase tracking-[.12em] text-slate-600">Content type
                    <select value={draft.content_type_slug || ""} onChange={(e)=>setDraft((current)=>({...current,content_type_slug:e.target.value,category:"",tag:""}))} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold normal-case tracking-normal text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10">{types.map((type)=><option key={type.id} value={type.slug}>{type.name}</option>)}</select>
                </label>
                <label className="text-[11px] font-bold uppercase tracking-[.12em] text-slate-600">Category
                    <select value={draft.category || ""} onChange={(e)=>setDraft((current)=>({...current,category:e.target.value}))} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold normal-case tracking-normal text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"><option value="">All categories</option>{categories.map((value)=><option key={value} value={value}>{value}</option>)}</select>
                </label>
                <label className="text-[11px] font-bold uppercase tracking-[.12em] text-slate-600">Tag
                    <select value={draft.tag || ""} onChange={(e)=>setDraft((current)=>({...current,tag:e.target.value}))} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold normal-case tracking-normal text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"><option value="">All tags</option>{tags.map((value)=><option key={value} value={value}>{value}</option>)}</select>
                </label>
                <label className="text-[11px] font-bold uppercase tracking-[.12em] text-slate-600">Sort
                    <select value={draft.sort || "newest"} onChange={(e)=>setDraft((current)=>({...current,sort:e.target.value}))} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold normal-case tracking-normal text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"><option value="newest">Newest</option><option value="oldest">Oldest</option><option value="title">Title A–Z</option>{block.type === "content_events_grid" ? <option value="event_date">Event date</option> : null}</select>
                </label>
                <label className="text-[11px] font-bold uppercase tracking-[.12em] text-slate-600">Items
                    <input type="number" min="1" max="24" value={draft.limit || 1} onChange={(e)=>setDraft((current)=>({...current,limit:Math.min(24,Math.max(1,Number(e.target.value || 1)))}))} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold normal-case tracking-normal text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"/>
                </label>
                {showColumns ? <label className="text-[11px] font-bold uppercase tracking-[.12em] text-slate-600">Columns
                    <select value={draft.columns || 3} onChange={(e)=>setDraft((current)=>({...current,columns:Number(e.target.value)}))} className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold normal-case tracking-normal text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"><option value={1}>1 column</option><option value={2}>2 columns</option><option value={3}>3 columns</option><option value={4}>4 columns</option></select>
                </label> : null}
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/70 px-5 py-3.5">
                <button type="button" onClick={resetDraft} className="rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-white hover:text-slate-900">Reset filters</button>
                <div className="flex items-center gap-2">
                    <button type="button" onClick={() => setOpen(false)} className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</button>
                    <button type="button" onClick={applyDraft} className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">Apply filters</button>
                </div>
            </div>
        </div> : null}
    </div>;
}
const entryUrl = (type, entry) => entry?.url || `/${type?.slug || "updates"}/${entry.slug || ""}`;
const img = (entry) => entry.featured_image_url || "/storage/cms-images/background/background-1.avif";

function Intro({ data, theme, onUpdate, block }) {
    return <div className={sparkTw(block, "element_1", "mb-9 max-w-3xl")}><EditableText value={data.eyebrow} className={sparkTw(block, "eyebrow_2", `block text-xs font-bold uppercase tracking-[.24em] ${theme.sub}`)} onSave={(eyebrow)=>onUpdate({eyebrow})}/><EditableText value={data.heading} cosmicType="h2" className={sparkTw(block, "heading_3", `mt-3 block text-3xl font-bold tracking-tight sm:text-4xl ${theme.text}`)} onSave={(heading)=>onUpdate({heading})}/><EditableText value={data.text} isTextArea className={sparkTw(block, "element_4", `mt-3 block text-base leading-7 ${theme.sub}`)} onSave={(text)=>onUpdate({text})}/></div>;
}

function Classic({ type, entries, theme, columns=3, block }) { const gridClass = columns === 1 ? "grid gap-5" : columns === 2 ? "grid gap-5 md:grid-cols-2" : columns === 4 ? "grid gap-5 sm:grid-cols-2 xl:grid-cols-4" : "grid gap-5 sm:grid-cols-2 lg:grid-cols-3"; return <div className={sparkTw(block, "grid", gridClass)}>{entries.map((entry,index)=><article key={entry.id} className={sparkTwPath(block, ["entries", index], "card", `overflow-hidden rounded-2xl border shadow-sm ${theme.card} ${theme.border}`)}><a href={entryUrl(type,entry)}><img src={img(entry)} alt={entry.title} className={sparkTwPath(block, ["entries", index], "image", "h-52 w-full object-cover")}/></a><div className={sparkTwPath(block, ["entries", index], "content", "p-5")}><p className={sparkTwPath(block, ["entries", index], "eyebrow", `text-[11px] font-bold uppercase tracking-[.2em] ${theme.sub}`)}>{entry.category || type?.singular_name || "Update"}</p><h3 className={sparkTwPath(block, ["entries", index], "title", `mt-3 text-xl font-bold ${theme.text}`)}><a href={entryUrl(type,entry)}>{entry.title}</a></h3><p className={sparkTwPath(block, ["entries", index], "desc", `mt-3 line-clamp-3 text-sm leading-6 ${theme.sub}`)}>{entry.excerpt}</p><a href={entryUrl(type,entry)} className={sparkTwPath(block, ["entries", index], "cta", `mt-5 inline-flex text-sm font-bold ${theme.text}`)}>Read more →</a></div></article>)}</div>; }
function Editorial({ type, entries, theme, block }) { const [lead,...rest]=entries; if(!lead) return null; return <div className={sparkTw(block, "grid_13", "grid gap-6 lg:grid-cols-2")}><article className={sparkTw(block, "card_14", `overflow-hidden rounded-3xl border ${theme.card} ${theme.border}`)}><img src={img(lead)} alt={lead.title} className={sparkTw(block, "image_15", "h-72 w-full object-cover sm:h-96")}/><div className={sparkTw(block, "element_16", "p-7")}><p className={sparkTw(block, "eyebrow_17", `text-xs font-bold uppercase tracking-[.2em] ${theme.sub}`)}>{lead.category || type?.name}</p><h3 className={sparkTw(block, "title_18", `mt-3 text-3xl font-bold ${theme.text}`)}>{lead.title}</h3><p className={sparkTw(block, "element_19", `mt-4 leading-7 ${theme.sub}`)}>{lead.excerpt}</p><a href={entryUrl(type,lead)} className={sparkTw(block, "title_20", `mt-6 inline-flex font-bold ${theme.text}`)}>Explore story →</a></div></article><div className={sparkTw(block, "grid_21", "grid gap-4")}>{rest.slice(0,4).map((entry)=><article key={entry.id} className={sparkTw(block, "grid_22", `grid grid-cols-[120px_1fr] gap-4 overflow-hidden rounded-2xl border p-3 ${theme.card} ${theme.border}`)}><img src={img(entry)} alt="" className={sparkTw(block, "image_23", "h-full min-h-28 w-full rounded-xl object-cover")}/><div className={sparkTw(block, "element_24", "py-2")}><p className={sparkTw(block, "eyebrow_25", `text-[10px] font-bold uppercase tracking-[.18em] ${theme.sub}`)}>{entry.category || type?.name}</p><h3 className={sparkTw(block, "title_26", `mt-2 text-lg font-bold ${theme.text}`)}>{entry.title}</h3><a href={entryUrl(type,entry)} className={sparkTw(block, "title_27", `mt-3 inline-flex text-sm font-bold ${theme.text}`)}>Read →</a></div></article>)}</div></div>; }
function Compact({ type, entries, theme, block }) { return <div className={sparkTw(block, "card_28", `divide-y rounded-2xl border ${theme.card} ${theme.border}`)}>{entries.map((entry,index)=><article key={entry.id} className={sparkTw(block, "grid_29", "grid gap-4 p-5 sm:grid-cols-[96px_1fr_auto] sm:items-center")}><img src={img(entry)} alt="" className={sparkTw(block, "image_30", "h-20 w-24 rounded-xl object-cover")}/><div><p className={sparkTw(block, "eyebrow_31", `text-[10px] font-bold uppercase tracking-[.18em] ${theme.sub}`)}>{entry.category || type?.name}</p><h3 className={sparkTw(block, "title_32", `mt-1 text-lg font-bold ${theme.text}`)}>{entry.title}</h3><p className={sparkTw(block, "element_33", `mt-1 line-clamp-1 text-sm ${theme.sub}`)}>{entry.excerpt}</p></div><a href={entryUrl(type,entry)} className={sparkTw(block, "title_34", `text-sm font-bold ${theme.text}`)}>View →</a></article>)}</div>; }
function EventCards({ type, entries, theme, block }) { return <div className={sparkTw(block, "grid_35", "grid gap-5 md:grid-cols-2 lg:grid-cols-3")}>{entries.map((entry)=>{ const date=entry.custom_fields?.start_date ? new Date(entry.custom_fields.start_date) : null; return <article key={entry.id} className={sparkTw(block, "card_36", `rounded-2xl border p-6 ${theme.card} ${theme.border}`)}><div className={sparkTw(block, "row_37", "flex gap-4")}><div className={sparkTw(block, "card_38", `min-w-16 rounded-xl border p-3 text-center ${theme.border}`)}><div className={sparkTw(block, "title_39", `text-xs font-bold uppercase ${theme.sub}`)}>{date ? date.toLocaleString(undefined,{month:"short"}) : "EVENT"}</div><div className={sparkTw(block, "title_40", `text-2xl font-bold ${theme.text}`)}>{date ? date.getDate() : "—"}</div></div><div><p className={sparkTw(block, "eyebrow_41", `text-xs font-bold uppercase tracking-[.18em] ${theme.sub}`)}>{entry.custom_fields?.venue || "Upcoming event"}</p><h3 className={sparkTw(block, "title_42", `mt-2 text-xl font-bold ${theme.text}`)}>{entry.title}</h3></div></div><p className={sparkTw(block, "element_43", `mt-4 text-sm leading-6 ${theme.sub}`)}>{entry.excerpt}</p><div className={sparkTw(block, "element_44", `mt-4 text-sm ${theme.sub}`)}>{entry.custom_fields?.time || ""}{entry.custom_fields?.address ? ` · ${entry.custom_fields.address}` : ""}</div><a href={entry.custom_fields?.registration_url || entryUrl(type,entry)} className={sparkTw(block, "title_45", `mt-5 inline-flex font-bold ${theme.text}`)}>{entry.custom_fields?.registration_url ? "Register →" : "View event →"}</a></article>})}</div>; }

export function StructuredContentBlock({ block, onUpdate=()=>{}, globalTheme, contentWorkspace, builderMode=false }) {
    const schema=schemas[block.type] || ContentGridClassicSchema;
    const data={...schema.defaults,...block};
    const selectedTheme=data.theme && data.theme !== "auto" ? data.theme : (data.resolvedTheme || "primary");
    const theme=getEffectiveTheme(selectedTheme, globalTheme);
    const {type,entries}=filteredEntries(data,contentWorkspace);
    const empty=<div className={sparkTw(block, "card_46", `rounded-2xl border border-dashed p-8 text-center ${theme.border} ${theme.sub}`)}>No published {type?.name?.toLowerCase() || "entries"} match this Spark yet.</div>;
    let body=empty;
    if(entries.length){
        if(data.type==="content_grid_editorial") body=<Editorial type={type} entries={entries} theme={theme} block={block}/>;
        else if(data.type==="content_grid_compact" || data.type==="content_latest_entries") body=<Compact type={type} entries={entries} theme={theme} block={block}/>;
        else if(data.type==="content_featured_entry") body=<Editorial type={type} entries={entries.slice(0,1)} theme={theme} block={block}/>;
        else if(data.type==="content_events_grid") body=<EventCards type={type} entries={entries} theme={theme} block={block}/>;
        else body=<Classic type={type} entries={entries} theme={theme} columns={Number(data.columns || 3)} block={block}/>;
    }
    return <section className={sparkTw(block, "section_47", `px-6 py-16 sm:px-8 lg:px-12 lg:py-20 ${theme.bg}`)}><div className={sparkTw(block, "wrapper_48", "mx-auto max-w-7xl")}><SourceControls block={data} contentWorkspace={contentWorkspace} onUpdate={onUpdate} builderMode={builderMode}/><Intro data={data} theme={theme} onUpdate={onUpdate} block={block}/>{body}</div></section>;
}

export const ContentGridClassicBlock=StructuredContentBlock;
export const ContentGridEditorialBlock=StructuredContentBlock;
export const ContentGridCompactBlock=StructuredContentBlock;
export const ContentFeaturedBlock=StructuredContentBlock;
export const ContentLatestBlock=StructuredContentBlock;
export const ContentEventsBlock=StructuredContentBlock;
