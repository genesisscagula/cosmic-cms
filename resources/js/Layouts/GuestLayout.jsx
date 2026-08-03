import { Head, Link } from '@inertiajs/react';

export default function GuestLayout({ children, title = 'Welcome to Cosmic CMS', subtitle, wide = false }) {
    return (
        <div className="relative min-h-screen overflow-hidden bg-[#09090b] px-4 py-6 text-slate-100 sm:px-6 sm:py-10">
            <Head title={title} />

            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <div className="absolute left-[8%] top-[-10rem] h-80 w-80 rounded-full bg-emerald-500/20 blur-3xl" />
                <div className="absolute bottom-[-12rem] right-[8%] h-96 w-96 rounded-full bg-cyan-400/15 blur-3xl" />
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.15),transparent_38%)]" />
            </div>

            <div className={`relative mx-auto flex min-h-[calc(100vh-3rem)] flex-col justify-center ${wide ? 'max-w-4xl' : 'max-w-md'}`}>
                <Link href="/" className="mb-8 inline-flex w-fit items-center gap-3 rounded-xl outline-none transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-2 focus-visible:ring-offset-[#09090b]">
                    <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-cyan-300 text-sm font-black text-slate-950 shadow-lg shadow-emerald-400/25">C</span>
                    <span>
                        <span className="block text-base font-bold tracking-tight text-white">Cosmic <span className="text-emerald-300">CMS</span></span>
                        <span className="block text-xs text-slate-400">AI website builder</span>
                    </span>
                </Link>

                <main className="rounded-2xl border border-white/10 bg-[#18181b]/95 p-6 shadow-2xl shadow-black/30 backdrop-blur sm:p-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-white">{title}</h1>
                    {subtitle && <p className="mt-2 text-sm leading-6 text-slate-400">{subtitle}</p>}
                    <div className="mt-7">{children}</div>
                </main>

                <p className="mt-6 text-center text-xs text-slate-500">Build, edit, and publish with confidence.</p>
            </div>
        </div>
    );
}
