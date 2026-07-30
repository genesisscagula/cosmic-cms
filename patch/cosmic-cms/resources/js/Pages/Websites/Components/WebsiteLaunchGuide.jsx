import { Link } from "@inertiajs/react";

function Step({ complete, number, title, description }) {
    return (
        <div className="flex min-w-0 gap-3">
            <span
                className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-[11px] font-bold ${
                    complete
                        ? "border-emerald-400/30 bg-emerald-400/10 text-emerald-300"
                        : "border-white/15 bg-white/[0.04] text-slate-400"
                }`}
                aria-label={complete ? `${title} complete` : `${title} not complete`}
            >
                {complete ? "✓" : number}
            </span>
            <div className="min-w-0">
                <p className={`text-sm font-medium ${complete ? "text-slate-300" : "text-white"}`}>{title}</p>
                <p className="mt-0.5 text-xs leading-5 text-slate-500">{description}</p>
            </div>
        </div>
    );
}

export default function WebsiteLaunchGuide({ pages = [], onNewPage }) {
    const hasPages = pages.length > 0;
    const hasPublishedPage = pages.some((page) => String(page.status || "").toLowerCase() === "published");
    const nextPage = pages[0];

    if (hasPublishedPage) {
        return null;
    }

    return (
        <section className="rounded-2xl border border-violet-400/20 bg-violet-400/[0.045] p-4 sm:p-5" aria-labelledby="website-launch-guide-title">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-violet-200">Getting started</p>
                    <h2 id="website-launch-guide-title" className="mt-1 text-sm font-semibold text-white">Get your first page ready to publish</h2>
                </div>
                {!hasPages ? (
                    <button type="button" onClick={onNewPage} className="inline-flex h-9 shrink-0 items-center justify-center rounded-lg bg-white px-3.5 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
                        Create first page
                    </button>
                ) : (
                    <Link href={route("pages.builder", nextPage.id)} className="inline-flex h-9 shrink-0 items-center justify-center rounded-lg bg-white px-3.5 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
                        Continue in Builder
                    </Link>
                )}
            </div>

            <div className="mt-5 grid gap-4 border-t border-white/10 pt-4 sm:grid-cols-3">
                <Step complete number="1" title="Website created" description="Your workspace is ready." />
                <Step complete={hasPages} number="2" title="Create a page" description="Add Home, About, or any page you need." />
                <Step complete={hasPublishedPage} number="3" title="Publish when ready" description="Your current live version stays safe until publishing succeeds." />
            </div>
        </section>
    );
}
