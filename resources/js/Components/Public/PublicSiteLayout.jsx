import PublicHeader from './PublicHeader';
import PublicFooter from './PublicFooter';
import cosmicLogo from '../../../images/cosmic-cms-logo.png';

export default function PublicSiteLayout({ children, getStartedHref = '/start', className = '' }) {
    return (
        <div className={`cosmic-public-site cosmic-public-light min-h-screen overflow-x-clip bg-white text-slate-950 antialiased ${className}`}>
            <a
                href="#main-content"
                className="fixed left-4 top-3 z-[120] -translate-y-20 rounded-xl bg-[#07132c] px-4 py-2.5 text-sm font-bold text-white shadow-xl transition-transform focus:translate-y-0 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2"
            >
                Skip to content
            </a>
            <PublicHeader getStartedHref={getStartedHref} logoSrc={cosmicLogo} />
            <main id="main-content" tabIndex={-1} className="outline-none">{children}</main>
            <PublicFooter />
        </div>
    );
}
