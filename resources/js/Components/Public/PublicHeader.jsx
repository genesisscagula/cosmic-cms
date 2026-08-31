import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const NAV_ITEMS = [
    { label: 'Features', href: '/#features' },
    { label: 'Workflow', href: '/#workflow' },
    { label: 'Pricing', href: '/pricing' },
];

export default function PublicHeader({
    getStartedHref = '/pricing',
    sticky = true,
    className = '',
    logoSrc = null,
    logoAlt = 'Cosmic CMS',
}) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    useEffect(() => {
        if (!mobileMenuOpen) return undefined;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        const closeOnEscape = (event) => {
            if (event.key === 'Escape') setMobileMenuOpen(false);
        };

        window.addEventListener('keydown', closeOnEscape);
        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', closeOnEscape);
        };
    }, [mobileMenuOpen]);

    const closeMenu = () => setMobileMenuOpen(false);

    return (
        <>
            <header className={`${sticky ? 'sticky top-0' : 'relative'} z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl ${className}`}>
                <div className="mx-auto flex min-h-[72px] max-w-[1440px] items-center justify-between px-5 py-3 sm:px-6 lg:px-8">
                    <Link href="/" className="flex items-center gap-3" aria-label="Cosmic CMS home">
                        {logoSrc ? (
                            <img src={logoSrc} alt={logoAlt} className="h-14 w-auto max-w-[270px] object-contain sm:h-[3.75rem]" />
                        ) : (
                            <>
                                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-green-700 text-lg font-bold text-white shadow-sm shadow-emerald-200">
                                    ✦
                                </span>
                                <span>
                                    <span className="block text-lg font-bold tracking-tight text-slate-950">Cosmic CMS</span>
                                    <span className="block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">AI website platform</span>
                                </span>
                            </>
                        )}
                    </Link>

                    <nav className="hidden items-center gap-9 lg:flex" aria-label="Primary navigation">
                        {NAV_ITEMS.map((item) => (
                            <a
                                key={item.href}
                                href={item.href}
                                className="text-[15px] font-semibold text-slate-600 transition hover:text-slate-950"
                            >
                                {item.label}
                            </a>
                        ))}
                    </nav>

                    <div className="hidden items-center gap-3 lg:flex">
                        <Link href="/login" className="rounded-lg px-4 py-2 text-[15px] font-bold text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">
                            Log in
                        </Link>
                        <Link href={getStartedHref} className="rounded-lg bg-emerald-700 px-5 py-2.5 text-[15px] font-bold text-white shadow-sm shadow-emerald-100 transition hover:bg-emerald-800">
                            Get started
                        </Link>
                    </div>

                    <button
                        type="button"
                        onClick={() => setMobileMenuOpen(true)}
                        className="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-900 shadow-sm transition hover:bg-slate-50 lg:hidden"
                        aria-label="Open navigation"
                        aria-expanded={mobileMenuOpen}
                    >
                        <span className="text-xl leading-none">☰</span>
                    </button>
                </div>
            </header>

            <div
                className={`fixed inset-0 z-[90] lg:hidden ${mobileMenuOpen ? 'pointer-events-auto' : 'pointer-events-none'}`}
                aria-hidden={!mobileMenuOpen}
            >
                <button
                    type="button"
                    onClick={closeMenu}
                    className={`absolute inset-0 bg-slate-950/45 backdrop-blur-[2px] transition-opacity duration-300 ${mobileMenuOpen ? 'opacity-100' : 'opacity-0'}`}
                    aria-label="Close navigation"
                />

                <aside
                    className={`absolute right-0 top-0 flex h-full w-[min(88vw,360px)] flex-col border-l border-slate-200 bg-white shadow-2xl transition-transform duration-300 ease-out ${mobileMenuOpen ? 'translate-x-0' : 'translate-x-full'}`}
                >
                    <div className="flex items-center justify-between border-b border-slate-200 px-5 py-5">
                        <div>
                            <p className="text-base font-bold text-slate-950">Cosmic CMS</p>
                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Navigation</p>
                        </div>
                        <button
                            type="button"
                            onClick={closeMenu}
                            className="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-xl text-slate-700 transition hover:bg-slate-50"
                            aria-label="Close navigation"
                        >
                            ×
                        </button>
                    </div>

                    <nav className="flex flex-1 flex-col gap-2 overflow-y-auto px-5 py-6" aria-label="Mobile navigation">
                        {NAV_ITEMS.map((item) => (
                            <a
                                key={item.href}
                                href={item.href}
                                onClick={closeMenu}
                                className="cosmic-public-mobile-link rounded-xl px-4 py-3 text-base font-bold text-slate-800 transition hover:bg-emerald-50 hover:text-emerald-800"
                            >
                                {item.label}
                            </a>
                        ))}
                    </nav>

                    <div className="border-t border-slate-200 p-5">
                        <div className="grid gap-3">
                            <Link href="/login" onClick={closeMenu} className="flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-bold text-slate-900">
                                Log in
                            </Link>
                            <Link href={getStartedHref} onClick={closeMenu} className="flex min-h-12 items-center justify-center rounded-xl bg-emerald-700 px-5 text-sm font-bold text-white shadow-sm">
                                Get started
                            </Link>
                        </div>
                    </div>
                </aside>
            </div>
        </>
    );
}
