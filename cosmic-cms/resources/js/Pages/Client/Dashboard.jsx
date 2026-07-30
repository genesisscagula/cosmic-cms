import { Head, Link, router } from '@inertiajs/react';

export default function ClientDashboard({ websites = [] }) {
    const logout = () => router.post(route('logout'));

    return (
        <div className="min-h-screen bg-[#0a0a0b] text-slate-100">
            <Head title="My Websites" />

            <header className="border-b border-white/10 bg-[#111113]">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-cyan-400 font-black text-slate-950">C</span>
                        <div>
                            <p className="font-semibold text-white">CosmicReact</p>
                            <p className="text-xs text-slate-500">Client workspace</p>
                        </div>
                    </div>
                    <button type="button" onClick={logout} className="rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">
                        Log out
                    </button>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-5 py-10">
                <div className="mb-8">
                    <p className="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-300">Your workspace</p>
                    <h1 className="mt-2 text-3xl font-bold text-white">My websites</h1>
                    <p className="mt-2 text-slate-400">Edit the pages and content assigned to your account.</p>
                </div>

                {websites.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-white/15 bg-white/[0.03] p-10 text-center text-slate-400">
                        No website has been assigned to this account yet.
                    </div>
                ) : (
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {websites.map((website) => (
                            <article key={website.id} className="rounded-2xl border border-white/10 bg-[#151519] p-5 shadow-xl shadow-black/20">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 className="text-lg font-semibold text-white">{website.name}</h2>
                                        <p className="mt-1 text-sm capitalize text-slate-500">{website.industry || 'Business website'}</p>
                                    </div>
                                    <span className="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-xs font-semibold text-emerald-300">Active</span>
                                </div>
                                <Link href={route('pages.index', website.id)} className="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-200">
                                    Open website
                                </Link>
                            </article>
                        ))}
                    </div>
                )}
            </main>
        </div>
    );
}
