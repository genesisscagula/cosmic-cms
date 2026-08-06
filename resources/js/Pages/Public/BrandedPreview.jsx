import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const viewportWidths = {
    desktop: '100%',
    tablet: '768px',
    mobile: '390px',
};

export default function BrandedPreview({ website, pages = [], branding = {}, link = {} }) {
    const firstPreviewable = pages.find((page) => page.has_preview) || pages[0] || null;
    const [selectedId, setSelectedId] = useState(firstPreviewable?.id || null);
    const [viewport, setViewport] = useState('desktop');
    const selectedPage = useMemo(
        () => pages.find((page) => page.id === selectedId) || firstPreviewable,
        [pages, selectedId, firstPreviewable],
    );

    return (
        <div className="cosmic-ui-shell min-h-screen bg-[#09090b] text-slate-100">
            <Head title={`${website.name} Preview`} />
            <header className="border-b border-white/10 bg-[#111113]">
                <div className="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-4 px-5 py-4">
                    <div className="flex min-w-0 items-center gap-3">
                        {branding.cosmic_branding_removed && (branding.logo_url || branding.agency_name) && <div className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white/10">{branding.logo_url ? <img src={branding.logo_url} alt="" className="h-full w-full object-contain p-1"/> : <span className="text-xs font-bold text-white">{String(branding.agency_name || 'A').slice(0,2).toUpperCase()}</span>}</div>}
                        <Link href={'#'} className="rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">← Websites</Link>
                        <div className="min-w-0"><p className="truncate font-semibold text-white">{website.name}</p><p className="truncate text-xs text-slate-500">{branding.cosmic_branding_removed ? `${branding.agency_name || 'Agency'} client preview` : 'Read-only client preview'}</p></div>
                    </div>
                    <div className="flex items-center gap-2">
                        {Object.keys(viewportWidths).map((key) => (
                            <button key={key} type="button" onClick={() => setViewport(key)} className={`rounded-lg px-3 py-2 text-xs font-semibold capitalize transition ${viewport === key ? 'bg-white text-slate-950' : 'border border-white/10 text-slate-400 hover:text-white'}`}>{key}</button>
                        ))}
                    </div>
                </div>
            </header>

            <main className="mx-auto grid max-w-[1600px] gap-5 px-5 py-5 lg:grid-cols-[260px_minmax(0,1fr)]">
                <aside className="h-fit rounded-2xl border border-white/10 bg-[#141418] p-4 lg:sticky lg:top-5">
                    <p className="text-xs font-bold uppercase tracking-[0.18em] text-cyan-300">Pages</p>
                    <div className="mt-3 space-y-2">
                        {pages.map((page) => (
                            <button key={page.id} type="button" onClick={() => setSelectedId(page.id)} className={`w-full rounded-xl border px-3 py-3 text-left transition ${selectedPage?.id === page.id ? 'border-violet-400/40 bg-violet-400/10' : 'border-white/5 bg-white/[0.02] hover:border-white/15'}`}>
                                <div className="flex items-center justify-between gap-2"><span className="truncate text-sm font-semibold text-white">{page.title}</span><span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${page.status === 'published' ? 'bg-emerald-400/10 text-emerald-300' : 'bg-amber-300/10 text-amber-200'}`}>{page.status}</span></div>
                                <p className="mt-1 truncate text-xs text-slate-500">/{page.slug}</p>
                            </button>
                        ))}
                        {pages.length === 0 && <p className="rounded-xl border border-dashed border-white/10 p-4 text-sm text-slate-500">No pages are available yet.</p>}
                    </div>
                    <div className="mt-5 border-t border-white/10 pt-4 text-xs leading-5 text-slate-500">This portal is view-only. Changes, publishing, and website settings remain protected.</div>
                </aside>

                <section className="min-w-0 rounded-2xl border border-white/10 bg-[#141418] p-3 sm:p-4">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3 px-1">
                        <div><h1 className="font-semibold text-white">{selectedPage?.title || 'Website preview'}</h1><p className="text-xs text-slate-500">{selectedPage ? `Updated ${selectedPage.updated_at}` : 'Select a page to preview'}</p></div>
                        <span className="rounded-full border border-cyan-300/15 bg-cyan-300/[0.06] px-3 py-1 text-xs font-semibold text-cyan-100">Sandboxed preview</span>
                    </div>
                    <div className="overflow-auto rounded-xl bg-[#202026] p-2 sm:p-4">
                        <div className="mx-auto min-h-[70vh] overflow-hidden rounded-lg bg-white shadow-2xl shadow-black/40 transition-[width] duration-300" style={{ width: viewportWidths[viewport], maxWidth: '100%' }}>
                            {selectedPage?.preview_html ? (
                                <iframe title={`${selectedPage.title} preview`} srcDoc={selectedPage.preview_html} sandbox="allow-forms allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts" className="h-[78vh] w-full border-0 bg-white" />
                            ) : (
                                <div className="flex h-[70vh] items-center justify-center p-8 text-center"><div><p className="text-lg font-semibold text-slate-900">Preview not published yet</p><p className="mt-2 max-w-md text-sm text-slate-500">This page is still a draft or has no published snapshot. Your agency will make it available after publishing.</p></div></div>
                            )}
                        </div>
                    </div>
                </section>
            </main>
            {!branding.cosmic_branding_removed && <footer className="border-t border-white/10 py-5 text-center text-xs text-slate-600">{branding.attribution_label || 'Built with Cosmic CMS'}</footer>}
        </div>
    );
}
