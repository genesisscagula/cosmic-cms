import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicSiteLayout from '@/Components/Public/PublicSiteLayout';

export default function NotFound() {
    return (
        <PublicSiteLayout>
            <SeoHead
                title="Page Not Found | Cosmic CMS"
                description="The page you were looking for could not be found. Explore Cosmic CMS features, guides, templates, or return to the homepage."
                path="/404"
                noIndex
            />
            <section className="relative overflow-hidden bg-[radial-gradient(circle_at_78%_22%,rgba(16,185,129,.16),transparent_28%),linear-gradient(180deg,#fff_0%,#f5faf8_100%)]">
                <div className="pointer-events-none absolute -right-24 top-16 h-80 w-80 rounded-full border border-emerald-200/60" />
                <div className="pointer-events-none absolute right-12 top-36 h-44 w-44 rounded-full border border-emerald-200/40" />
                <div className="mx-auto grid min-h-[68vh] max-w-[1240px] items-center gap-10 px-5 py-16 sm:px-6 lg:grid-cols-[.9fr_1.1fr] lg:px-8 lg:py-24">
                    <div className="relative z-10 max-w-2xl">
                        <span className="inline-flex items-center rounded-full border border-emerald-200 bg-white px-4 py-2 text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700 shadow-sm">✦ Off course</span>
                        <p className="mt-7 text-7xl font-black tracking-[-.07em] text-emerald-700 sm:text-8xl">404</p>
                        <h1 className="mt-3 text-4xl font-extrabold leading-tight tracking-[-.045em] text-[#07132c] sm:text-5xl">This page drifted out of orbit.</h1>
                        <p className="mt-5 max-w-xl text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">The link may have changed, or the page may no longer exist. Head back home or jump into one of the main Cosmic CMS resources.</p>
                        <div className="mt-8 grid gap-3 sm:flex sm:flex-wrap">
                            <Link href="/" className="inline-flex min-h-12 items-center justify-center rounded-xl bg-emerald-700 px-6 text-sm font-bold text-white transition hover:bg-emerald-800">Back to homepage →</Link>
                            <Link href="/help" className="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-6 text-sm font-bold text-slate-800 transition hover:border-emerald-200 hover:text-emerald-800">Open Help Center</Link>
                        </div>
                    </div>

                    <div className="relative mx-auto hidden w-full max-w-[520px] lg:block" aria-hidden="true">
                        <div className="absolute inset-8 rounded-full bg-emerald-300/15 blur-3xl" />
                        <div className="relative aspect-square overflow-hidden rounded-full border border-emerald-100 bg-[radial-gradient(circle_at_38%_32%,rgba(52,211,153,.34),transparent_13%),radial-gradient(circle_at_58%_60%,rgba(16,185,129,.16),transparent_21%),linear-gradient(145deg,#06142d,#082b39_58%,#064e3b)] shadow-[0_40px_100px_-45px_rgba(4,47,46,.65)]">
                            <div className="absolute left-[18%] top-[23%] h-2 w-2 rounded-full bg-white/80 shadow-[120px_34px_0_0_rgba(255,255,255,.55),210px_90px_0_0_rgba(255,255,255,.7),60px_210px_0_0_rgba(255,255,255,.5)]" />
                            <div className="absolute left-[24%] top-[42%] h-[3px] w-[58%] -rotate-[24deg] rounded-full bg-gradient-to-r from-transparent via-emerald-300 to-transparent shadow-[0_0_18px_rgba(52,211,153,.6)]" />
                            <div className="absolute left-[44%] top-[35%] grid h-24 w-24 rotate-[28deg] place-items-center rounded-[34%_58%_48%_42%] border border-white/25 bg-white/90 text-4xl shadow-2xl">✦</div>
                            <div className="absolute -bottom-[34%] left-[10%] h-[58%] w-[82%] rounded-[50%] border border-emerald-300/20 bg-emerald-100/10" />
                        </div>
                    </div>
                </div>
            </section>
        </PublicSiteLayout>
    );
}
