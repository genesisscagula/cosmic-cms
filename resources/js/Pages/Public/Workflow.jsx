import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicSiteLayout from '@/Components/Public/PublicSiteLayout';
import PublicInnerHero from '@/Components/Public/PublicInnerHero';
import PublicCard from '@/Components/Public/PublicCard';
import PublicCta from '@/Components/Public/PublicCta';

const workflowSteps = [
    {
        number: '01',
        label: 'Describe',
        title: 'Tell Luna what you are building',
        description: 'Start with the business, audience, goals, pages, and visual direction instead of a blank canvas.',
    },
    {
        number: '02',
        label: 'Generate',
        title: 'Create the first website direction',
        description: 'Turn the brief into a page plan, content direction, section structure, and an initial visual system.',
    },
    {
        number: '03',
        label: 'Refine',
        title: 'Edit visually or ask Luna',
        description: 'Improve content, layout, media, typography, sections, navigation, and page structure while keeping the website editable.',
    },
    {
        number: '04',
        label: 'Reuse',
        title: 'Keep the design language connected',
        description: 'New pages reuse the website’s established design system, including installed Marketplace patterns when relevant.',
    },
    {
        number: '05',
        label: 'Launch',
        title: 'Preview and publish with confidence',
        description: 'Review responsive output, complete launch details, and move the website from preview to production.',
    },
];

const connectedLayers = [
    {
        icon: '✦',
        eyebrow: 'Conversation',
        title: 'Luna keeps the request connected to the website',
        description: 'Ask for a new page, rewrite, design change, navigation update, or visual refinement without restarting the workflow in another tool.',
    },
    {
        icon: '◇',
        eyebrow: 'Design',
        title: 'The visual system grows with the website',
        description: 'Colors, typography, spacing, buttons, cards, header, footer, and reusable patterns provide context for future changes.',
    },
    {
        icon: '▦',
        eyebrow: 'Content',
        title: 'Pages stay part of one structured project',
        description: 'Build and manage the website as a connected set of pages and sections instead of a collection of disconnected exports.',
    },
    {
        icon: '↗',
        eyebrow: 'Delivery',
        title: 'Preview and publishing remain part of the same flow',
        description: 'Keep creation, review, responsive QA, and launch preparation close together so fewer handoffs are required.',
    },
];

function PromptBriefPreview() {
    const chips = ['Restaurant', 'Premium', '5 pages', 'Reservations', 'Warm editorial'];

    return (
        <div className="relative mx-auto max-w-[560px]">
            <div className="pointer-events-none absolute -inset-8 rounded-[40px] bg-emerald-300/10 blur-3xl" />
            <div className="relative overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_35px_100px_-45px_rgba(4,47,46,.5)]">
                <div className="flex items-center justify-between border-b border-slate-200 bg-slate-50/80 px-5 py-4">
                    <div>
                        <p className="text-sm font-extrabold text-[#07132c]">Start with a clear brief</p>
                        <p className="mt-0.5 text-[10px] font-bold uppercase tracking-[.16em] text-slate-400">Luna website setup</p>
                    </div>
                    <span className="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[10px] font-extrabold text-emerald-700">Step 1</span>
                </div>

                <div className="space-y-4 p-5 sm:p-6">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p className="text-[10px] font-extrabold uppercase tracking-[.15em] text-slate-400">What are you building?</p>
                        <p className="mt-2 text-sm leading-6 text-slate-700">
                            Build a premium neighborhood restaurant website for Ember &amp; Olive. Include Home, About, Menu, Reservations, and Contact. Keep the design warm, editorial, and modern.
                        </p>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/75 p-4">
                            <p className="text-[9px] font-extrabold uppercase tracking-[.16em] text-slate-400">Primary goal</p>
                            <p className="mt-2 text-xs font-bold text-[#07132c]">Drive reservations</p>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/75 p-4">
                            <p className="text-[9px] font-extrabold uppercase tracking-[.16em] text-slate-400">Audience</p>
                            <p className="mt-2 text-xs font-bold text-[#07132c]">Local diners &amp; events</p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {chips.map((chip) => (
                            <span key={chip} className="rounded-lg border border-emerald-100 bg-emerald-50/70 px-3 py-1.5 text-[10px] font-bold text-emerald-800">{chip}</span>
                        ))}
                    </div>

                    <div className="rounded-2xl bg-[#07132c] p-4 text-white">
                        <div className="flex items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <span className="grid h-9 w-9 place-items-center rounded-xl bg-emerald-400/15 text-sm font-extrabold text-emerald-300">✦</span>
                                <div>
                                    <p className="text-xs font-extrabold">Luna has enough context to begin</p>
                                    <p className="mt-1 text-[10px] text-slate-400">Business · goals · pages · design direction</p>
                                </div>
                            </div>
                            <span className="rounded-xl bg-emerald-500 px-3 py-2 text-[10px] font-extrabold text-white">Generate →</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function GeneratedSitePlanPreview() {
    const pages = [
        ['Home', 'Hero · Story · Menu preview · Reviews · CTA'],
        ['About', 'Story · Philosophy · Team · Local sourcing'],
        ['Menu', 'Menu hero · Categories · Featured plates · CTA'],
        ['Reservations', 'Booking intro · Form · Policies · FAQ'],
        ['Contact', 'Location · Hours · Map · Contact details'],
    ];

    return (
        <div className="relative mx-auto max-w-[580px]">
            <div className="pointer-events-none absolute -inset-10 rounded-[42px] bg-violet-300/10 blur-3xl" />
            <div className="relative overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_35px_100px_-45px_rgba(15,23,42,.45)]">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <span className="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-violet-500 to-emerald-500 text-sm font-extrabold text-white">✦</span>
                        <div>
                            <p className="text-sm font-extrabold text-[#07132c]">Website plan</p>
                            <p className="text-[10px] font-bold uppercase tracking-[.15em] text-slate-400">Generated from the brief</p>
                        </div>
                    </div>
                    <span className="rounded-full bg-slate-100 px-3 py-1.5 text-[10px] font-extrabold text-slate-500">5 pages</span>
                </div>

                <div className="bg-[radial-gradient(circle_at_100%_0%,rgba(16,185,129,.08),transparent_30%),#fff] p-5 sm:p-6">
                    <div className="grid grid-cols-3 gap-2">
                        {[
                            ['Style', 'Warm editorial'],
                            ['Primary CTA', 'Reserve a table'],
                            ['Structure', 'Conversion-led'],
                        ].map(([label, value]) => (
                            <div key={label} className="rounded-xl border border-slate-200 bg-slate-50/75 p-3">
                                <p className="text-[8px] font-extrabold uppercase tracking-[.14em] text-slate-400">{label}</p>
                                <p className="mt-1.5 text-[10px] font-extrabold text-[#07132c]">{value}</p>
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 space-y-2.5">
                        {pages.map(([page, structure], index) => (
                            <div key={page} className="group flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 transition hover:border-emerald-200 hover:bg-emerald-50/30">
                                <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#07132c] text-[9px] font-extrabold text-white">0{index + 1}</span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-xs font-extrabold text-[#07132c]">{page}</p>
                                        <span className="text-[9px] font-bold text-emerald-700">Ready</span>
                                    </div>
                                    <p className="mt-1 text-[9px] leading-4 text-slate-400">{structure}</p>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 flex items-center justify-between rounded-2xl border border-emerald-100 bg-emerald-50/65 p-4">
                        <div>
                            <p className="text-[9px] font-extrabold uppercase tracking-[.15em] text-emerald-700">Generation plan</p>
                            <p className="mt-1 text-xs font-extrabold text-[#07132c]">Design system + page content + navigation</p>
                        </div>
                        <span className="rounded-xl bg-emerald-700 px-3 py-2 text-[10px] font-extrabold text-white">Build website →</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

function BuilderPreview() {
    return (
        <div className="relative mx-auto max-w-[620px]">
            <div className="pointer-events-none absolute -inset-10 rounded-[42px] bg-emerald-300/10 blur-3xl" />
            <div className="relative overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_35px_100px_-45px_rgba(4,47,46,.48)]">
                <div className="flex items-center justify-between border-b border-slate-200 bg-slate-50/75 px-4 py-3 sm:px-5">
                    <div className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-rose-300" />
                        <span className="h-2.5 w-2.5 rounded-full bg-amber-300" />
                        <span className="h-2.5 w-2.5 rounded-full bg-emerald-300" />
                        <span className="ml-2 text-[9px] font-bold text-slate-400">Ember &amp; Olive / Home</span>
                    </div>
                    <div className="flex gap-1.5">
                        <span className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[8px] font-extrabold text-slate-500">Preview</span>
                        <span className="rounded-lg bg-emerald-700 px-2.5 py-1.5 text-[8px] font-extrabold text-white">Publish</span>
                    </div>
                </div>

                <div className="grid min-h-[390px] grid-cols-[76px_minmax(0,1fr)] sm:grid-cols-[105px_minmax(0,1fr)_125px]">
                    <aside className="border-r border-slate-200 bg-[#08152f] p-2.5 text-white sm:p-3">
                        <p className="text-[8px] font-extrabold uppercase tracking-[.16em] text-emerald-300">Pages</p>
                        <div className="mt-3 space-y-1.5">
                            {['Home', 'About', 'Menu', 'Reservations', 'Contact'].map((item, index) => (
                                <div key={item} className={`rounded-lg px-2 py-2 text-[8px] font-bold ${index === 0 ? 'bg-white/10 text-white' : 'text-slate-400'}`}>{item}</div>
                            ))}
                        </div>
                        <div className="mt-4 border-t border-white/10 pt-3">
                            {['Design', 'Build', 'Publish'].map((item) => (
                                <div key={item} className="mb-1.5 rounded-lg border border-white/10 bg-white/[.03] px-2 py-2 text-[7px] font-bold text-slate-400">{item}⌄</div>
                            ))}
                        </div>
                    </aside>

                    <div className="relative overflow-hidden bg-[#f5f0e7] p-3 sm:p-4">
                        <div className="overflow-hidden rounded-xl border border-[#d5c8b5] bg-[#f8f1e7] shadow-sm">
                            <div className="flex items-center justify-between border-b border-[#d8cbbb] px-3 py-2">
                                <p className="text-[8px] font-extrabold tracking-[.14em] text-[#4d3a2b]">EMBER &amp; OLIVE</p>
                                <div className="flex gap-2 text-[6px] font-bold text-[#806d5e]">
                                    <span>ABOUT</span><span>MENU</span><span>RESERVE</span>
                                </div>
                            </div>
                            <div className="grid min-h-[182px] grid-cols-[1.05fr_.95fr]">
                                <div className="flex flex-col justify-center p-4 sm:p-5">
                                    <p className="text-[7px] font-bold uppercase tracking-[.18em] text-[#a06a43]">Seasonal neighborhood dining</p>
                                    <p className="mt-2 max-w-[190px] font-serif text-[19px] font-semibold leading-[1.04] text-[#35281f] sm:text-[24px]">Food worth gathering around.</p>
                                    <p className="mt-2 max-w-[190px] text-[7px] leading-3.5 text-[#766557]">A warm neighborhood kitchen inspired by the seasons, shared plates, and unhurried evenings.</p>
                                    <span className="mt-3 w-fit rounded-full bg-[#6d4b34] px-3 py-1.5 text-[6px] font-bold text-white">Reserve a table</span>
                                </div>
                                <div className="relative overflow-hidden bg-[radial-gradient(circle_at_25%_35%,#d89a58_0%,transparent_20%),radial-gradient(circle_at_70%_55%,#6f3f26_0%,transparent_32%),linear-gradient(145deg,#c98952,#43281d)]">
                                    <div className="absolute inset-3 rounded-full border border-white/20" />
                                    <div className="absolute bottom-4 right-4 h-16 w-16 rounded-full bg-[#d7b78b]/40 blur-lg" />
                                </div>
                            </div>
                        </div>

                        <div className="mt-3 grid grid-cols-3 gap-2">
                            {['Our story', 'Seasonal menu', 'Reservations'].map((item, index) => (
                                <div key={item} className={`rounded-lg border p-2 ${index === 1 ? 'border-emerald-400 bg-white ring-2 ring-emerald-300/30' : 'border-[#dbd0c2] bg-white/75'}`}>
                                    <span className="block h-8 rounded-md bg-[#e8ddd0]" />
                                    <p className="mt-1.5 text-[7px] font-extrabold text-[#4d3a2b]">{item}</p>
                                    <p className="mt-1 text-[6px] leading-3 text-[#9a8b7e]">Edit content and styling while keeping the page system connected.</p>
                                </div>
                            ))}
                        </div>

                        <div className="absolute right-4 top-[138px] flex gap-1">
                            {['✦', '↑', '↓', '×'].map((item) => (
                                <span key={item} className="grid h-6 w-6 place-items-center rounded-md border border-slate-200 bg-white text-[8px] font-bold text-slate-500 shadow-sm">{item}</span>
                            ))}
                        </div>
                    </div>

                    <aside className="hidden border-l border-slate-200 bg-slate-50/80 p-3 sm:block">
                        <p className="text-[8px] font-extrabold uppercase tracking-[.16em] text-slate-400">Selected section</p>
                        <p className="mt-1 text-[11px] font-extrabold text-[#07132c]">Menu preview</p>
                        <div className="mt-4 space-y-2">
                            {[
                                ['Content', 'Edit text'],
                                ['Media', 'Choose image'],
                                ['Layout', '3 cards'],
                                ['Spacing', 'Balanced'],
                            ].map(([label, value]) => (
                                <div key={label} className="rounded-lg border border-slate-200 bg-white p-2.5">
                                    <p className="text-[7px] font-extrabold uppercase tracking-[.14em] text-slate-400">{label}</p>
                                    <p className="mt-1 text-[8px] font-bold text-slate-600">{value}</p>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 rounded-xl border border-violet-100 bg-violet-50 p-3">
                            <p className="text-[8px] font-extrabold text-violet-700">✦ Ask Luna</p>
                            <p className="mt-1 text-[8px] leading-4 text-violet-500">“Make this section feel more editorial.”</p>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    );
}

function DesignReusePreview() {
    const tokens = [
        ['Color', '#6D4B34', 'Warm espresso'],
        ['Type', 'Aa', 'Editorial serif'],
        ['Radius', '24', 'Soft corners'],
        ['Space', '32', 'Airy rhythm'],
    ];

    return (
        <div className="relative mx-auto max-w-[585px]">
            <div className="pointer-events-none absolute -inset-9 rounded-[42px] bg-amber-200/15 blur-3xl" />
            <div className="relative overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_35px_100px_-45px_rgba(68,42,24,.35)]">
                <div className="border-b border-slate-200 px-5 py-4">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="text-sm font-extrabold text-[#07132c]">Installed design kit</p>
                            <p className="mt-0.5 text-[10px] font-bold uppercase tracking-[.15em] text-slate-400">Ember &amp; Olive · Marketplace</p>
                        </div>
                        <span className="rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-[10px] font-extrabold text-amber-800">Website-owned copy</span>
                    </div>
                </div>

                <div className="p-5 sm:p-6">
                    <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                        {tokens.map(([label, value, description]) => (
                            <div key={label} className="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                                <p className="text-[8px] font-extrabold uppercase tracking-[.14em] text-slate-400">{label}</p>
                                <div className="mt-2 flex h-9 items-center">
                                    {label === 'Color' ? (
                                        <span className="h-8 w-8 rounded-lg border border-black/5" style={{ backgroundColor: value }} />
                                    ) : (
                                        <span className="text-lg font-extrabold text-[#6d4b34]">{value}</span>
                                    )}
                                </div>
                                <p className="mt-1 text-[8px] font-bold text-slate-500">{description}</p>
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 rounded-2xl border border-[#e2d7ca] bg-[#f7f1e8] p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="text-[9px] font-extrabold uppercase tracking-[.15em] text-[#9b6846]">New request</p>
                                <p className="mt-1 text-sm font-extrabold text-[#4b3729]">Create a Catering page</p>
                            </div>
                            <span className="rounded-xl bg-[#6d4b34] px-3 py-2 text-[9px] font-extrabold text-white">Use design kit</span>
                        </div>

                        <div className="mt-4 grid grid-cols-3 gap-2">
                            {[
                                ['Hero', 'Existing hero pattern'],
                                ['Packages', 'Card/grid pattern'],
                                ['Events', 'Image + copy pattern'],
                                ['Menu', 'Menu pattern'],
                                ['FAQ', 'Simple content pattern'],
                                ['CTA', 'Reservation CTA'],
                            ].map(([title, description]) => (
                                <div key={title} className="rounded-xl border border-[#ded1c2] bg-white p-2.5">
                                    <p className="text-[8px] font-extrabold text-[#4d3a2b]">{title}</p>
                                    <p className="mt-1 text-[7px] leading-3 text-[#958474]">{description}</p>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="mt-4 flex items-start gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4">
                        <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-emerald-100 text-[11px] font-extrabold text-emerald-700">✓</span>
                        <div>
                            <p className="text-xs font-extrabold text-[#07132c]">The new page looks like it belonged there from day one.</p>
                            <p className="mt-1 text-[10px] leading-5 text-slate-500">Luna can generate new content while reusing the website’s typography, spacing, cards, buttons, header, footer, and visual language.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function LaunchPreview() {
    const checks = [
        ['Responsive layout', 'Desktop · Tablet · Mobile'],
        ['Navigation', 'Pages and menu reviewed'],
        ['SEO basics', 'Titles · descriptions · sitemap'],
        ['Launch destination', 'Website ready to publish'],
    ];

    return (
        <div className="relative mx-auto max-w-[580px]">
            <div className="pointer-events-none absolute -inset-10 rounded-[42px] bg-emerald-300/10 blur-3xl" />
            <div className="relative overflow-hidden rounded-[28px] border border-white/10 bg-[#07132c] text-white shadow-[0_35px_110px_-48px_rgba(2,18,40,.8)]">
                <div className="relative overflow-hidden border-b border-white/10 px-5 py-5">
                    <div className="pointer-events-none absolute -right-12 -top-16 h-44 w-44 rounded-full border border-emerald-300/15" />
                    <div className="relative flex items-center justify-between gap-4">
                        <div>
                            <p className="text-sm font-extrabold">Launch checklist</p>
                            <p className="mt-1 text-[10px] font-bold uppercase tracking-[.15em] text-emerald-300">Ember &amp; Olive</p>
                        </div>
                        <span className="rounded-full border border-emerald-300/25 bg-emerald-300/10 px-3 py-1.5 text-[10px] font-extrabold text-emerald-200">Ready to review</span>
                    </div>
                </div>

                <div className="p-5 sm:p-6">
                    <div className="grid grid-cols-3 gap-2">
                        {[
                            ['▱', 'Desktop'],
                            ['▯', 'Tablet'],
                            ['▯', 'Mobile'],
                        ].map(([icon, label], index) => (
                            <div key={label} className={`rounded-xl border p-3 text-center ${index === 0 ? 'border-emerald-300/35 bg-emerald-300/10' : 'border-white/10 bg-white/[.04]'}`}>
                                <p className="text-lg text-emerald-300">{icon}</p>
                                <p className="mt-1 text-[9px] font-extrabold text-white">{label}</p>
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 space-y-2.5">
                        {checks.map(([title, detail]) => (
                            <div key={title} className="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[.045] px-3.5 py-3">
                                <span className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-emerald-400/10 text-[10px] font-extrabold text-emerald-300 ring-1 ring-emerald-300/15">✓</span>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[10px] font-extrabold text-white">{title}</p>
                                    <p className="mt-0.5 text-[9px] text-slate-400">{detail}</p>
                                </div>
                                <span className="text-[8px] font-extrabold uppercase tracking-[.12em] text-emerald-300">Ready</span>
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 overflow-hidden rounded-2xl border border-white/10 bg-white/[.04] p-3">
                        <div className="rounded-xl bg-white p-3 text-slate-900">
                            <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                                <span className="text-[8px] font-extrabold tracking-[.14em] text-[#4d3a2b]">EMBER &amp; OLIVE</span>
                                <span className="rounded-full bg-[#6d4b34] px-2.5 py-1 text-[6px] font-bold text-white">Reserve</span>
                            </div>
                            <div className="grid grid-cols-[1.1fr_.9fr] gap-2 pt-3">
                                <div className="rounded-lg bg-[#f5eee5] p-3">
                                    <p className="font-serif text-[14px] font-semibold leading-tight text-[#3c2d23]">Seasonal food.<br />Warm evenings.</p>
                                    <div className="mt-2 h-1.5 w-12 rounded-full bg-[#b1774f]" />
                                </div>
                                <div className="rounded-lg bg-[linear-gradient(145deg,#d19b68,#4e3023)]" />
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 flex items-center justify-between gap-4 rounded-2xl bg-emerald-500 p-4 text-white">
                        <div>
                            <p className="text-[9px] font-extrabold uppercase tracking-[.14em] text-emerald-950/70">Final step</p>
                            <p className="mt-1 text-xs font-extrabold">Publish the reviewed website</p>
                        </div>
                        <span className="rounded-xl bg-[#07132c] px-3.5 py-2 text-[9px] font-extrabold text-white">Publish →</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

function WorkflowStage({ eyebrow, number, title, description, points, preview, reverse = false, dark = false }) {
    const content = (
        <div className="max-w-xl">
            <div className="flex items-center gap-3">
                <span className={`grid h-10 w-10 place-items-center rounded-xl text-[11px] font-extrabold ${dark ? 'bg-emerald-300/10 text-emerald-300 ring-1 ring-emerald-300/20' : 'bg-emerald-100 text-emerald-700'}`}>{number}</span>
                <p className={`text-[11px] font-extrabold uppercase tracking-[.2em] ${dark ? 'text-emerald-300' : 'text-emerald-700'}`}>{eyebrow}</p>
            </div>
            <h2 className={`mt-5 text-3xl font-extrabold tracking-[-.04em] sm:text-4xl ${dark ? 'text-white' : 'text-[#07132c]'}`}>{title}</h2>
            <p className={`mt-5 text-base leading-7 ${dark ? 'text-slate-300' : 'text-slate-600'}`}>{description}</p>
            <div className="mt-6 space-y-3">
                {points.map((point) => (
                    <div key={point} className="flex items-start gap-3">
                        <span className={`mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-lg text-[9px] font-extrabold ${dark ? 'bg-white/5 text-emerald-300 ring-1 ring-white/10' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100'}`}>✓</span>
                        <p className={`text-sm leading-6 ${dark ? 'text-slate-300' : 'text-slate-600'}`}>{point}</p>
                    </div>
                ))}
            </div>
        </div>
    );

    return (
        <section className={dark ? 'relative overflow-hidden bg-[#07132c]' : 'bg-white'}>
            {dark && (
                <>
                    <div className="pointer-events-none absolute -right-40 top-0 h-[420px] w-[420px] rounded-full border border-emerald-300/10" />
                    <div className="pointer-events-none absolute -right-16 top-24 h-[260px] w-[260px] rounded-full border border-emerald-300/10" />
                    <div className="pointer-events-none absolute left-[38%] top-0 h-64 w-64 rounded-full bg-emerald-400/5 blur-3xl" />
                </>
            )}
            <div className="relative mx-auto grid max-w-[1240px] items-center gap-12 px-5 py-16 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8 lg:py-24">
                {reverse ? preview : content}
                {reverse ? content : preview}
            </div>
        </section>
    );
}

export default function Workflow() {
    return (
        <PublicSiteLayout>
            <SeoHead
                title="Workflow | Cosmic CMS"
                description="See how Cosmic CMS connects business prompting, AI-assisted website generation, visual refinement, reusable design systems, responsive preview, and publishing in one workflow."
            />

            <PublicInnerHero
                eyebrow="Cosmic workflow"
                title="From business idea to a live website,"
                highlight="in one connected flow."
                description="Cosmic CMS keeps the brief, website structure, design system, visual editing, Luna conversation, responsive review, and publishing workflow connected so the site can keep evolving after the first draft."
                breadcrumbs={[{ label: 'Workflow' }]}
            >
                <div className="flex flex-wrap gap-3">
                    <Link href="/start" className="inline-flex min-h-12 items-center rounded-xl bg-emerald-700 px-6 text-sm font-bold text-white shadow-lg shadow-emerald-900/10 transition hover:bg-emerald-800">Create free demo →</Link>
                    <Link href="/features" className="inline-flex min-h-12 items-center rounded-xl border border-slate-300 bg-white px-6 text-sm font-bold text-slate-800 transition hover:bg-slate-50">Explore features</Link>
                </div>
            </PublicInnerHero>

            <section className="border-b border-slate-200 bg-slate-50/70">
                <div className="mx-auto max-w-[1240px] px-5 py-14 sm:px-6 lg:px-8 lg:py-16">
                    <div className="mx-auto max-w-3xl text-center">
                        <p className="text-[11px] font-extrabold uppercase tracking-[.2em] text-emerald-700">The complete website loop</p>
                        <h2 className="mt-4 text-3xl font-extrabold tracking-[-.04em] text-[#07132c] sm:text-4xl">Five stages. One website context.</h2>
                        <p className="mt-4 text-base leading-7 text-slate-600">Each stage feeds the next, so you are not recreating the brief, design decisions, and page logic every time the project moves forward.</p>
                    </div>

                    <div className="relative mt-10 grid gap-3 md:grid-cols-5">
                        <div className="pointer-events-none absolute left-[9%] right-[9%] top-7 hidden border-t border-dashed border-emerald-300 md:block" />
                        {workflowSteps.map((step) => (
                            <div key={step.number} className="relative rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div className="relative z-10 flex items-center justify-between gap-3">
                                    <span className="grid h-9 w-9 place-items-center rounded-xl bg-[#07132c] text-[10px] font-extrabold text-white ring-4 ring-slate-50">{step.number}</span>
                                    <span className="text-[9px] font-extrabold uppercase tracking-[.15em] text-emerald-700">{step.label}</span>
                                </div>
                                <p className="mt-4 text-sm font-extrabold leading-5 text-[#07132c]">{step.title}</p>
                                <p className="mt-2 text-[11px] leading-5 text-slate-500">{step.description}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <WorkflowStage
                number="01"
                eyebrow="Describe your business"
                title="Start with intent instead of an empty page."
                description="A better first draft starts with useful context. Tell Luna what the business does, who it serves, what the website needs to accomplish, which pages matter, and the kind of visual direction you want."
                points={[
                    'Capture the business, audience, offer, and primary conversion goal.',
                    'Describe page requirements and useful functionality in plain language.',
                    'Set an initial visual direction without manually choosing every design token.',
                ]}
                preview={<PromptBriefPreview />}
            />

            <WorkflowStage
                number="02"
                eyebrow="Generate the starting point"
                title="Turn the brief into a coherent website plan."
                description="Cosmic can use the initial request to establish page structure, content direction, navigation, and visual language before the website is refined section by section."
                points={[
                    'Plan the page set and the role each page plays in the customer journey.',
                    'Generate section structure and content around the website’s actual goals.',
                    'Create a visual system that can be reused instead of styling pages independently.',
                ]}
                preview={<GeneratedSitePlanPreview />}
                reverse
            />

            <WorkflowStage
                number="03"
                eyebrow="Refine in the builder"
                title="Edit visually, ask Luna, or combine both."
                description="The first draft is only the starting point. Refine sections, media, page structure, navigation, typography, colors, and content while preserving the website as an editable system."
                points={[
                    'Select the section you want to improve without rebuilding everything around it.',
                    'Use direct visual controls for routine edits and Luna for higher-level requests.',
                    'Preview the result while keeping page structure and reusable patterns intact.',
                ]}
                preview={<BuilderPreview />}
                dark
            />

            <WorkflowStage
                number="04"
                eyebrow="Reuse the website design system"
                title="New pages should feel like they were always part of the site."
                description="When a website grows, Cosmic can reuse the established design language instead of reaching for unrelated global patterns. Marketplace websites can keep their installed template design kit as the primary source for future pages."
                points={[
                    'Reuse typography, colors, spacing, buttons, cards, header, footer, and page patterns.',
                    'For Marketplace websites, prefer the installed design kit and template-specific components.',
                    'Generate new page-specific content while maintaining the existing brand language.',
                ]}
                preview={<DesignReusePreview />}
                reverse
            />

            <WorkflowStage
                number="05"
                eyebrow="Preview and publish"
                title="Review the complete experience before it goes live."
                description="Check responsive behavior, navigation, launch fundamentals, and the final visual result before publishing. The goal is to make delivery part of the website workflow rather than a separate handoff."
                points={[
                    'Review desktop, tablet, and mobile output before launch.',
                    'Confirm page structure, navigation, metadata, and public-facing content.',
                    'Publish the reviewed website when the experience is ready.',
                ]}
                preview={<LaunchPreview />}
            />

            <section className="border-y border-slate-200 bg-slate-50/70">
                <div className="mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-20">
                    <div className="grid gap-8 lg:grid-cols-[.82fr_1.18fr] lg:items-end">
                        <div>
                            <p className="text-[11px] font-extrabold uppercase tracking-[.2em] text-emerald-700">Why the workflow matters</p>
                            <h2 className="mt-4 text-3xl font-extrabold tracking-[-.04em] text-[#07132c] sm:text-4xl">Keep the important context between every change.</h2>
                        </div>
                        <p className="max-w-2xl text-base leading-7 text-slate-600 lg:justify-self-end">Cosmic CMS is designed around the website as an evolving system. That means the AI conversation, page structure, visual language, reusable components, and publishing path can work together instead of becoming separate tasks.</p>
                    </div>

                    <div className="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {connectedLayers.map((feature) => (
                            <PublicCard key={feature.title} icon={feature.icon} eyebrow={feature.eyebrow} title={feature.title}>
                                <p>{feature.description}</p>
                            </PublicCard>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-20">
                <div className="overflow-hidden rounded-[30px] border border-slate-200 bg-[radial-gradient(circle_at_80%_25%,rgba(16,185,129,.12),transparent_26%),linear-gradient(135deg,#ffffff,#f5fbf8)] p-7 sm:p-9 lg:p-12">
                    <div className="grid gap-10 lg:grid-cols-[1fr_.85fr] lg:items-center">
                        <div>
                            <p className="text-[11px] font-extrabold uppercase tracking-[.2em] text-emerald-700">Designed for the next request too</p>
                            <h2 className="mt-4 max-w-2xl text-3xl font-extrabold tracking-[-.04em] text-[#07132c] sm:text-4xl">Launch is not the end of the workflow.</h2>
                            <p className="mt-4 max-w-2xl text-base leading-7 text-slate-600">A month later, the business may need a new service, campaign, landing page, menu, team member, or content update. The website should be able to continue from the design and structure already established.</p>
                            <div className="mt-6 flex flex-wrap gap-3">
                                <Link href="/features" className="inline-flex min-h-11 items-center rounded-xl bg-[#07132c] px-5 text-sm font-bold text-white transition hover:bg-slate-800">See platform features →</Link>
                                <Link href="/marketplace" className="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Browse Marketplace</Link>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            {[
                                ['Week 1', 'Launch the first website'],
                                ['Month 2', 'Add a Catering page'],
                                ['Month 4', 'Update seasonal content'],
                                ['Month 8', 'Expand the customer journey'],
                            ].map(([time, action], index) => (
                                <div key={time} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-[9px] font-extrabold uppercase tracking-[.15em] text-emerald-700">{time}</span>
                                        <span className="grid h-7 w-7 place-items-center rounded-lg bg-emerald-50 text-[9px] font-extrabold text-emerald-700">0{index + 1}</span>
                                    </div>
                                    <p className="mt-3 text-sm font-extrabold text-[#07132c]">{action}</p>
                                    <p className="mt-2 text-[11px] leading-5 text-slate-500">Continue from the same website context rather than beginning from scratch.</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <PublicCta
                eyebrow="Start with the workflow"
                title="Describe the business. Let the website take shape."
                description="Create a first draft, refine it visually, keep the design system connected, and move toward launch from the same Cosmic CMS workflow."
            />
        </PublicSiteLayout>
    );
}
