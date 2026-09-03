import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { PRIMARY_NAV, RESOURCE_GROUPS } from './publicNavigation';

function normalizePath(url = '/') {
    const path = String(url || '/').split('?')[0].split('#')[0] || '/';
    return path !== '/' ? path.replace(/\/$/, '') : '/';
}

function routeIsActive(currentPath, href) {
    const target = normalizePath(href);
    if (target === '/') return currentPath === '/';
    return currentPath === target || currentPath.startsWith(`${target}/`);
}

export default function PublicHeader({
    getStartedHref = '/pricing',
    sticky = true,
    className = '',
    logoSrc = null,
    logoAlt = 'Cosmic CMS',
}) {
    const page = usePage();
    const currentPath = normalizePath(page?.url || '/');
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [resourcesOpen, setResourcesOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const resourcesRef = useRef(null);
    const mobileTriggerRef = useRef(null);
    const mobileCloseRef = useRef(null);

    const resourceItems = RESOURCE_GROUPS.flatMap((group) => group.items);
    const resourcesActive = resourceItems.some((item) => routeIsActive(currentPath, item.href));

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    useEffect(() => {
        setResourcesOpen(false);
        setMobileMenuOpen(false);
    }, [currentPath]);

    useEffect(() => {
        if (!mobileMenuOpen) return undefined;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.requestAnimationFrame(() => mobileCloseRef.current?.focus());
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') {
                setMobileMenuOpen(false);
                window.requestAnimationFrame(() => mobileTriggerRef.current?.focus());
            }
        };
        window.addEventListener('keydown', closeOnEscape);
        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', closeOnEscape);
        };
    }, [mobileMenuOpen]);

    useEffect(() => {
        if (!resourcesOpen) return undefined;
        const close = (event) => {
            if (resourcesRef.current && !resourcesRef.current.contains(event.target)) setResourcesOpen(false);
        };
        const escape = (event) => {
            if (event.key === 'Escape') setResourcesOpen(false);
        };
        document.addEventListener('pointerdown', close);
        window.addEventListener('keydown', escape);
        return () => {
            document.removeEventListener('pointerdown', close);
            window.removeEventListener('keydown', escape);
        };
    }, [resourcesOpen]);

    const closeMenu = () => {
        setMobileMenuOpen(false);
        window.requestAnimationFrame(() => mobileTriggerRef.current?.focus());
    };

    const desktopNavClass = (active) => `relative rounded-lg px-1 py-2 text-[15px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-4 ${active ? 'text-emerald-800 after:absolute after:inset-x-1 after:-bottom-[17px] after:h-0.5 after:rounded-full after:bg-emerald-600' : 'text-slate-600 hover:text-slate-950'}`;

    return (
        <>
            <header className={`${sticky ? 'sticky top-0' : 'relative'} z-50 border-b transition-all duration-300 ${scrolled ? 'border-slate-200/90 bg-white/95 shadow-[0_14px_40px_-28px_rgba(15,23,42,.42)] backdrop-blur-xl' : 'border-slate-200/70 bg-white/90 backdrop-blur-lg'} ${className}`}>
                <div className="mx-auto flex min-h-[72px] max-w-[1440px] items-center justify-between px-5 py-2.5 sm:px-6 lg:px-8 xl:px-[50px]">
                    <Link href="/" className="flex min-w-0 items-center gap-3 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-4" aria-label="Cosmic CMS home">
                        {logoSrc ? (
                            <img src={logoSrc} alt={logoAlt} className="h-11 w-auto max-w-[190px] object-contain sm:h-12 sm:max-w-[220px]" />
                        ) : (
                            <>
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-green-700 text-lg font-bold text-white shadow-sm shadow-emerald-200">✦</span>
                                <span className="min-w-0">
                                    <span className="block truncate text-lg font-bold tracking-tight text-slate-950">Cosmic CMS</span>
                                    <span className="block truncate text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">AI website platform</span>
                                </span>
                            </>
                        )}
                    </Link>

                    <nav className="hidden items-center gap-7 lg:flex xl:gap-8" aria-label="Primary navigation">
                        {PRIMARY_NAV.map((item) => {
                            const active = routeIsActive(currentPath, item.href);
                            return (
                                <Link key={item.href} href={item.href} aria-current={active ? 'page' : undefined} className={desktopNavClass(active)}>
                                    {item.label}
                                </Link>
                            );
                        })}

                        <div ref={resourcesRef} className="relative">
                            <button
                                type="button"
                                onClick={() => setResourcesOpen((open) => !open)}
                                className={desktopNavClass(resourcesActive || resourcesOpen)}
                                aria-expanded={resourcesOpen}
                                aria-haspopup="true"
                                aria-controls="public-resources-menu"
                            >
                                <span className="inline-flex items-center gap-1.5">
                                    Resources
                                    <svg
                                        aria-hidden="true"
                                        viewBox="0 0 12 12"
                                        fill="none"
                                        className={`h-3 w-3 shrink-0 transition-transform duration-200 ${resourcesOpen ? 'rotate-180' : ''}`}
                                    >
                                        <path d="M2.5 4.5 6 8l3.5-3.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                </span>
                            </button>

                            <div
                                id="public-resources-menu"
                                aria-hidden={!resourcesOpen}
                                className={`absolute left-1/2 top-[calc(100%+18px)] w-[min(760px,calc(100vw-48px))] -translate-x-1/2 rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_30px_90px_-32px_rgba(15,23,42,.35)] transition duration-200 ${resourcesOpen ? 'visible translate-y-0 opacity-100' : 'invisible -translate-y-2 opacity-0'}`}
                            >
                                <div className="grid grid-cols-3 gap-4">
                                    {RESOURCE_GROUPS.map((group) => (
                                        <div key={group.label} className="min-w-0">
                                            <p className="px-3 text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">{group.label}</p>
                                            <div className="mt-2 space-y-1">
                                                {group.items.map((item) => {
                                                    const active = routeIsActive(currentPath, item.href);
                                                    return (
                                                        <Link
                                                            key={item.href}
                                                            href={item.href}
                                                            aria-current={active ? 'page' : undefined}
                                                            onClick={() => setResourcesOpen(false)}
                                                            className={`group block rounded-xl px-3 py-3 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 ${active ? 'bg-emerald-50 ring-1 ring-emerald-100' : 'hover:bg-emerald-50/70'}`}
                                                        >
                                                            <span className={`block text-sm font-bold ${active ? 'text-emerald-800' : 'text-slate-900 group-hover:text-emerald-800'}`}>{item.label}</span>
                                                            <span className="mt-1 block text-[11px] leading-4 text-slate-500">{item.description}</span>
                                                        </Link>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <div className="mt-4 flex items-center justify-between gap-5 rounded-2xl bg-[#07132c] px-5 py-4 text-white">
                                    <div className="min-w-0">
                                        <p className="text-sm font-bold">Need a premium starting point?</p>
                                        <p className="mt-1 text-[11px] text-slate-400">Browse complete Marketplace designs by industry.</p>
                                    </div>
                                    <Link href="/marketplace" onClick={() => setResourcesOpen(false)} className="shrink-0 rounded-lg bg-emerald-500 px-4 py-2 text-xs font-bold text-white transition hover:bg-emerald-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">Marketplace →</Link>
                                </div>
                            </div>
                        </div>
                    </nav>

                    <div className="hidden items-center gap-2 lg:flex">
                        <Link href="/login" className="rounded-xl px-4 py-2.5 text-[15px] font-bold text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">Log in</Link>
                        <Link href={getStartedHref} className="rounded-xl bg-emerald-700 px-5 py-2.5 text-[15px] font-bold text-white shadow-sm shadow-emerald-100 transition hover:-translate-y-0.5 hover:bg-emerald-800 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">Get started</Link>
                    </div>

                    <button
                        ref={mobileTriggerRef}
                        type="button"
                        onClick={() => setMobileMenuOpen(true)}
                        className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-900 shadow-sm transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 lg:hidden"
                        aria-label="Open navigation"
                        aria-expanded={mobileMenuOpen}
                        aria-controls="public-mobile-navigation"
                    >
                        <span aria-hidden="true" className="text-xl leading-none">☰</span>
                    </button>
                </div>
            </header>

            <div id="public-mobile-navigation" className={`fixed inset-0 z-[90] lg:hidden ${mobileMenuOpen ? 'pointer-events-auto' : 'pointer-events-none'}`} aria-hidden={!mobileMenuOpen}>
                <button type="button" onClick={closeMenu} tabIndex={mobileMenuOpen ? 0 : -1} className={`absolute inset-0 bg-slate-950/45 backdrop-blur-[2px] transition-opacity duration-300 ${mobileMenuOpen ? 'opacity-100' : 'opacity-0'}`} aria-label="Close navigation" />
                <aside className={`absolute right-0 top-0 flex h-full w-[min(92vw,410px)] flex-col border-l border-slate-200 bg-white shadow-2xl transition-transform duration-300 ease-out ${mobileMenuOpen ? 'translate-x-0' : 'translate-x-full'}`}>
                    <div className="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
                        <Link href="/" onClick={closeMenu} tabIndex={mobileMenuOpen ? 0 : -1} className="min-w-0 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                            {logoSrc ? <img src={logoSrc} alt={logoAlt} className="h-10 w-auto max-w-[180px] object-contain" /> : <p className="text-base font-bold text-slate-950">Cosmic CMS</p>}
                        </Link>
                        <button ref={mobileCloseRef} type="button" onClick={closeMenu} tabIndex={mobileMenuOpen ? 0 : -1} className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-xl text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="Close navigation">×</button>
                    </div>

                    <nav className="flex flex-1 flex-col overflow-y-auto px-4 py-5" aria-label="Mobile navigation">
                        <div className="space-y-1">
                            {PRIMARY_NAV.map((item) => {
                                const active = routeIsActive(currentPath, item.href);
                                return (
                                    <Link key={item.href} href={item.href} aria-current={active ? 'page' : undefined} onClick={closeMenu} tabIndex={mobileMenuOpen ? 0 : -1} className={`block rounded-xl px-4 py-3 text-base font-bold transition ${active ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100' : 'text-slate-800 hover:bg-emerald-50 hover:text-emerald-800'}`}>{item.label}</Link>
                                );
                            })}
                        </div>

                        <div className="my-5 border-t border-slate-100" />
                        <p className="px-4 text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">Resources</p>
                        <div className="mt-3 space-y-5">
                            {RESOURCE_GROUPS.map((group) => (
                                <div key={group.label}>
                                    <p className="px-4 text-[10px] font-black uppercase tracking-[.15em] text-slate-400">{group.label}</p>
                                    <div className="mt-1 space-y-1">
                                        {group.items.map((item) => {
                                            const active = routeIsActive(currentPath, item.href);
                                            return (
                                                <Link key={`${item.href}-${item.label}`} href={item.href} aria-current={active ? 'page' : undefined} onClick={closeMenu} tabIndex={mobileMenuOpen ? 0 : -1} className={`block rounded-xl px-4 py-3 transition ${active ? 'bg-emerald-50 ring-1 ring-emerald-100' : 'hover:bg-emerald-50'}`}>
                                                    <span className={`block text-sm font-bold ${active ? 'text-emerald-800' : 'text-slate-800'}`}>{item.label}</span>
                                                    <span className="mt-0.5 block text-[11px] leading-4 text-slate-500">{item.description}</span>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </nav>

                    <div className="border-t border-slate-200 bg-slate-50/70 p-4 sm:p-5">
                        <div className="grid grid-cols-2 gap-3">
                            <Link href="/login" onClick={closeMenu} tabIndex={mobileMenuOpen ? 0 : -1} className="flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-900">Log in</Link>
                            <Link href={getStartedHref} onClick={closeMenu} tabIndex={mobileMenuOpen ? 0 : -1} className="flex min-h-12 items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-bold text-white shadow-sm">Get started</Link>
                        </div>
                    </div>
                </aside>
            </div>
        </>
    );
}
