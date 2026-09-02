import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicSiteLayout from '@/Components/Public/PublicSiteLayout';
import PublicInnerHero from '@/Components/Public/PublicInnerHero';
import PublicCard from '@/Components/Public/PublicCard';
import PublicCta from '@/Components/Public/PublicCta';

const coreFeatures = [
    {
        icon: '✦',
        eyebrow: 'Luna AI',
        title: 'Start with the outcome, not the blank canvas',
        description: 'Describe the website or change you want. Luna can plan pages, generate a first draft, rewrite content, and help refine the experience through natural language.',
    },
    {
        icon: '▦',
        eyebrow: 'Visual builder',
        title: 'Refine every section without losing control',
        description: 'Work visually with page sections, media, navigation, global styles, and responsive layouts while keeping the final website structured and editable.',
    },
    {
        icon: '◇',
        eyebrow: 'Design system',
        title: 'Keep the whole website visually consistent',
        description: 'Typography, spacing, buttons, cards, colors, headers, footers, and reusable section patterns stay connected instead of becoming one-off page decisions.',
    },
    {
        icon: '↗',
        eyebrow: 'Publish',
        title: 'Move from preview to production faster',
        description: 'Review responsive output, prepare the site for launch, and publish without stitching together a separate design tool, CMS, and deployment workflow.',
    },
];

const lunaActions = [
    ['01', 'Create', 'Build a new page or section from a short business request.'],
    ['02', 'Refine', 'Change layout, visual direction, typography, color, or content.'],
    ['03', 'Reuse', 'Follow the website’s existing design language when expanding the site.'],
    ['04', 'Navigate', 'Move between pages and keep the conversation connected to the website.'],
];

const builderCapabilities = [
    ['Section editing', 'Update the content and visual direction of individual sections while preserving the rest of the page.', '⌁'],
    ['Global styling', 'Manage typography, heading scale, buttons, spacing, corners, and effects from a consistent design layer.', 'Aa'],
    ['Media library', 'Organize site media and reuse the right image or asset across page editing workflows.', '▧'],
    ['Responsive preview', 'Review desktop, tablet, and mobile behavior before the website is published.', '▤'],
    ['Navigation', 'Create and refine page structure, menu links, submenus, headers, and footers as the website grows.', '☰'],
    ['Reusable patterns', 'Build new pages from coherent section and page patterns instead of assembling unrelated components.', '◫'],
];

const designKitItems = [
    'Colors and design tokens',
    'Typography and type scale',
    'Spacing and visual rhythm',
    'Buttons and form styling',
    'Header and footer patterns',
    'Cards, grids, and CTAs',
    'Hero and section patterns',
    'Navigation and page layouts',
];

const deliveryFeatures = [
    {
        icon: '◎',
        title: 'Responsive by default',
        copy: 'Build with desktop, tablet, and mobile output in mind instead of treating responsive work as a final patch.',
    },
    {
        icon: '⚡',
        title: 'Lean delivery path',
        copy: 'Keep the creation and publishing workflow connected so teams spend less time moving work between tools.',
    },
    {
        icon: '⌕',
        title: 'SEO-ready foundation',
        copy: 'Structure public pages with the metadata and launch fundamentals needed for a professional business website.',
    },
    {
        icon: '↗',
        title: 'Preview before launch',
        copy: 'Share and review the result before committing changes to the final public website.',
    },
];

const scaleFeatures = [
    {
        eyebrow: 'Content',
        title: 'Pages, posts, and updates',
        description: 'Manage more than brochure pages as the website grows into an active content channel.',
    },
    {
        eyebrow: 'Growth',
        title: 'Leads and customer journeys',
        description: 'Build clearer conversion paths around forms, calls to action, offers, and business-focused page experiences.',
    },
    {
        eyebrow: 'Commerce',
        title: 'Products and online ordering',
        description: 'Extend supported websites into product, checkout, order, and commerce workflows when the project needs it.',
    },
    {
        eyebrow: 'Workspace',
        title: 'Multiple websites, one account',
        description: 'Keep websites, credits, team access, previews, and ongoing work organized from a connected workspace.',
    },
];

function FeaturePill({ icon, title, copy }) {
    return (
        <div className="rounded-2xl border border-white/10 bg-white/[.055] p-4 backdrop-blur-sm">
            <div className="flex gap-3">
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-400/10 text-xs font-extrabold text-emerald-300 ring-1 ring-emerald-300/20">{icon}</span>
                <div>
                    <p className="text-sm font-semibold text-white">{title}</p>
                    <p className="mt-1 text-xs leading-5 text-slate-400">{copy}</p>
                </div>
            </div>
        </div>
    );
}

function LunaPreview() {
    return (
        <div className="relative mx-auto max-w-[590px]">
            <div className="pointer-events-none absolute -inset-8 rounded-[40px] bg-emerald-300/10 blur-3xl" />
            <div className="relative overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_35px_100px_-45px_rgba(4,47,46,.5)]">
                <div className="flex items-center justify-between border-b border-slate-200 bg-slate-50/80 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <span className="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-violet-500 to-emerald-500 text-sm font-extrabold text-white shadow-sm">✦</span>
                        <div>
                            <p className="text-sm font-extrabold text-[#07132c]">Luna</p>
                            <p className="text-[10px] font-bold uppercase tracking-[.16em] text-slate-400">Website copilot</p>
                        </div>
                    </div>
                    <span className="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[10px] font-extrabold text-emerald-700">Connected to website</span>
                </div>

                <div className="space-y-5 bg-[radial-gradient(circle_at_85%_0%,rgba(16,185,129,.08),transparent_32%),#fff] p-5 sm:p-6">
                    <div className="ml-auto max-w-[84%] rounded-2xl rounded-tr-md bg-[#07132c] px-4 py-3 text-sm leading-6 text-white shadow-sm">
                        Create a premium Catering page and keep it consistent with the rest of this restaurant website.
                    </div>

                    <div className="max-w-[92%] rounded-2xl rounded-tl-md border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex items-start gap-3">
                            <span className="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-emerald-100 text-[11px] font-bold text-emerald-700">✦</span>
                            <div>
                                <p className="text-sm font-bold text-[#07132c]">I’ll reuse the installed design system.</p>
                                <p className="mt-1.5 text-xs leading-5 text-slate-500">I’ll match the existing typography, spacing, buttons, cards, header, footer, and section language while creating Catering-specific content.</p>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {['Design kit', 'Existing patterns', 'Catering content'].map((item) => (
                                        <span key={item} className="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-bold text-slate-600">{item}</span>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-emerald-100 bg-emerald-50/65 p-4">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-[10px] font-extrabold uppercase tracking-[.15em] text-emerald-700">Proposed page</p>
                                <p className="mt-1 text-sm font-extrabold text-[#07132c]">Catering · 6 sections</p>
                            </div>
                            <span className="rounded-xl bg-emerald-700 px-3 py-2 text-[10px] font-extrabold text-white">Apply build →</span>
                        </div>
                        <div className="mt-3 grid grid-cols-3 gap-2">
                            {['Hero', 'Packages', 'Events', 'Menu', 'FAQ', 'CTA'].map((item) => (
                                <div key={item} className="rounded-lg border border-emerald-100 bg-white px-2 py-2 text-center text-[9px] font-bold text-slate-600">{item}</div>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                        <span className="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-xs text-slate-500">＋</span>
                        <span className="flex-1 px-2 text-xs text-slate-400">Ask Luna to change this website…</span>
                        <span className="grid h-9 w-9 place-items-center rounded-xl bg-emerald-700 text-xs font-bold text-white">↑</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

function BuilderPanel() {
    return (
        <div className="overflow-hidden rounded-[28px] border border-slate-200 bg-[#f7faf8] shadow-[0_32px_90px_-42px_rgba(15,23,42,.38)]">
            <div className="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 sm:px-5">
                <div className="flex items-center gap-2">
                    <span className="h-2.5 w-2.5 rounded-full bg-rose-300" />
                    <span className="h-2.5 w-2.5 rounded-full bg-amber-300" />
                    <span className="h-2.5 w-2.5 rounded-full bg-emerald-300" />
                </div>
                <span className="rounded-lg bg-slate-100 px-4 py-1.5 text-[9px] font-semibold text-slate-400">Builder · Home</span>
                <span className="text-[9px] font-bold text-emerald-700">Preview</span>
            </div>

            <div className="grid min-h-[385px] sm:grid-cols-[1fr_168px]">
                <div className="relative overflow-hidden bg-white p-4 sm:p-5">
                    <div className="relative overflow-hidden rounded-2xl bg-[radial-gradient(circle_at_80%_20%,rgba(52,211,153,.18),transparent_25%),linear-gradient(120deg,#06142d,#064e3b)] px-5 py-8 text-white sm:px-7 sm:py-10">
                        <span className="rounded-full bg-white/10 px-3 py-1 text-[8px] font-extrabold uppercase tracking-[.16em] text-emerald-200">Premium website</span>
                        <h3 className="mt-4 max-w-xs text-2xl font-semibold leading-tight tracking-[-.035em]">A polished page that stays easy to edit.</h3>
                        <p className="mt-3 max-w-sm text-[10px] leading-5 text-slate-300">Hover a section, open the focused editor, and ask Luna for the change you want.</p>
                        <div className="mt-5 flex gap-2">
                            <span className="rounded-lg bg-emerald-500 px-3 py-2 text-[9px] font-bold">Primary action</span>
                            <span className="rounded-lg border border-white/20 bg-white/5 px-3 py-2 text-[9px] font-bold">Learn more</span>
                        </div>
                    </div>

                    <div className="relative mt-3 rounded-2xl border-2 border-emerald-400 bg-emerald-50/40 p-3">
                        <span className="absolute -top-3 left-3 rounded-lg bg-emerald-600 px-2.5 py-1 text-[8px] font-bold text-white shadow-sm">Section · Features</span>
                        <div className="grid gap-2 pt-2 sm:grid-cols-3">
                            {['Fast starting point', 'Visual refinement', 'Responsive output'].map((title, index) => (
                                <div key={title} className="rounded-xl border border-slate-200 bg-white p-3">
                                    <span className="grid h-7 w-7 place-items-center rounded-lg bg-emerald-100 text-[9px] font-bold text-emerald-700">{index + 1}</span>
                                    <p className="mt-2 text-[9px] font-semibold text-[#07132c]">{title}</p>
                                    <p className="mt-1 text-[8px] leading-4 text-slate-400">Designed as part of the same page system.</p>
                                </div>
                            ))}
                        </div>
                        <div className="absolute right-3 top-3 flex gap-1">
                            {['✦', '↑', '↓', '×'].map((item) => <span key={item} className="grid h-6 w-6 place-items-center rounded-md border border-slate-200 bg-white text-[8px] font-bold text-slate-500 shadow-sm">{item}</span>)}
                        </div>
                    </div>
                </div>

                <aside className="hidden border-l border-slate-200 bg-slate-50/80 p-3 sm:block">
                    <p className="text-[8px] font-extrabold uppercase tracking-[.16em] text-slate-400">Page</p>
                    <p className="mt-1 text-[11px] font-extrabold text-[#07132c]">Home</p>
                    <div className="mt-4 space-y-2">
                        {['Header', 'Hero', 'Features', 'Services', 'CTA', 'Footer'].map((item, index) => (
                            <div key={item} className={`flex items-center gap-2 rounded-lg px-2.5 py-2 text-[8px] font-bold ${index === 2 ? 'border border-emerald-200 bg-emerald-50 text-emerald-700' : 'border border-slate-200 bg-white text-slate-500'}`}>
                                <span className="text-slate-300">⠿</span>{item}
                            </div>
                        ))}
                    </div>
                    <div className="mt-4 rounded-xl border border-violet-100 bg-violet-50 p-3">
                        <p className="text-[8px] font-extrabold text-violet-700">✦ Ask Luna</p>
                        <p className="mt-1 text-[8px] leading-4 text-violet-500">Edit the selected section with natural language.</p>
                    </div>
                </aside>
            </div>
        </div>
    );
}

export default function Features() {
    return (
        <PublicSiteLayout>
            <SeoHead
                title="Features | Cosmic CMS"
                description="Explore Luna AI, visual website editing, reusable design systems, responsive preview, publishing, content, commerce, and workspace features in Cosmic CMS."
            />

            <PublicInnerHero
                eyebrow="Product features"
                title="One connected platform to create, refine, and "
                highlight="publish faster."
                description="Cosmic CMS combines AI-assisted creation, visual website editing, reusable design systems, and publishing workflows so a business website can keep evolving without becoming harder to manage."
                breadcrumbs={[{ label: 'Features' }]}
            >
                <div className="flex flex-wrap gap-3">
                    <Link href="/start" className="inline-flex min-h-12 items-center rounded-xl bg-emerald-700 px-6 text-sm font-bold text-white shadow-lg shadow-emerald-900/10 transition hover:bg-emerald-800">Create free demo →</Link>
                    <Link href="/workflow" className="inline-flex min-h-12 items-center rounded-xl border border-slate-300 bg-white px-6 text-sm font-bold text-slate-800 transition hover:bg-slate-50">See the workflow</Link>
                </div>
                <div className="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-xs font-semibold text-slate-500">
                    <span>✓ AI-assisted first draft</span>
                    <span>✓ Visual refinement</span>
                    <span>✓ Reusable design language</span>
                    <span>✓ Responsive publishing</span>
                </div>
            </PublicInnerHero>

            <section className="mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-20">
                <div className="mx-auto max-w-3xl text-center">
                    <p className="text-[11px] font-extrabold uppercase tracking-[.2em] text-emerald-700">The core platform</p>
                    <h2 className="mt-4 text-3xl font-extrabold tracking-[-.04em] text-[#07132c] sm:text-4xl lg:text-[44px]">Build faster without turning the website into a black box.</h2>
                    <p className="mx-auto mt-5 max-w-2xl text-base leading-7 text-slate-600">AI helps with the repetitive starting work. The builder, website design system, and publishing flow keep the result understandable and editable after generation.</p>
                </div>

                <div className="mt-10 grid gap-5 md:grid-cols-2">
                    {coreFeatures.map((feature) => (
                        <PublicCard key={feature.title} icon={feature.icon} eyebrow={feature.eyebrow} title={feature.title} className="min-h-[245px]">
                            <p>{feature.description}</p>
                        </PublicCard>
                    ))}
                </div>
            </section>

            <section className="border-y border-slate-200 bg-[radial-gradient(circle_at_10%_25%,rgba(16,185,129,.08),transparent_26%),linear-gradient(180deg,#f8fbfa_0%,#fff_100%)]">
                <div className="mx-auto grid max-w-[1240px] gap-12 px-5 py-16 sm:px-6 lg:grid-cols-[.82fr_1.18fr] lg:items-center lg:px-8 lg:py-24">
                    <div>
                        <span className="inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-3.5 py-2 text-[10px] font-extrabold uppercase tracking-[.18em] text-violet-700">✦ Luna AI</span>
                        <h2 className="mt-5 text-3xl font-extrabold tracking-[-.045em] text-[#07132c] sm:text-4xl lg:text-[46px]">Ask for the website change you actually want.</h2>
                        <p className="mt-5 max-w-xl text-base leading-7 text-slate-600">Luna works as the AI layer across the website rather than a separate copy generator. It can understand the page you are working on, help create new content, and preserve the website’s visual direction while making changes.</p>

                        <div className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                            {lunaActions.map(([num, title, copy]) => (
                                <div key={num} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                    <div className="flex gap-3">
                                        <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-violet-50 text-[9px] font-extrabold text-violet-700 ring-1 ring-violet-100">{num}</span>
                                        <div><p className="text-sm font-semibold text-[#07132c]">{title}</p><p className="mt-1 text-xs leading-5 text-slate-500">{copy}</p></div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                    <LunaPreview />
                </div>
            </section>

            <section className="mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-24">
                <div className="grid gap-12 lg:grid-cols-[1fr_.9fr] lg:items-center">
                    <BuilderPanel />
                    <div>
                        <p className="text-[11px] font-extrabold uppercase tracking-[.2em] text-emerald-700">Visual builder</p>
                        <h2 className="mt-4 text-3xl font-extrabold tracking-[-.045em] text-[#07132c] sm:text-4xl">Visual where it should be. Structured underneath.</h2>
                        <p className="mt-5 text-base leading-7 text-slate-600">The builder keeps the editing experience approachable while preserving meaningful website structure. Work at the page or section level, make focused changes, and keep the site’s shared styles connected.</p>

                        <div className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                            {builderCapabilities.map(([title, copy, icon]) => (
                                <div key={title} className="rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-emerald-200 hover:bg-emerald-50/30">
                                    <div className="flex items-start gap-3">
                                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-50 text-[11px] font-extrabold text-emerald-700">{icon}</span>
                                        <div><h3 className="text-sm font-semibold text-[#07132c]">{title}</h3><p className="mt-1 text-xs leading-5 text-slate-500">{copy}</p></div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <section className="relative overflow-hidden bg-[#05142c] text-white">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_85%_20%,rgba(16,185,129,.17),transparent_30%),radial-gradient(circle_at_20%_90%,rgba(59,130,246,.08),transparent_28%)]" />
                <div className="pointer-events-none absolute -right-32 top-20 h-96 w-96 rounded-full border border-emerald-300/10" />
                <div className="pointer-events-none absolute -right-10 top-44 h-60 w-60 rounded-full border border-emerald-300/10" />

                <div className="relative mx-auto grid max-w-[1240px] gap-12 px-5 py-16 sm:px-6 lg:grid-cols-[.95fr_1.05fr] lg:items-center lg:px-8 lg:py-24">
                    <div>
                        <p className="text-[11px] font-extrabold uppercase tracking-[.2em] text-emerald-300">Marketplace design intelligence</p>
                        <h2 className="mt-4 text-3xl font-extrabold tracking-[-.045em] sm:text-4xl lg:text-[46px]">A purchased template becomes a reusable website design system.</h2>
                        <p className="mt-5 max-w-xl text-base leading-7 text-slate-300">When a website starts from the Marketplace, Cosmic can retain the installed design language for future pages. New content should feel like part of the original website—not a random collection of generic sections.</p>

                        <div className="mt-8 flex flex-wrap gap-2">
                            {designKitItems.map((item) => (
                                <span key={item} className="rounded-xl border border-white/10 bg-white/[.055] px-3.5 py-2 text-xs font-semibold text-slate-300">✓ {item}</span>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-[30px] border border-white/10 bg-white/[.055] p-4 shadow-[0_30px_100px_-50px_rgba(16,185,129,.45)] backdrop-blur-sm sm:p-6">
                        <div className="rounded-[24px] border border-white/10 bg-[#071a31] p-5 sm:p-6">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-300">Installed design kit</p>
                                    <h3 className="mt-2 text-xl font-semibold">Ember &amp; Olive</h3>
                                </div>
                                <span className="rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-1.5 text-[9px] font-extrabold text-emerald-300">Marketplace source</span>
                            </div>

                            <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                {[
                                    ['Aa', 'Typography'],
                                    ['●', 'Colors'],
                                    ['◫', 'Cards'],
                                    ['↗', 'Buttons'],
                                ].map(([icon, label]) => (
                                    <div key={label} className="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                                        <span className="mx-auto grid h-9 w-9 place-items-center rounded-xl bg-emerald-400/10 text-[11px] font-extrabold text-emerald-300">{icon}</span>
                                        <p className="mt-2 text-[9px] font-bold text-slate-300">{label}</p>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-3 rounded-2xl border border-emerald-300/15 bg-gradient-to-br from-emerald-400/10 to-white/[.035] p-4">
                                <div className="flex items-center gap-3">
                                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-400 text-sm font-extrabold text-[#052019]">＋</span>
                                    <div className="flex-1">
                                        <p className="text-sm font-extrabold">Create Catering page</p>
                                        <p className="mt-1 text-[10px] text-slate-400">Reuse installed page and section patterns</p>
                                    </div>
                                    <span className="text-emerald-300">→</span>
                                </div>
                            </div>

                            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                <FeaturePill icon="⌁" title="Preserve language" copy="New sections inherit the same visual rhythm and component styling." />
                                <FeaturePill icon="✦" title="Still customizable" copy="Luna can intentionally change the design when the user explicitly asks." />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-24">
                <div className="grid gap-10 lg:grid-cols-[.78fr_1.22fr] lg:items-start">
                    <div className="lg:sticky lg:top-28">
                        <p className="text-[11px] font-extrabold uppercase tracking-[.2em] text-emerald-700">Preview &amp; delivery</p>
                        <h2 className="mt-4 text-3xl font-extrabold tracking-[-.045em] text-[#07132c] sm:text-4xl">A launch workflow that stays connected to the build.</h2>
                        <p className="mt-5 text-base leading-7 text-slate-600">The final stage should not feel like a separate project. Cosmic keeps preview, responsive review, SEO fundamentals, and publishing close to the same website workflow.</p>
                        <Link href="/workflow" className="mt-6 inline-flex items-center gap-2 text-sm font-extrabold text-emerald-700 transition hover:text-emerald-800">Explore the complete workflow <span>→</span></Link>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {deliveryFeatures.map((item) => (
                            <article key={item.title} className="rounded-[24px] border border-slate-200 bg-gradient-to-br from-white to-slate-50/70 p-6 shadow-[0_20px_60px_-45px_rgba(15,23,42,.35)]">
                                <span className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-sm font-extrabold text-emerald-700 ring-1 ring-emerald-100">{item.icon}</span>
                                <h3 className="mt-5 text-lg font-semibold tracking-[-.025em] text-[#07132c]">{item.title}</h3>
                                <p className="mt-2 text-sm leading-6 text-slate-600">{item.copy}</p>
                            </article>
                        ))}
                    </div>
                </div>
            </section>

            <section className="border-y border-slate-200 bg-slate-50/60">
                <div className="mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-24">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-3xl">
                            <p className="text-[11px] font-extrabold uppercase tracking-[.2em] text-emerald-700">Built to grow with the website</p>
                            <h2 className="mt-4 text-3xl font-extrabold tracking-[-.045em] text-[#07132c] sm:text-4xl">More than a one-page AI generator.</h2>
                            <p className="mt-5 text-base leading-7 text-slate-600">Start with the website you need now, then expand into richer content, customer journeys, commerce, and multi-site workflows as the business requires them.</p>
                        </div>
                        <Link href="/pricing" className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-extrabold text-slate-800 transition hover:border-emerald-300 hover:text-emerald-800">Compare plans →</Link>
                    </div>

                    <div className="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {scaleFeatures.map((feature) => (
                            <article key={feature.title} className="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                                <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">{feature.eyebrow}</p>
                                <h3 className="mt-3 text-lg font-semibold tracking-[-.025em] text-[#07132c]">{feature.title}</h3>
                                <p className="mt-3 text-sm leading-6 text-slate-600">{feature.description}</p>
                            </article>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-[1240px] px-5 pt-16 sm:px-6 lg:px-8 lg:pt-20">
                <div className="rounded-[28px] border border-emerald-100 bg-[radial-gradient(circle_at_90%_0%,rgba(16,185,129,.09),transparent_28%),linear-gradient(135deg,#f5fbf8,#fff)] p-7 sm:p-9 lg:p-11">
                    <div className="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <p className="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">Everything stays connected</p>
                            <h2 className="mt-3 max-w-3xl text-2xl font-extrabold tracking-[-.04em] text-[#07132c] sm:text-3xl">The same website context follows from first prompt to future page.</h2>
                            <p className="mt-4 max-w-3xl text-sm leading-7 text-slate-600">That is the main advantage of treating AI, editing, design systems, content, and publishing as parts of one product rather than separate tools.</p>
                        </div>
                        <div className="flex flex-wrap gap-2 lg:max-w-[330px] lg:justify-end">
                            {['Prompt', 'Generate', 'Edit', 'Reuse', 'Preview', 'Publish'].map((item, index) => (
                                <span key={item} className={`rounded-xl px-3.5 py-2 text-xs font-extrabold ${index === 0 || index === 5 ? 'bg-emerald-700 text-white' : 'border border-slate-200 bg-white text-slate-600'}`}>{item}</span>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <PublicCta
                eyebrow="Build with a connected workflow"
                title="See what Cosmic can build from your business idea."
                description="Start with a short description, get a structured first draft, and keep refining the same website with Luna and the visual builder."
                primaryLabel="Create free demo"
                primaryHref="/start"
                secondaryLabel="View workflow"
                secondaryHref="/workflow"
            />
        </PublicSiteLayout>
    );
}
