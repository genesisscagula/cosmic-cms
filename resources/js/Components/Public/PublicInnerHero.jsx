import PublicBreadcrumbs from './PublicBreadcrumbs';

export default function PublicInnerHero({
    eyebrow = 'Cosmic CMS',
    title,
    highlight = '',
    description,
    breadcrumbs = [],
    children,
}) {
    return (
        <section className="relative overflow-hidden border-b border-slate-200 bg-[radial-gradient(circle_at_82%_18%,rgba(16,185,129,.13),transparent_28%),linear-gradient(180deg,#ffffff_0%,#f8fbfa_100%)]">
            <div className="pointer-events-none absolute -right-28 top-8 h-80 w-80 rounded-full border border-emerald-100/80" />
            <div className="pointer-events-none absolute -right-12 top-28 h-48 w-48 rounded-full border border-emerald-200/50" />
            <div className="pointer-events-none absolute left-[8%] top-16 h-24 w-24 rounded-full bg-emerald-200/20 blur-3xl" />
            <div className="relative mx-auto max-w-[1240px] px-5 py-12 sm:px-6 sm:py-16 lg:px-8 lg:py-20 xl:py-24">
                <PublicBreadcrumbs items={breadcrumbs} />
                <div className="mt-7 max-w-4xl sm:mt-8">
                    <span className="inline-flex max-w-full items-center rounded-full border border-emerald-200 bg-white/85 px-3.5 py-2 text-[10px] font-extrabold uppercase tracking-[.16em] text-emerald-700 shadow-sm sm:px-4 sm:text-[11px] sm:tracking-[.2em]">
                        <span aria-hidden="true">✦</span><span className="ml-2 truncate">{eyebrow}</span>
                    </span>
                    <h1 className="mt-5 max-w-4xl text-[clamp(2.35rem,8vw,3.5rem)] font-extrabold leading-[1.03] tracking-[-.045em] text-[#07132c] sm:mt-6 lg:text-6xl">
                        {title}{highlight ? <> <span className="text-emerald-700">{highlight}</span></> : null}
                    </h1>
                    {description && <p className="mt-5 max-w-3xl text-base leading-7 text-slate-600 sm:mt-6 sm:text-lg sm:leading-8">{description}</p>}
                    {children && <div className="mt-7 sm:mt-8">{children}</div>}
                </div>
            </div>
        </section>
    );
}
