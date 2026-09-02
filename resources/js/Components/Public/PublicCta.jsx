import { Link } from '@inertiajs/react';

export default function PublicCta({
    eyebrow = 'Your next website can start today',
    title = 'Ready to launch your next website?',
    description = 'Turn a short business description into a polished, editable website and keep refining it with Luna.',
    primaryLabel = 'Create free demo',
    primaryHref = '/start',
    secondaryLabel = 'View plans',
    secondaryHref = '/pricing',
}) {
    return (
        <section className="mx-auto max-w-[1240px] px-5 py-14 sm:px-6 sm:py-16 lg:px-8 lg:py-20">
            <div className="relative overflow-hidden rounded-[26px] bg-[radial-gradient(circle_at_85%_35%,rgba(52,211,153,.24),transparent_25%),linear-gradient(115deg,#06132d_0%,#062d38_58%,#064e3b_100%)] px-6 py-9 text-white shadow-[0_28px_90px_-38px_rgba(2,32,44,.7)] sm:rounded-[30px] sm:px-10 sm:py-10 lg:px-14 lg:py-14">
                <div className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full border border-emerald-300/20" />
                <div className="pointer-events-none absolute -bottom-24 right-[18%] h-56 w-56 rounded-full bg-emerald-300/10 blur-3xl" />
                <div className="relative max-w-2xl">
                    <p className="text-[10px] font-extrabold uppercase tracking-[.16em] text-emerald-300 sm:text-[11px] sm:tracking-[.2em]">{eyebrow}</p>
                    <h2 className="mt-4 text-3xl font-extrabold leading-tight tracking-[-.04em] sm:text-4xl">{title}</h2>
                    <p className="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base">{description}</p>
                    <div className="mt-7 grid gap-3 sm:flex sm:flex-wrap">
                        <Link href={primaryHref} className="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-emerald-500 px-6 text-sm font-bold text-white shadow-lg shadow-emerald-950/20 transition hover:-translate-y-0.5 hover:bg-emerald-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white sm:w-auto">{primaryLabel} →</Link>
                        <Link href={secondaryHref} className="inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-white/20 bg-white/5 px-6 text-sm font-bold text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white sm:w-auto">{secondaryLabel}</Link>
                    </div>
                </div>
            </div>
        </section>
    );
}
