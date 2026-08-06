import { Head, Link } from '@inertiajs/react';

export default function GuestLayout({ children, title = 'Welcome to Cosmic CMS', subtitle, wide = false, forceLight = false }) {
    return (
        <div className={`cosmic-guest-light ${forceLight ? 'cosmic-force-light' : ''} relative min-h-screen overflow-hidden bg-[#fbfffc] px-4 py-6 text-slate-900 sm:px-6 sm:py-10`}>
            <Head title={title} />

            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <div className="absolute left-[-8rem] top-[-10rem] h-96 w-96 rounded-full bg-emerald-100/75 blur-3xl" />
                <div className="absolute bottom-[-14rem] right-[-8rem] h-[30rem] w-[30rem] rounded-full bg-lime-100/60 blur-3xl" />
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(16,185,129,0.055)_1px,transparent_0)] bg-[size:28px_28px]" />
            </div>

            <div className={`relative mx-auto flex min-h-[calc(100vh-3rem)] flex-col justify-center ${wide ? 'max-w-4xl' : 'max-w-md'}`}>
                <Link href="/" className="mb-8 inline-flex w-fit items-center gap-3 rounded-xl outline-none transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 focus-visible:ring-offset-[#fbfffc]">
                    <span className="cosmic-guest-brand-mark flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-green-700 text-lg font-black text-white shadow-sm shadow-emerald-200" aria-hidden="true">✦</span>
                    <span>
                        <span className="block text-base font-black tracking-tight text-slate-950">Cosmic CMS</span>
                        <span className="block text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">AI website platform</span>
                    </span>
                </Link>

                <main className="rounded-3xl border border-emerald-100 bg-white/95 p-6 shadow-[0_24px_70px_rgba(15,23,42,0.10)] backdrop-blur sm:p-8">
                    <h1 className="text-2xl font-black tracking-tight text-slate-950">{title}</h1>
                    {subtitle && <p className="mt-2 text-sm leading-6 text-slate-600">{subtitle}</p>}
                    <div className="mt-7">{children}</div>
                </main>

                <div className="mt-6 flex items-center justify-center gap-4 text-xs text-slate-500">
                    <Link href="/pricing" className="hover:text-emerald-700">Pricing</Link>
                    <Link href="/privacy" className="hover:text-emerald-700">Privacy</Link>
                    <Link href="/terms" className="hover:text-emerald-700">Terms</Link>
                </div>
            </div>
        </div>
    );
}
