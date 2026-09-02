import { Link } from '@inertiajs/react';
import cosmicLogo from '../../../images/cosmic-cms-logo.png';
import { FOOTER_GROUPS } from './publicNavigation';

export default function PublicFooter() {
    return (
        <footer className="relative overflow-hidden border-t border-white/10 bg-[#06132d] text-white">
            <div className="pointer-events-none absolute -right-32 top-10 h-80 w-80 rounded-full border border-emerald-300/10" />
            <div className="pointer-events-none absolute -bottom-28 right-20 h-64 w-64 rounded-full bg-emerald-400/[.04] blur-3xl" />

            <div className="relative mx-auto grid max-w-[1240px] gap-10 px-5 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-[1.45fr_repeat(3,1fr)] lg:px-8 lg:py-14">
                <div className="max-w-sm sm:col-span-2 lg:col-span-1">
                    <Link href="/" className="inline-flex rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-4 focus-visible:ring-offset-[#06132d]" aria-label="Cosmic CMS home">
                        <img src={cosmicLogo} alt="Cosmic CMS" className="h-11 w-auto object-contain brightness-0 invert sm:h-12" />
                    </Link>
                    <p className="mt-4 max-w-xs text-sm leading-6 text-slate-400">AI-assisted website creation, publishing, and content management for modern businesses and agencies.</p>
                    <div className="mt-6 flex flex-wrap gap-2">
                        {['AI-assisted', 'Visual editing', 'Publish ready'].map((item) => (
                            <span key={item} className="rounded-full border border-white/10 bg-white/[.04] px-3 py-1.5 text-[10px] font-bold text-slate-300">{item}</span>
                        ))}
                    </div>
                </div>
                {FOOTER_GROUPS.map((group) => (
                    <div key={group.label} className="min-w-0">
                        <p className="text-xs font-extrabold uppercase tracking-[.18em] text-emerald-300">{group.label}</p>
                        <div className="mt-4 space-y-3">
                            {group.items.map((item) => (
                                <Link key={item.href} href={item.href} className="block w-fit rounded-md text-sm font-semibold text-slate-300 transition hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">{item.label}</Link>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
            <div className="relative border-t border-white/10">
                <div className="mx-auto flex max-w-[1240px] flex-col gap-4 px-5 py-5 text-xs text-slate-500 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                    <p>© {new Date().getFullYear()} Cosmic CMS. All rights reserved.</p>
                    <div className="flex flex-wrap gap-x-5 gap-y-2">
                        <Link href="/privacy" className="rounded transition hover:text-slate-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">Privacy Policy</Link>
                        <Link href="/terms" className="rounded transition hover:text-slate-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">Terms of Service</Link>
                    </div>
                </div>
            </div>
        </footer>
    );
}
