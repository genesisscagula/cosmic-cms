import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import TemplateSiteRenderer from './TemplateSiteRenderer';
import cosmicLogo from '../../../images/cosmic-cms-logo.png';

const deviceWidths = {
    desktop: '100%',
    tablet: '820px',
    mobile: '390px',
};

function MarketplaceLogo({ href }) {
    return (
        <Link href={href} className="flex min-w-0 items-center gap-3" aria-label="Cosmic CMS Marketplace home">
            <img src={cosmicLogo} alt="Cosmic CMS" className="h-9 w-auto max-w-[130px] object-contain brightness-0 invert" />
            <span className="hidden h-7 w-px bg-white/15 sm:block" />
            <span className="hidden text-[9px] font-black uppercase tracking-[.24em] text-white/75 sm:block">Marketplace</span>
        </Link>
    );
}

export default function MarketplaceTemplateDemo({
    canonicalUrl = 'https://marketplace.cosmiccms.com',
    template,
    embed = false,
    marketplaceHome = '/marketplace',
    catalogPath = '/marketplace/templates',
    seoPath = '/templates',
}) {
    const [device, setDevice] = useState('desktop');
    const currentSlug = template?.current_page?.slug || 'home';
    const pages = Array.isArray(template?.pages) ? template.pages : [];
    const frameWidth = deviceWidths[device] || deviceWidths.desktop;
    const selectedValue = currentSlug;
    const title = `${template?.name || 'Website'} — ${template?.current_page?.name || 'Preview'} | Cosmic CMS Marketplace`;

    const pageOptions = useMemo(() => pages.map((page) => ({
        ...page,
        label: page.parent_slug ? `↳ ${page.name}` : page.name,
    })), [pages]);

    if (embed) {
        return (
            <>
                <Head title={title}>
                    <meta head-key="robots" name="robots" content="noindex,nofollow" />
                    <meta head-key="theme-color" name="theme-color" content="#ffffff" />
                </Head>
                <TemplateSiteRenderer template={template} embed />
            </>
        );
    }

    return (
        <>
            <Head title={title}>
                <meta head-key="robots" name="robots" content="noindex,nofollow" />
                <link head-key="canonical" rel="canonical" href={`${String(canonicalUrl).replace(/\/$/, '')}${seoPath}`} />
                <meta head-key="theme-color" name="theme-color" content="#070b1d" />
            </Head>
            <div className="flex min-h-screen flex-col bg-[#e8eaf0] text-slate-900" data-marketplace-surface="1">
                <header className="z-50 border-b border-white/10 bg-[#060b1d] text-white shadow-lg">
                    <div className="flex min-h-[72px] items-center gap-3 px-4 sm:px-5">
                        <div className="hidden min-w-[200px] xl:block"><MarketplaceLogo href={marketplaceHome} /></div>
                        <Link href={template.detail_path} className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-white/15 text-lg text-white/80 transition hover:bg-white/10 hover:text-white" aria-label="Back to template details">←</Link>
                        <div className="min-w-0 flex-1 sm:flex-none sm:min-w-[190px]"><p className="truncate text-sm font-black">{template.name}</p><p className="truncate text-[10px] font-bold uppercase tracking-[.14em] text-white/45">{template.current_page?.name}</p></div>

                        <div className="mx-auto hidden items-center gap-1 rounded-xl border border-white/10 bg-white/5 p-1 md:flex">
                            {[['desktop', '▱', 'Desktop'], ['tablet', '▯', 'Tablet'], ['mobile', '▯', 'Mobile']].map(([key, icon, label]) => <button key={key} type="button" onClick={() => setDevice(key)} title={label} className={`grid h-9 min-w-10 place-items-center rounded-lg px-3 text-xs font-black transition ${device === key ? 'bg-white text-slate-950 shadow-sm' : 'text-white/60 hover:bg-white/10 hover:text-white'}`}>{icon}<span className="sr-only">{label}</span></button>)}
                        </div>

                        <select value={selectedValue} onChange={(event) => { const target = pages.find((page) => page.slug === event.target.value); if (target?.demo_path) router.visit(target.demo_path); }} className="hidden h-10 max-w-[210px] rounded-xl border-white/15 bg-white/10 px-3 text-xs font-black text-white focus:border-violet-400 focus:ring-violet-400 lg:block">
                            {pageOptions.map((page) => <option key={page.slug} value={page.slug} className="text-slate-900">{page.label}</option>)}
                        </select>

                        <div className="ml-auto flex items-center gap-2"><Link href={catalogPath} className="hidden min-h-10 items-center rounded-xl px-3 text-xs font-black text-white/60 hover:bg-white/10 hover:text-white sm:flex">All Templates</Link><Link href={template.checkout_path} className="flex min-h-10 items-center justify-center rounded-xl bg-violet-600 px-4 text-xs font-black text-white shadow-lg shadow-violet-950/30 transition hover:bg-violet-500 sm:px-5">Get This Website</Link></div>
                    </div>
                    <div className="flex items-center gap-2 border-t border-white/10 px-4 py-2 md:hidden"><select value={selectedValue} onChange={(event) => { const target = pages.find((page) => page.slug === event.target.value); if (target?.demo_path) router.visit(target.demo_path); }} className="h-9 min-w-0 flex-1 rounded-lg border-white/15 bg-white/10 px-3 text-xs font-black text-white focus:border-violet-400 focus:ring-violet-400">{pageOptions.map((page) => <option key={page.slug} value={page.slug} className="text-slate-900">{page.label}</option>)}</select><div className="flex rounded-lg bg-white/5 p-0.5">{Object.keys(deviceWidths).map((key) => <button key={key} type="button" onClick={() => setDevice(key)} className={`h-8 rounded-md px-2 text-[10px] font-black uppercase ${device === key ? 'bg-white text-slate-950' : 'text-white/55'}`}>{key[0]}</button>)}</div></div>
                </header>

                <main className="relative flex flex-1 justify-center overflow-auto bg-[radial-gradient(circle_at_center_top,rgba(255,255,255,.9),rgba(226,232,240,.74)_48%,rgba(203,213,225,.7))] p-3 sm:p-5 lg:p-7">
                    <div className="mx-auto flex w-full justify-center">
                        <div className={`overflow-hidden bg-white shadow-[0_28px_80px_rgba(15,23,42,.22)] transition-all duration-300 ${device === 'desktop' ? 'rounded-xl' : 'rounded-[26px] border-[8px] border-slate-800'}`} style={{ width: frameWidth, maxWidth: '100%' }}>
                            {device !== 'desktop' && <div className="h-4 bg-slate-800"><div className="mx-auto h-1.5 w-12 rounded-b-full bg-slate-600" /></div>}
                            <iframe src={template.embed_path} title={`${template.name} — ${template.current_page?.name}`} className="block h-[calc(100vh-116px)] min-h-[640px] w-full bg-white md:h-[calc(100vh-112px)]" />
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
