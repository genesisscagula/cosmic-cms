import { router } from "@inertiajs/react";

const Detail = ({ label, value }) => (
    <div className="rounded-xl border border-white/10 bg-black/10 px-4 py-3">
        <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{label}</p>
        <p className="mt-1 truncate text-sm font-medium text-slate-100" title={value || "—"}>{value || "—"}</p>
    </div>
);

export default function WorkspaceInformation({ workspace = {}, onViewAll }) {
    const websites = Array.isArray(workspace.websites) ? workspace.websites : [];

    return (
        <section className="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.035]">
            <div className="flex flex-col gap-3 border-b border-white/10 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-base font-semibold text-white">{workspace.name || "Workspace"}</h2>
                        <span className="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-300">
                            {workspace.status || "Active"}
                        </span>
                        <span className="rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-1 text-[11px] font-medium capitalize text-slate-300">
                            {workspace.role || "owner"}
                        </span>
                    </div>
                    <p className="mt-1 text-xs text-slate-500">Workspace and website information from your account.</p>
                </div>
                <button type="button" onClick={onViewAll} className="text-xs font-semibold text-violet-300 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">
                    Manage websites
                </button>
            </div>

            <div className="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
                <Detail label="Owner" value={workspace.owner?.name} />
                <Detail label="Owner email" value={workspace.owner?.email} />
                <Detail label="Created" value={workspace.created_label || "Not available"} />
                <Detail label="Members" value={String(workspace.members_count ?? 1)} />
            </div>

            <div className="border-t border-white/10 px-5 py-4">
                <div className="mb-3 flex items-center justify-between">
                    <p className="text-sm font-semibold text-white">Websites</p>
                    <p className="text-xs text-slate-500">{workspace.published_websites_count || 0} published · {workspace.websites_count || 0} total</p>
                </div>

                {websites.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-slate-500">
                        No websites have been created in this workspace yet.
                    </div>
                ) : (
                    <div className="space-y-3">
                        {websites.map((website) => (
                            <button key={website.id} type="button" onClick={() => router.visit(route("pages.index", website.id))} className="grid w-full gap-3 rounded-xl border border-white/10 bg-black/10 px-4 py-4 text-left transition hover:border-violet-400/30 hover:bg-white/[0.04] focus:outline-none focus:ring-2 focus:ring-violet-400 sm:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_auto] sm:items-center">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="truncate text-sm font-semibold text-white">{website.name}</p>
                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${website.status === "Published" ? "bg-emerald-400/10 text-emerald-300" : "bg-amber-300/10 text-amber-200"}`}>{website.status}</span>
                                    </div>
                                    <p className="mt-1 truncate text-xs text-slate-500">{website.domain}</p>
                                </div>
                                <div className="grid grid-cols-2 gap-2 text-xs">
                                    <div><span className="block text-slate-500">Industry</span><span className="mt-1 block truncate text-slate-300">{website.industry}</span></div>
                                    <div><span className="block text-slate-500">Location</span><span className="mt-1 block truncate text-slate-300">{website.location}</span></div>
                                </div>
                                <div className="text-left text-xs sm:text-right">
                                    <p className="font-medium text-slate-300">{website.published_pages_count}/{website.pages_count} pages live</p>
                                    <p className="mt-1 text-slate-500">{website.deployment_status}</p>
                                </div>
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}
