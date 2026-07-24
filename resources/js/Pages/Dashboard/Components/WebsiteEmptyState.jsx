export default function WebsiteEmptyState({ query }) {
    return <div className="rounded-2xl border border-dashed border-white/15 bg-white/[0.02] px-6 py-16 text-center"><div className="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-white/10 text-lg text-slate-300" aria-hidden="true">◈</div><h2 className="mt-4 font-semibold text-white">No websites found</h2><p className="mt-2 text-sm text-slate-500">{query ? "Try a different search term." : "Create your first website to start building."}</p></div>;
}
