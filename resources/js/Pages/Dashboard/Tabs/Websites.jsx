export default function Websites({ websites = [] }) {
    return (
        <section>
            <p className="text-sm font-medium text-violet-300">Workspace</p>
            <div className="mt-2 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight text-white">Websites</h1>
                    <p className="mt-2 text-sm text-slate-400">Manage the sites in your Cosmic workspace.</p>
                </div>
                <button type="button" className="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-200">New website</button>
            </div>
            <div className="mt-8 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                <p className="text-sm text-slate-400">{websites.length ? `${websites.length} website${websites.length === 1 ? "" : "s"} available.` : "Your websites will appear here."}</p>
            </div>
        </section>
    );
}
