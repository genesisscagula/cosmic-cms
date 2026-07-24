import { Link } from "@inertiajs/react";

const formatLastEdited = (value) => { if (!value) return "Not yet edited"; const date = new Date(value); return Number.isNaN(date.getTime()) ? "Recently updated" : `Edited ${date.toLocaleDateString(undefined, { month: "short", day: "numeric" })}`; };

export default function PageListRow({ page }) {
    const status = page.status === "published" || page.status === "Published" ? "Published" : "Draft";
    return <article className="flex flex-col gap-3 px-4 py-3.5 transition hover:bg-white/[0.04] sm:flex-row sm:items-center"><div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h3 className="truncate text-sm font-semibold text-white">{page.title || "Untitled Page"}</h3><span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${status === "Published" ? "bg-emerald-400/10 text-emerald-300" : "bg-amber-300/10 text-amber-200"}`}>{status}</span></div><div className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500"><span>/{page.slug || "untitled"}</span><span>{formatLastEdited(page.updated_at)}</span></div></div><Link href={route("pages.builder", page.id)} className="inline-flex h-8 shrink-0 items-center justify-center rounded-lg bg-white px-3 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">Open Builder</Link></article>;
}
