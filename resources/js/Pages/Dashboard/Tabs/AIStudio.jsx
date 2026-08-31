const tools = [
    {
        id: "website",
        title: "Generate Website",
        description:
            "Create a complete website structure, page content, and recommended sections from one business prompt.",
        action: "Start website",
        badge: "Popular",
        icon: (
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.8"
                className="h-5 w-5"
                aria-hidden="true"
            >
                <rect x="3" y="4" width="18" height="16" rx="2" />
                <path d="M3 9h18M8 4v5" />
                <path d="M8 14h3M8 17h6" />
            </svg>
        ),
    },
    {
        id: "page",
        title: "Generate Page",
        description:
            "Build a complete Home, About, Services, Contact, or landing page using your chosen business style.",
        action: "Create page",
        icon: (
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.8"
                className="h-5 w-5"
                aria-hidden="true"
            >
                <path d="M6 3h9l4 4v14H6z" />
                <path d="M15 3v5h5M9 13h6M9 17h6" />
            </svg>
        ),
    },
    {
        id: "section",
        title: "Generate Section",
        description:
            "Create a new hero, services, pricing, testimonials, FAQ, contact, or custom website section.",
        action: "Create section",
        icon: (
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.8"
                className="h-5 w-5"
                aria-hidden="true"
            >
                <rect x="4" y="4" width="16" height="6" rx="1.5" />
                <rect x="4" y="14" width="7" height="6" rx="1.5" />
                <rect x="13" y="14" width="7" height="6" rx="1.5" />
            </svg>
        ),
    },
    {
        id: "content",
        title: "Rewrite Content",
        description:
            "Improve existing website copy, change its tone, shorten text, or make it more persuasive and professional.",
        action: "Rewrite content",
        icon: (
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.8"
                className="h-5 w-5"
                aria-hidden="true"
            >
                <path d="M4 6h11M4 10h8M4 14h6" />
                <path d="m14 17 5-5 2 2-5 5-3 1z" />
            </svg>
        ),
    },
    {
        id: "seo",
        title: "SEO Assistant",
        description:
            "Generate page titles, meta descriptions, keywords, headings, and search-friendly content recommendations.",
        action: "Optimize SEO",
        icon: (
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.8"
                className="h-5 w-5"
                aria-hidden="true"
            >
                <circle cx="10.5" cy="10.5" r="6.5" />
                <path d="m15.5 15.5 5 5M8 11h5M10.5 8.5v5" />
            </svg>
        ),
    },
    {
        id: "images",
        title: "Image Assistant",
        description:
            "Find suitable stock images, create image prompts, and recommend visuals that match the website industry.",
        action: "Find images",
        icon: (
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.8"
                className="h-5 w-5"
                aria-hidden="true"
            >
                <rect x="3" y="4" width="18" height="16" rx="2" />
                <circle cx="8.5" cy="9" r="1.5" />
                <path d="m4 17 5-5 4 4 2-2 5 5" />
            </svg>
        ),
    },
];

const recentGenerations = [
    {
        title: "Dental Clinic Homepage",
        type: "Full page",
        status: "Completed",
        time: "12 minutes ago",
    },
    {
        title: "Modern Services Section",
        type: "Section",
        status: "Completed",
        time: "Yesterday",
    },
    {
        title: "Restaurant SEO Content",
        type: "SEO content",
        status: "Draft",
        time: "Jul 26",
    },
];

function SparkleIcon({ className = "h-5 w-5" }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            className={className}
            aria-hidden="true"
        >
            <path d="M12 3c.6 4.2 2.8 6.4 7 7-4.2.6-6.4 2.8-7 7-.6-4.2-2.8-6.4-7-7 4.2-.6 6.4-2.8 7-7Z" />
            <path d="M19 16c.25 1.75 1.25 2.75 3 3-1.75.25-2.75 1.25-3 3-.25-1.75-1.25-2.75-3-3 1.75-.25 2.75-1.25 3-3Z" />
        </svg>
    );
}

function ArrowIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            className="h-4 w-4"
            aria-hidden="true"
        >
            <path d="M5 12h14M13 6l6 6-6 6" />
        </svg>
    );
}

export default function AIStudio() {
    const handleToolClick = (tool) => {
        console.info("AI Studio tool selected:", tool.id);
    };

    const handleGenerate = () => {
        console.info("Generate from prompt");
    };

    return (
        <section className="mx-auto w-full max-w-7xl pb-16">
            {/* Page heading */}
            <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div className="flex items-center gap-2 text-sm font-semibold text-violet-300">
                        <SparkleIcon className="h-4 w-4" />
                        <span>Cosmic AI</span>
                        <span className="rounded-full border border-violet-400/20 bg-violet-400/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.16em] text-violet-200">
                            Beta
                        </span>
                    </div>

                    <h1 className="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">
                        AI Studio
                    </h1>

                    <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-400">
                        Generate complete websites, pages, sections, content,
                        images, and SEO recommendations from a simple prompt.
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    <div className="rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3">
                        <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">
                            AI credits
                        </p>
                        <p className="mt-1 text-sm font-semibold text-white">
                            333 remaining
                        </p>
                    </div>

                    <button
                        type="button"
                        className="rounded-xl border border-white/10 bg-white/[0.04] px-4 py-3 text-sm font-semibold text-slate-200 transition hover:border-white/20 hover:bg-white/[0.07]"
                    >
                        View history
                    </button>
                </div>
            </div>

            {/* Main prompt */}
            <div className="cosmic-ai-prompt relative mt-8 overflow-hidden rounded-3xl border border-violet-400/20 bg-gradient-to-br from-violet-500/[0.12] via-[#121216] to-cyan-500/[0.06] p-5 shadow-2xl shadow-black/20 sm:p-7">
                <div className="pointer-events-none absolute -right-28 -top-28 h-72 w-72 rounded-full bg-violet-500/10 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-cyan-500/10 blur-3xl" />

                <div className="relative">
                    <div className="flex items-start gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-violet-300/20 bg-violet-400/10 text-violet-200">
                            <SparkleIcon />
                        </div>

                        <div>
                            <h2 className="text-lg font-semibold text-white">
                                What would you like to create?
                            </h2>
                            <p className="mt-1 text-sm text-slate-400">
                                Describe the business, page, style, and content
                                you need.
                            </p>
                        </div>
                    </div>

                    <div className="cosmic-ai-composer mt-6 rounded-2xl border border-white/10 bg-black/20 p-2 focus-within:border-violet-400/40 focus-within:ring-4 focus-within:ring-violet-500/5">
                        <textarea
                            rows={5}
                            placeholder="Example: Create a modern five-page website for a dental clinic in Cebu. Use a clean blue and white design with services, testimonials, appointment booking, and contact sections."
                            className="min-h-36 w-full resize-none border-0 bg-transparent px-3 py-3 text-sm leading-6 text-white outline-none placeholder:text-slate-600"
                        />

                        <div className="flex flex-col gap-3 border-t border-white/10 px-2 pt-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-wrap gap-2">
                                {[
                                    "Dental clinic",
                                    "Restaurant",
                                    "Real estate",
                                    "Construction",
                                ].map((suggestion) => (
                                    <button
                                        key={suggestion}
                                        type="button"
                                        className="rounded-lg border border-white/10 bg-white/[0.03] px-3 py-1.5 text-xs font-medium text-slate-400 transition hover:border-white/20 hover:bg-white/[0.06] hover:text-white"
                                    >
                                        {suggestion}
                                    </button>
                                ))}
                            </div>

                            <button
                                type="button"
                                onClick={handleGenerate}
                                className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-violet-100 focus:outline-none focus:ring-2 focus:ring-violet-400 focus:ring-offset-2 focus:ring-offset-[#121216]"
                            >
                                <SparkleIcon className="h-4 w-4" />
                                Generate
                            </button>
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-500">
                        <span>Industry-aware content</span>
                        <span className="hidden h-1 w-1 rounded-full bg-slate-700 sm:block" />
                        <span>Editable before publishing</span>
                        <span className="hidden h-1 w-1 rounded-full bg-slate-700 sm:block" />
                        <span>No coding required</span>
                    </div>
                </div>
            </div>

            {/* AI tools */}
            <div className="mt-10">
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-semibold text-white">
                            AI tools
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Choose a focused workflow for your next task.
                        </p>
                    </div>

                    <button
                        type="button"
                        className="hidden text-sm font-semibold text-violet-300 transition hover:text-violet-200 sm:block"
                    >
                        Explore all tools
                    </button>
                </div>

                <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {tools.map((tool) => (
                        <article
                            key={tool.id}
                            className="group flex min-h-56 flex-col rounded-2xl border border-white/10 bg-[#121214] p-5 transition hover:-translate-y-0.5 hover:border-violet-400/25 hover:bg-[#151519] hover:shadow-xl hover:shadow-black/20"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/[0.04] text-violet-300 transition group-hover:border-violet-400/20 group-hover:bg-violet-400/10">
                                    {tool.icon}
                                </div>

                                {tool.badge && (
                                    <span className="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-emerald-300">
                                        {tool.badge}
                                    </span>
                                )}
                            </div>

                            <h3 className="mt-5 text-base font-semibold text-white">
                                {tool.title}
                            </h3>

                            <p className="mt-2 flex-1 text-sm leading-6 text-slate-500">
                                {tool.description}
                            </p>

                            <button
                                type="button"
                                onClick={() => handleToolClick(tool)}
                                className="mt-5 inline-flex items-center gap-2 self-start text-sm font-semibold text-slate-300 transition group-hover:text-violet-300"
                            >
                                {tool.action}
                                <ArrowIcon />
                            </button>
                        </article>
                    ))}
                </div>
            </div>

            {/* Bottom panels */}
            <div className="mt-10 grid gap-5 xl:grid-cols-[1.45fr_0.75fr]">
                {/* Recent activity */}
                <div className="rounded-2xl border border-white/10 bg-[#121214]">
                    <div className="flex items-center justify-between border-b border-white/10 px-5 py-4">
                        <div>
                            <h2 className="text-base font-semibold text-white">
                                Recent generations
                            </h2>
                            <p className="mt-1 text-xs text-slate-500">
                                Continue editing your latest AI-generated work.
                            </p>
                        </div>

                        <button
                            type="button"
                            className="text-xs font-semibold text-slate-400 transition hover:text-white"
                        >
                            View all
                        </button>
                    </div>

                    <div className="divide-y divide-white/10">
                        {recentGenerations.map((item) => (
                            <div
                                key={item.title}
                                className="flex flex-col gap-4 px-5 py-4 transition hover:bg-white/[0.02] sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="flex min-w-0 items-center gap-3">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/[0.04] text-slate-400">
                                        <SparkleIcon className="h-4 w-4" />
                                    </div>

                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-white">
                                            {item.title}
                                        </p>
                                        <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                            <span>{item.type}</span>
                                            <span className="h-1 w-1 rounded-full bg-slate-700" />
                                            <span>{item.time}</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between gap-3 sm:justify-end">
                                    <span
                                        className={`rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.12em] ${
                                            item.status === "Completed"
                                                ? "bg-emerald-400/10 text-emerald-300"
                                                : "bg-amber-400/10 text-amber-300"
                                        }`}
                                    >
                                        {item.status}
                                    </span>

                                    <button
                                        type="button"
                                        className="rounded-lg border border-white/10 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:border-white/20 hover:bg-white/[0.05] hover:text-white"
                                    >
                                        Open
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Usage panel */}
                <aside className="rounded-2xl border border-white/10 bg-[#121214] p-5">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/20 bg-cyan-400/10 text-cyan-300">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.8"
                            className="h-5 w-5"
                            aria-hidden="true"
                        >
                            <path d="M4 19V9M10 19V5M16 19v-7M22 19H2" />
                        </svg>
                    </div>

                    <h2 className="mt-5 text-base font-semibold text-white">
                        Monthly AI usage
                    </h2>

                    <p className="mt-2 text-sm leading-6 text-slate-500">
                        You have used 67 of your 400 available AI credits this
                        month.
                    </p>

                    <div className="mt-5">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-medium text-slate-400">
                                67 credits used
                            </span>
                            <span className="font-semibold text-white">17%</span>
                        </div>

                        <div className="mt-2 h-2 overflow-hidden rounded-full bg-white/10">
                            <div className="h-full w-[17%] rounded-full bg-gradient-to-r from-violet-400 to-cyan-400" />
                        </div>
                    </div>

                    <div className="mt-6 rounded-xl border border-white/10 bg-white/[0.025] p-4">
                        <p className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
                            Current plan
                        </p>
                        <div className="mt-2 flex items-center justify-between gap-3">
                            <div>
                                <p className="text-sm font-semibold text-white">
                                    Cosmic Studio
                                </p>
                                <p className="mt-1 text-xs text-slate-500">
                                    Resets on August 1
                                </p>
                            </div>

                            <button
                                type="button"
                                className="rounded-lg bg-white px-3 py-2 text-xs font-bold text-slate-950 transition hover:bg-slate-200"
                            >
                                Upgrade
                            </button>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    );
}