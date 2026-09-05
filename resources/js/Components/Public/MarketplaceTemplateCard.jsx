import { Link } from '@inertiajs/react';

const previewThemes = [
    {
        shell: 'from-[#f4eadb] via-[#fffaf2] to-[#d8b993]',
        accent: 'bg-[#6d412b]',
        soft: 'bg-[#ead9c3]',
        dark: 'bg-[#2c201b]',
    },
    {
        shell: 'from-[#e7f4ef] via-white to-[#bfded1]',
        accent: 'bg-[#0f766e]',
        soft: 'bg-[#cfe8df]',
        dark: 'bg-[#123c3a]',
    },
    {
        shell: 'from-[#edf1fb] via-white to-[#cad4ee]',
        accent: 'bg-[#4656a6]',
        soft: 'bg-[#dce3f5]',
        dark: 'bg-[#17223f]',
    },
    {
        shell: 'from-[#f8ecef] via-white to-[#e8c7cf]',
        accent: 'bg-[#9f4660]',
        soft: 'bg-[#f1dce2]',
        dark: 'bg-[#40202b]',
    },
];

function FallbackPreview({ index = 0, name = 'Premium website' }) {
    const theme = previewThemes[index % previewThemes.length];

    return (
        <div className={`relative h-full min-h-[235px] overflow-hidden bg-gradient-to-br ${theme.shell}`}>
            <div className="absolute inset-x-0 top-0 flex h-10 items-center justify-between border-b border-black/5 bg-white/70 px-4 backdrop-blur-sm">
                <div className="flex items-center gap-2">
                    <span className={`h-4 w-4 rounded-full ${theme.accent}`} />
                    <span className="max-w-[120px] truncate text-[8px] font-black uppercase tracking-[.13em] text-slate-700">{name}</span>
                </div>
                <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                    <span className="h-1.5 w-8 rounded-full bg-slate-300" />
                    <span className="h-1.5 w-6 rounded-full bg-slate-300" />
                    <span className={`h-5 w-12 rounded-full ${theme.accent}`} />
                </div>
            </div>

            <div className="grid h-full min-h-[235px] grid-cols-[1.05fr_.95fr] gap-4 px-5 pb-5 pt-14">
                <div className="flex flex-col justify-center">
                    <span className={`mb-3 h-2 w-14 rounded-full ${theme.soft}`} />
                    <span className={`h-5 w-[86%] rounded-md ${theme.dark}`} />
                    <span className={`mt-2 h-5 w-[70%] rounded-md ${theme.dark}`} />
                    <span className="mt-4 h-2 w-[88%] rounded-full bg-slate-400/25" />
                    <span className="mt-2 h-2 w-[74%] rounded-full bg-slate-400/20" />
                    <div className="mt-5 flex gap-2">
                        <span className={`h-7 w-20 rounded-full ${theme.accent}`} />
                        <span className="h-7 w-16 rounded-full border border-slate-400/25 bg-white/60" />
                    </div>
                </div>
                <div className="relative flex items-center justify-center">
                    <div className={`absolute h-[74%] w-[78%] rotate-3 rounded-[28px] ${theme.soft}`} />
                    <div className="relative h-[72%] w-[72%] overflow-hidden rounded-[26px] border border-white/70 bg-white/65 shadow-[0_18px_35px_-20px_rgba(15,23,42,.4)]">
                        <div className={`h-[58%] ${theme.accent} opacity-85`} />
                        <div className="space-y-2 p-3">
                            <span className={`block h-2 w-3/4 rounded-full ${theme.dark}`} />
                            <span className="block h-1.5 w-full rounded-full bg-slate-400/20" />
                            <span className="block h-1.5 w-4/5 rounded-full bg-slate-400/20" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function MarketplaceTemplateCard({ template, index = 0, example = false }) {
    const detailPath = template?.detailPath || '/marketplace/templates';
    const demoPath = template?.demoPath || detailPath;
    const title = template?.name || `Premium website ${index + 1}`;
    const industry = template?.industryLabel || 'Business';
    const style = template?.style ? template.style.replaceAll('-', ' ') : 'Premium';

    return (
        <article className="group overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-[0_18px_55px_-38px_rgba(15,23,42,.45)] transition duration-300 hover:-translate-y-1 hover:border-emerald-200 hover:shadow-[0_30px_70px_-38px_rgba(4,120,87,.32)]">
            <Link href={demoPath} className="block overflow-hidden border-b border-slate-200 bg-slate-100" aria-label={`Preview ${title}`}>
                <div className="aspect-[16/10] overflow-hidden">
                    {template?.image ? (
                        <img src={template.image} alt={`${title} website preview`} className="h-full w-full object-cover object-top transition duration-500 group-hover:scale-[1.02]" loading="lazy" decoding="async" />
                    ) : (
                        <FallbackPreview index={index} name={title} />
                    )}
                </div>
            </Link>

            <div className="p-5 sm:p-6">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-[.14em] text-emerald-700">{industry}</span>
                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-black capitalize text-slate-500">{style}</span>
                    {template?.pages ? <span className="text-[10px] font-bold text-slate-400">{template.pages} pages</span> : null}
                </div>

                <div className="mt-4 flex items-start justify-between gap-4">
                    <div>
                        <h3 className="text-xl font-semibold tracking-[-.025em] text-[#07132c]">{title}</h3>
                        <p className="mt-2 line-clamp-2 text-sm leading-6 text-slate-500">
                            {template?.copy || (example ? 'A complete website direction with a consistent visual system across every page.' : 'A polished Marketplace starting point ready to personalize for your business.')}
                        </p>
                    </div>
                    {template?.featured ? <span className="shrink-0 rounded-lg bg-[#07132c] px-2 py-1 text-[8px] font-black uppercase tracking-[.12em] text-white">Featured</span> : null}
                </div>

                <div className="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        {Number(template?.creditPrice || 0) > 0 ? (
                            <p className="text-sm font-black text-[#07132c]">{Number(template.creditPrice).toLocaleString()} <span className="text-[10px] font-bold text-slate-400">Cosmic Credits</span></p>
                        ) : null}
                        <div className="mt-1 flex min-w-0 flex-wrap items-center gap-2 text-[10px] font-bold text-slate-400">
                            {template?.aiPersonalization !== false && <span>✦ AI setup</span>}
                            {template?.websiteCare && <span>· Website care</span>}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link href={demoPath} className="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 px-3 text-[10px] font-black text-slate-700 transition hover:border-emerald-200 hover:text-emerald-800">Live demo</Link>
                        <Link href={detailPath} className="inline-flex min-h-10 items-center justify-center rounded-xl bg-emerald-700 px-3 text-[10px] font-black text-white transition hover:bg-emerald-800">Details →</Link>
                    </div>
                </div>
            </div>
        </article>
    );
}
