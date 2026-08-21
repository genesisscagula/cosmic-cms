import { useState, useEffect } from 'react';
import { Head, useForm, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { DarkCyanHeader, GlassmorphismHeader } from './GenerateHeader';
import { MinimalFooter } from './GenerateFooter';
import WebsiteWorkspaceHeader from './Components/WebsiteWorkspaceHeader';
import NewPagePanel from './Components/NewPagePanel';
import PageList from './Components/PageList';
import PageEmptyState from './Components/PageEmptyState';
import EditPageTitleModal from './Components/EditPageTitleModal';
import WebsiteLaunchGuide from './Components/WebsiteLaunchGuide';
import CommerceProductsWorkspace from './Components/CommerceProductsWorkspace';
import PostsUpdatesWorkspace from './Components/PostsUpdatesWorkspace';
import InquiryInboxModal from './Components/InquiryInboxModal';
import WebsiteSettingsModal from './Components/WebsiteSettingsModal';
import BusinessProfileModal from './Components/BusinessProfileModal';
import HeaderMenuEditor from './Components/HeaderMenuEditor';
import { confirmCosmicAction, showCosmicNotification } from '../../Components/CosmicNotification';
import CreditPrice from '../../Components/CosmicCredits/CreditPrice';
import CreditBalanceBadge from '../../Components/CosmicCredits/CreditBalanceBadge';
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import { ACTION_PRICING } from '../../cosmic/pricing';

const WebsiteWorkspaceShell = ({ children }) => <>{children}</>;
const countMenuItems = (items = []) => items.reduce((total, item) => total + 1 + countMenuItems(item.children || []), 0);
const legacyHeaderLogoSamples = new Set(['AkongLogo', 'DesignKaBai', 'CosmicCMS']);

const defaultMegaFooter = {
    enabled: false,
    theme: 'auto',
    tagline: 'A premium information-rich footer.',
    primary_label: 'Get in touch',
    primary_url: '#contact',
    columns: [
        { title: 'Company', items: [{ label: 'About us', url: '#about' }, { label: 'Careers', url: '#careers' }, { label: 'Contact', url: '#contact' }] },
        { title: 'Services', items: [{ label: 'What we do', url: '#services' }, { label: 'Solutions', url: '#solutions' }, { label: 'Pricing', url: '#pricing' }] },
        { title: 'Resources', items: [{ label: 'Insights', url: '#insights' }, { label: 'Guides', url: '#guides' }, { label: 'Updates', url: '#updates' }] },
    ],
};

const normalizeGlobalFooter = (footer, websiteName) => {
    const source = footer && typeof footer === 'object' ? footer : {};
    const mega = source.mega_footer && typeof source.mega_footer === 'object' ? source.mega_footer : {};
    return {
        ...source,
        type: 'minimal_footer',
        theme: source.theme || 'white',
        logo_text: source.logo_text || websiteName,
        copyright: source.copyright || `© ${new Date().getFullYear()}. All rights reserved.`,
        privacy_label: source.privacy_label || 'Privacy Policy',
        privacy_url: source.privacy_url || '/privacy-policy',
        terms_label: source.terms_label || 'Terms & Conditions',
        terms_url: source.terms_url || '/terms-and-conditions',
        mega_enabled: Boolean(source.mega_enabled ?? mega.enabled ?? false),
        mega_footer: {
            ...defaultMegaFooter,
            ...mega,
            enabled: Boolean(source.mega_enabled ?? mega.enabled ?? false),
            columns: Array.isArray(mega.columns) && mega.columns.length ? mega.columns.slice(0, 4) : defaultMegaFooter.columns,
        },
    };
};


const replaceLegacyHeaderLogo = (header, websiteName) => {
    if (!header || !legacyHeaderLogoSamples.has((header.logo_text || '').trim())) {
        return header;
    }

    return { ...header, logo_text: websiteName };
};

export default function Index({ website, pages, inquiryCount = 0, recentInquiries = [], globalHeaderBlock, globalFooterBlock, commerce = {}, contentWorkspace = { types: [] } }) {
    const { balance: creditBalance, setBalance: setCreditBalance } = useCreditBalance();

    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        page_type: 'standard',
        parent_id: null,
    });

    const [isHeaderModalOpen, setIsHeaderModalOpen] = useState(false);
    const [isNewPageOpen, setIsNewPageOpen] = useState(false);
    const [savedHeader, setSavedHeader] = useState(() => replaceLegacyHeaderLogo(globalHeaderBlock, website.name));
    const [isLogoSizeOpen, setIsLogoSizeOpen] = useState(false);
    const originalMenuItemCount = countMenuItems(globalHeaderBlock?.menu || []);
    const [isSaving, setIsSaving] = useState(false);
    const [isLogoUploading, setIsLogoUploading] = useState(false);
    const [isPushingLive, setIsPushingLive] = useState(false);
    const [isCheckingLive, setIsCheckingLive] = useState(false);
    const [isLiveConnected, setIsLiveConnected] = useState(Boolean(website?.deployment_verified_at));
    const [isConnectorSetupOpen, setIsConnectorSetupOpen] = useState(false);
    const [connectorError, setConnectorError] = useState(website?.deployment_error || "");
    const [isInquiryInboxOpen, setIsInquiryInboxOpen] = useState(false);
    const [isWebsiteSettingsOpen, setIsWebsiteSettingsOpen] = useState(false);
    const [isBusinessProfileOpen, setIsBusinessProfileOpen] = useState(false);
    const [workspaceContentTab, setWorkspaceContentTab] = useState(() => {
        if (typeof window === 'undefined') return 'standard';
        const requested = new URLSearchParams(window.location.search).get('workspace');
        return ['standard', 'posts', 'shop'].includes(requested) ? requested : 'standard';
    });
    const [visibleInquiryCount, setVisibleInquiryCount] = useState(inquiryCount);
    const [isFooterModalOpen, setIsFooterModalOpen] = useState(false);
    const [editingPage, setEditingPage] = useState(null);
    const [isUpdatingPageTitle, setIsUpdatingPageTitle] = useState(false);
    const [cloningPageId, setCloningPageId] = useState(null);
    const [savedFooter, setSavedFooter] = useState(() => normalizeGlobalFooter(globalFooterBlock, website.name));

    const updateFooterContent = (updatedFields) => {
        setSavedFooter(prev => ({ ...prev, ...updatedFields }));
    };


    // KINI ANG MO-SYNC SA STATE ARON DILI MO-EMPTY INIG OPEN SA MODAL O HUMAN SA RELOAD
    useEffect(() => {
        setSavedHeader(replaceLegacyHeaderLogo(globalHeaderBlock, website.name));
    }, [globalHeaderBlock, website.name]);


    useEffect(() => {
        // Kung naay gipasa nga props, i-update ang state
        if (globalFooterBlock) {
            setSavedFooter(normalizeGlobalFooter(globalFooterBlock, website.name));
        }
    }, [globalFooterBlock]);

    useEffect(() => {
        setIsLiveConnected(Boolean(website?.deployment_verified_at));
        setConnectorError(website?.deployment_error || "");
    }, [website?.deployment_verified_at, website?.deployment_error]);

    useEffect(() => {
        if (!isConnectorSetupOpen) return undefined;
        const handleConnectorEscape = (event) => {
            if (event.key === 'Escape' && !isCheckingLive) setIsConnectorSetupOpen(false);
        };
        window.addEventListener('keydown', handleConnectorEscape);
        return () => window.removeEventListener('keydown', handleConnectorEscape);
    }, [isConnectorSetupOpen, isCheckingLive]);

    useEffect(() => {
        if (!isHeaderModalOpen && !isFooterModalOpen) return undefined;

        const handleKeyDown = (event) => {
            if (event.key !== 'Escape' || isSaving) return;
            setIsHeaderModalOpen(false);
            setIsFooterModalOpen(false);
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isHeaderModalOpen, isFooterModalOpen, isSaving]);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('pages.store', website.id), {
            onSuccess: () => {
                reset();
                setData({ title: '', page_type: 'standard', parent_id: null });
                setIsNewPageOpen(false);
                setNewPageParent(null);
                setCreditBalance(Math.max(0, creditBalance - ACTION_PRICING.add_page));
            },
        });
    };

    const [newPageParent, setNewPageParent] = useState(null);

    const openNewPage = (parent = null) => {
        reset();
        setData({ title: '', page_type: 'standard', parent_id: parent?.id ?? null });
        setNewPageParent(parent);
        setIsNewPageOpen(true);
    };

    const closeNewPage = () => {
        if (processing) return;
        setIsNewPageOpen(false);
        setNewPageParent(null);
        reset();
        setData({ title: '', page_type: 'standard', parent_id: null });
    };

    const defaultTheme = {
        primary: 'emerald',
        secondary: 'white',
        tertiary: 'stone',
    };
    let savedThemeSettings = {};

    if (typeof website.theme_settings === 'string') {
        try {
            savedThemeSettings = JSON.parse(website.theme_settings);
        } catch {
            savedThemeSettings = {};
        }
    } else if (website.theme_settings && typeof website.theme_settings === 'object') {
        savedThemeSettings = website.theme_settings;
    }

    if (!savedThemeSettings || typeof savedThemeSettings !== 'object' || Array.isArray(savedThemeSettings)) {
        savedThemeSettings = {};
    }

    const globalTheme = {
        ...defaultTheme,
        ...savedThemeSettings,
        primary: savedThemeSettings.primary || savedThemeSettings.primary_color || defaultTheme.primary,
    };
    const themeSummary = globalTheme.primary;

    const updateHeaderContent = (updatedFields) => {
        setSavedHeader(prev => ({ ...prev, ...updatedFields }));
    };

    const updateHeaderMenu = (menu) => updateHeaderContent({ menu });

    const uploadHeaderLogo = async (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) return;

        const allowedTypes = ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showCosmicNotification({ title: 'Unsupported logo format', message: 'Upload an SVG, PNG, JPG, or WebP logo.', tone: 'error' });
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            showCosmicNotification({ title: 'Logo is too large', message: 'Choose a logo smaller than 2 MB.', tone: 'error' });
            return;
        }

        setIsLogoUploading(true);

        try {
            const formData = new FormData();
            formData.append('website_id', website.id);
            formData.append('image', file);

            const response = await axios.post(route('websites.logo.upload'), formData);
            updateHeaderContent({ logo_image_url: response.data.url });
            showCosmicNotification({ title: 'Logo uploaded', message: 'Save the header to use this logo across the website.', tone: 'success' });
        } catch (error) {
            const message = error.response?.data?.message || 'Please try uploading the logo again.';
            showCosmicNotification({ title: 'Unable to upload logo', message, tone: 'error' });
        } finally {
            setIsLogoUploading(false);
        }
    };

    const publishedPageTargets = pages
        .filter((page) => page.status === 'published')
        .map((page) => ({ title: page.title, slug: page.slug }));

    const addedMenuItemCount = Math.max(0, countMenuItems(savedHeader?.menu || []) - originalMenuItemCount);
    const pendingMenuCreditCost = addedMenuItemCount * ACTION_PRICING.add_menu_item;

    const saveHeaderToDatabase = async () => {
        setIsSaving(true);
        
        try {
            const response = await axios.post(`/websites/${website.id}/global-header/save`, {
                header_block: savedHeader
            });
            
            if (response.data.status === 'success') {
                if (response.data.credit_balance !== undefined) {
                    setCreditBalance(response.data.credit_balance);
                }
                showCosmicNotification({ title: 'Header saved', message: 'Use Push to live when you are ready to send this header to the live site.', tone: 'success' });
                router.reload({ 
                    only: ['globalHeaderBlock'],
                    onSuccess: () => {
                        setIsHeaderModalOpen(false);
                    }
                });
            }
        } catch (error) {
            console.error("Full Axios Error Context:", error.response || error);
            showCosmicNotification({ title: 'Unable to save header', message: 'Please try saving the global header again.', tone: 'error' });
        } finally {
            setIsSaving(false);
        }
    };

    const saveFooterToDatabase = async () => {
        setIsSaving(true);
        try {
            const response = await axios.post(route('websites.global-footer.save', website.id), {
                footer_block: savedFooter
            });
            
            if (response.data.status === 'success') {
                showCosmicNotification({ title: 'Footer saved', message: 'Use Push to live when you are ready to send this footer to the live site.', tone: 'success' });
                router.reload({ 
                    only: ['globalFooterBlock'],
                    onSuccess: () => {
                        setIsFooterModalOpen(false);
                    }
                });
            }
        } catch (error) {
            console.error("Full Axios Error Context:", error.response || error);
            showCosmicNotification({ title: 'Unable to save footer', message: 'Please try saving the global footer again.', tone: 'error' });
        } finally {
            setIsSaving(false);
        }
    };

    const verifyLiveConnection = async ({ quiet = false } = {}) => {
        setIsCheckingLive(true);
        try {
            const response = await axios.post(route('websites.deployment-connector.verify', website.id));
            setIsLiveConnected(true);
            setConnectorError('');
            setIsConnectorSetupOpen(false);
            if (!quiet) showCosmicNotification({ title: 'Live site connected', message: response.data.message || 'The Cosmic connector is ready.', tone: 'success' });
            return true;
        } catch (error) {
            const message = error.response?.data?.message || 'The Cosmic connector could not be reached at this website.';
            setIsLiveConnected(false);
            setConnectorError(message);
            setIsConnectorSetupOpen(true);
            return false;
        } finally {
            setIsCheckingLive(false);
        }
    };

    const pushLiveUpdate = async () => {
        if (!isLiveConnected) {
            await verifyLiveConnection();
            return;
        }

        if (!await confirmCosmicAction({ title: 'Push to live?', message: `All published pages for ${website.name} will be sent to the connected live site.`, confirmLabel: 'Push to live', tone: 'info' })) return;

        setIsPushingLive(true);

        try {
            const response = await axios.post(route('websites.deployment-connector.push', website.id));
            setIsLiveConnected(true);
            showCosmicNotification({ title: 'Live site updated', message: response.data.message, tone: 'success' });
        } catch (error) {
            const message = error.response?.data?.message || 'The live update could not be pushed.';
            const connectionFailure = error.response?.status === 422 && /(connector|connect|reach|verified|domain)/i.test(message);
            if (connectionFailure) {
                setIsLiveConnected(false);
                setConnectorError(message);
                setIsConnectorSetupOpen(true);
            } else {
                showCosmicNotification({ title: 'Live update failed', message, tone: 'error' });
            }
        } finally {
            setIsPushingLive(false);
        }
    };

    const downloadConnector = () => {
        window.location.href = route('websites.deployment-connector.download', website.id);
    };

    const editPageTitle = async (title) => {
        if (!editingPage || isUpdatingPageTitle) return;
        setIsUpdatingPageTitle(true);
        try {
            await axios.patch(route('pages.title.update', [website.id, editingPage.id]), { title });
            showCosmicNotification({ title: 'Page title updated', message: `Renamed to “${title}”. The page URL was kept unchanged.`, tone: 'success' });
            setEditingPage(null);
            router.reload({ only: ['pages'] });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to update title', message: error.response?.data?.message || error.response?.data?.errors?.title?.[0] || 'Please try again.', tone: 'error' });
        } finally {
            setIsUpdatingPageTitle(false);
        }
    };

    const clonePage = async (page) => {
        if (cloningPageId) return;
        if (!await confirmCosmicAction({
            title: `Clone ${page.title || 'this page'}?`,
            message: 'A draft copy will be created with the same layout and content.',
            confirmLabel: 'Clone page',
            tone: 'info',
        })) return;

        setCloningPageId(page.id);
        try {
            const response = await axios.post(route('pages.clone', [website.id, page.id]));
            if (response.data.credit_balance !== undefined) setCreditBalance(response.data.credit_balance);
            showCosmicNotification({ title: 'Page cloned', message: `${response.data.page?.title || 'The copy'} is ready as a draft.`, tone: 'success' });
            router.reload({ only: ['pages'] });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to clone page', message: error.response?.data?.message || 'Please check your Cosmic Credits and try again.', tone: 'error' });
        } finally {
            setCloningPageId(null);
        }
    };

    const deletePage = async (page) => {
        const typeDescription = page.page_type === 'blog'
            ? 'Its posts and updates will also be permanently deleted.'
            : 'Its saved blocks and unpublished changes will be permanently deleted.';

        if (!await confirmCosmicAction({
            title: `Delete ${page.title || 'this page'}?`,
            message: typeDescription,
            confirmLabel: 'Delete page',
            tone: 'danger',
        })) return;

        try {
            await axios.delete(route('pages.destroy', [website.id, page.id]));
            showCosmicNotification({ title: 'Page deleted', message: 'The page was removed from this website.', tone: 'success' });
            router.reload({ only: ['pages'] });
        } catch (error) {
            showCosmicNotification({ title: 'Unable to delete page', message: error.response?.data?.message || 'Please try again.', tone: 'error' });
        }
    };

    return (
        <WebsiteWorkspaceShell
            header={
                <div className="flex justify-between items-center">
                    <div className="flex items-center space-x-3">
                        <Link href={route('dashboard')} className="text-indigo-600 hover:text-indigo-800 font-semibold transition">
                            &larr; Back to Hub
                        </Link>
                        <span className="text-gray-400">|</span>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Managing Pages for: <span className="text-indigo-600">{website.name}</span> 📱
                        </h2>
                    </div>
                </div>
            }
        >
            <Head title={`Manage Pages - ${website.name}`} />

            <div className="cosmic-ui-shell min-h-screen bg-[#0a0a0b] px-4 py-6 text-slate-100 sm:px-6 lg:px-10 lg:py-10">
                <div className="mx-auto max-w-6xl space-y-7">
                    <WebsiteWorkspaceHeader website={website} pageCount={pages?.length || 0} inquiryCount={visibleInquiryCount} themeSummary={themeSummary} onNewPage={() => openNewPage()} onLiveAction={pushLiveUpdate} liveConnected={isLiveConnected} pushingLive={isPushingLive} checkingLive={isCheckingLive} onOpenInquiries={() => setIsInquiryInboxOpen(true)} onOpenProfile={() => setIsBusinessProfileOpen(true)} onOpenSettings={() => setIsWebsiteSettingsOpen(true)} creditBalance={creditBalance} />

                    <WebsiteLaunchGuide pages={pages || []} onNewPage={() => openNewPage()} />

                    <section id="cosmic-website-shell-card" className="cosmic-website-shell-card rounded-2xl p-4 sm:flex sm:items-center sm:justify-between sm:gap-5">
                        <div className="flex items-start gap-3"><span className="cosmic-shell-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M4 5.5h16v13H4z"/><path d="M4 9h16"/><path d="M8 5.5v3.5"/></svg></span><div><p className="cosmic-shell-title text-sm font-semibold">Website shell</p><p className="cosmic-shell-copy mt-1 text-sm">Configure the shared header and footer used across this website.</p></div></div>
                        <div className="mt-4 flex gap-2 sm:mt-0"><button type="button" onClick={() => { setSavedHeader(replaceLegacyHeaderLogo(globalHeaderBlock, website.name)); setIsHeaderModalOpen(true); }} className="cosmic-shell-action rounded-lg px-3 py-2 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-violet-400">Edit Header</button><button type="button" onClick={() => setIsFooterModalOpen(true)} className="cosmic-shell-action rounded-lg px-3 py-2 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-violet-400">Edit Footer</button></div>
                    </section>

                    <section className="space-y-4">
                        <div id="cosmic-website-page-type-tabs" className="cosmic-website-page-type-tabs flex flex-wrap gap-2 rounded-2xl border border-white/10 bg-white/[0.025] p-2" role="tablist" aria-label="Website content types">
                            {[
                                ['standard', 'Standard Pages', (pages || []).filter((page) => page.page_type === 'standard').length],
                                ['posts', 'Posts / Updates', (contentWorkspace?.types || []).reduce((total, type) => total + (type.entries_count || 0), 0)],
                                ['shop', 'Shop / Products', commerce?.products?.length || 0],
                            ].map(([key, label, count]) => {
                                const isActive = workspaceContentTab === key;
                                return <button
                                    key={key}
                                    type="button"
                                    role="tab"
                                    aria-selected={isActive}
                                    data-state={isActive ? 'active' : 'inactive'}
                                    onClick={() => setWorkspaceContentTab(key)}
                                    className={`cosmic-website-page-type-tab ${isActive ? 'is-active' : 'is-inactive'}`}
                                >
                                    <span className="cosmic-website-page-type-tab-label">{label}</span>
                                    <span className="cosmic-website-page-type-tab-count">{count}</span>
                                </button>;
                            })}
                        </div>

                        {workspaceContentTab === 'shop' ? <CommerceProductsWorkspace website={website} commerce={commerce} /> : workspaceContentTab === 'posts' ? <div className="space-y-5"><PostsUpdatesWorkspace website={website} initialWorkspace={contentWorkspace} />{(pages || []).some((page) => page.page_type === 'blog') ? <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-4"><div className="mb-3"><p className="text-sm font-semibold text-white">Legacy Posts / Updates pages</p><p className="mt-1 text-xs text-slate-500">Existing blog-style Builder pages stay available while the structured content engine is introduced.</p></div><PageList pages={(pages || []).filter((page) => page.page_type === 'blog')} onDelete={deletePage} onAddChild={openNewPage} onEditTitle={setEditingPage} onClone={clonePage} /></div> : null}</div> : (() => {
                            const visiblePages = (pages || []).filter((page) => page.page_type === 'standard');
                            return <div><div className="mb-3 flex items-center justify-between"><div><p className="cosmic-pages-section-title text-sm font-semibold">Standard Pages</p><p className="cosmic-pages-section-copy mt-1 text-sm">Open a page in Builder to edit its blocks and layout.</p></div><span className="text-xs text-slate-500">{visiblePages.length} total</span></div>{visiblePages.length ? <PageList pages={visiblePages} onDelete={deletePage} onAddChild={openNewPage} onEditTitle={setEditingPage} onClone={clonePage} /> : <PageEmptyState onNewPage={() => openNewPage()} />}</div>;
                        })()}
                    </section>

                    <NewPagePanel open={isNewPageOpen} onClose={closeNewPage} data={data} setData={setData} errors={errors} processing={processing} onSubmit={handleSubmit} parentPage={newPageParent} creditBalance={creditBalance} />
                    {isInquiryInboxOpen ? <InquiryInboxModal website={website} submissions={recentInquiries} onClose={() => setIsInquiryInboxOpen(false)} onCountChange={(difference) => setVisibleInquiryCount((count) => Math.max(0, count + difference))} /> : null}
                    <EditPageTitleModal page={editingPage} saving={isUpdatingPageTitle} onClose={() => !isUpdatingPageTitle && setEditingPage(null)} onSave={editPageTitle} />
                    {isBusinessProfileOpen ? <BusinessProfileModal website={website} onClose={() => setIsBusinessProfileOpen(false)} onSaved={() => { showCosmicNotification({ title: 'Business profile saved', message: 'Future AI drafts will use these details as context.', tone: 'success' }); router.reload(); }} /> : null}
                    {isWebsiteSettingsOpen ? <WebsiteSettingsModal website={website} onClose={() => setIsWebsiteSettingsOpen(false)} onSaved={() => { showCosmicNotification({ title: 'Website settings saved', message: 'Download a new connector if you changed the live URL or inquiry recipient email.', tone: 'success' }); router.reload(); }} /> : null}
                    
                    {isConnectorSetupOpen ? (
                        <div className="fixed inset-0 z-[160] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="cosmic-live-connector-title" onMouseDown={(event) => { if (event.target === event.currentTarget && !isCheckingLive) setIsConnectorSetupOpen(false); }}>
                            <div className="w-full max-w-xl rounded-2xl border border-white/10 bg-[#121216] p-6 shadow-2xl">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">Cosmic live connector</p>
                                        <h2 id="cosmic-live-connector-title" className="mt-2 text-xl font-semibold text-white">Connect this website to live</h2>
                                        <p className="mt-2 text-sm leading-6 text-slate-400">Cosmic could not verify the connector yet. Install it once, then future updates can be pushed directly from this dashboard.</p>
                                    </div>
                                    <button type="button" onClick={() => setIsConnectorSetupOpen(false)} disabled={isCheckingLive} className="rounded-lg border border-white/10 px-2.5 py-1.5 text-sm text-slate-400 transition hover:bg-white/10 hover:text-white disabled:opacity-50" aria-label="Close connector setup">×</button>
                                </div>

                                {connectorError ? <div className="mt-4 rounded-xl border border-amber-400/20 bg-amber-400/[0.06] px-4 py-3 text-sm leading-6 text-amber-100">{connectorError}</div> : null}

                                <div className="mt-5 rounded-xl border border-white/10 bg-white/[0.03] p-4">
                                    <ol className="space-y-3 text-sm leading-6 text-slate-300">
                                        <li><span className="mr-2 font-semibold text-white">1.</span>Download and extract the Cosmic connector ZIP.</li>
                                        <li><span className="mr-2 font-semibold text-white">2.</span>Upload the <code className="rounded bg-black/30 px-1.5 py-0.5 text-violet-200">cosmic-cms</code> folder to your website root directory.</li>
                                        <li><span className="mr-2 font-semibold text-white">3.</span>Upload the included <code className="rounded bg-black/30 px-1.5 py-0.5 text-violet-200">.htaccess</code> to the root. If you already have one, back it up and merge the Cosmic rules instead of replacing your existing rules.</li>
                                        <li><span className="mr-2 font-semibold text-white">4.</span>Return here and click <strong className="text-white">Check connection</strong>.</li>
                                    </ol>
                                </div>

                                <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                    <button type="button" onClick={downloadConnector} className="inline-flex h-10 items-center justify-center rounded-xl border border-white/10 px-4 text-sm font-semibold text-slate-200 transition hover:bg-white/10">Download connector</button>
                                    <button type="button" onClick={() => verifyLiveConnection()} disabled={isCheckingLive} className="inline-flex h-10 items-center justify-center rounded-xl bg-emerald-400 px-4 text-sm font-semibold text-emerald-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:opacity-60">{isCheckingLive ? 'Checking connection...' : 'Check connection'}</button>
                                </div>
                            </div>
                        </div>
                    ) : null}

                    {/* INPUT FORM PANEL */}
                    <div className="hidden p-6 bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100">
                        <h3 className="text-lg font-medium text-gray-900 mb-1">Create New Dynamic Page</h3>
                        <p className="text-xs text-gray-500 mb-4">Enter a page name, such as Home or About Us, to create its page route.</p>
                        
                        <form onSubmit={handleSubmit} className="flex gap-4 items-end max-w-xl">
                            <div className="flex-1">
                                <label className="block text-sm font-medium text-gray-700">Page Title</label>
                                <input
                                    type="text"
                                    value={data.title}
                                    onChange={e => setData('title', e.target.value)}
                                    placeholder="e.g., Home"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    required
                                />
                                {errors.title && <div className="text-red-500 text-xs mt-1">{errors.title}</div>}
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-6 py-2 bg-indigo-600 text-white font-semibold text-sm rounded-md shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 h-[38px] transition"
                            >
                                {processing ? 'Creating...' : '+ Create Page'}
                            </button>
                        </form>
                    </div>

                    {/* GLOBAL ELEMENTS CONFIGURATION PANEL */}
                    <div className="hidden p-6 bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg border border-slate-800 text-white">
                        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="px-2 py-0.5 text-[10px] uppercase tracking-wider bg-purple-500/20 text-purple-300 rounded font-bold font-mono">Global Layout Matrix</span>
                                    <span className="animate-ping w-2 h-2 rounded-full bg-emerald-400"></span>
                                </div>
                                <h3 className="text-lg font-bold mt-1 text-white">Website Shell: Global Header & Footer</h3>
                                <p className="text-xs text-slate-400 max-w-xl mt-0.5">
                                    Configure shared layouts, brand details, and footer content for the full website.
                                </p>
                            </div>
                            <div className="flex gap-3 w-full md:w-auto shrink-0">
                                <button 
                                    type="button"
                                    onClick={() => {
                                        // Pwersahon nato ang state base sa pinakabag-ong globalHeaderBlock prop sa dili pa i-open ang frame
                                        setSavedHeader(globalHeaderBlock || null);
                                        setIsHeaderModalOpen(true);
                                    }}
                                    className="flex-1 md:flex-initial text-center text-xs font-bold bg-purple-650 hover:bg-purple-600 text-white px-4 py-2.5 rounded-xl border border-purple-500/30 transition shadow-lg shadow-purple-900/20"
                                >
                                    🌐 AI Edit Header
                                </button>
                                <button 
                                    type="button"
                                    onClick={() => setIsFooterModalOpen(true)}
                                    className="flex-1 md:flex-initial text-center text-xs font-bold bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2.5 rounded-xl border border-slate-700 transition"
                                >
                                    📥 AI Edit Footer
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* PAGES ARCHITECTURE LIST */}
                    <div className="hidden p-6 bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Website Pages Architecture</h3>
                        
                        {!pages || pages.length === 0 ? (
                            <div className="text-center py-10 border-2 border-dashed border-gray-200 rounded-lg">
                                <p className="text-gray-500 text-sm">No pages created yet for this site. Standard flow starts by adding a 'Home' page!</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Page Title</th>
                                            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Slug / Route URL</th>
                                            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Layout Configuration</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {pages.map((page) => (
                                            <tr key={page.id} className="hover:bg-gray-50 transition">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-semibold text-gray-900">{page.title}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="text-xs font-mono bg-slate-100 text-slate-700 px-2 py-1 rounded border">
                                                        /{page.slug}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 uppercase">
                                                        {page.status || 'published'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <Link 
                                                        href={route('pages.builder', page.id)}
                                                        className="text-indigo-600 hover:text-indigo-900 font-semibold bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded transition inline-block text-sm"
                                                    >
                                                        🛠️ Edit Layout Blocks &rarr;
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                </div>
            </div>

            {/* GLOBAL HEADER MODAL POPUP SYSTEM */}
            {isHeaderModalOpen && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
                    <button type="button" aria-label="Close header dialog" onClick={() => !isSaving && setIsHeaderModalOpen(false)} className="absolute inset-0 cursor-default" />
                    <div role="dialog" aria-modal="true" aria-labelledby="edit-header-title" id="cosmic-global-header-modal" className="cosmic-global-header-modal relative h-[min(88dvh,900px)] max-h-[calc(100dvh-2rem)] w-full max-w-7xl overflow-y-auto rounded-2xl border border-white/10 bg-[#151519] p-5 text-slate-100 shadow-2xl shadow-black/50 sm:p-6 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-700 hover:[&::-webkit-scrollbar-thumb]:bg-violet-500/70">
                        <div className="mb-5 flex items-start justify-between gap-4">
                            <div>
                                <h2 id="edit-header-title" className="text-xl font-semibold text-white">Edit global header</h2>
                                <p className="mt-1 text-sm text-slate-400">Choose the header used across this website. Publish a page when you are ready to send changes live.</p>
                            </div>
                            <button type="button" disabled={isSaving} onClick={() => setIsHeaderModalOpen(false)} className="flex h-9 w-9 items-center justify-center rounded-lg text-lg text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Close">×</button>
                        </div>

                        {/* LIVE PREVIEW FIELD */}
                        <div className="cosmic-footer-preview mb-6 rounded-xl border border-white/10 bg-black/20 px-2 pb-2 pt-5 sm:px-3 sm:pb-3">
                            <h3 className="mb-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Preview</h3>
                            {savedHeader ? (
                                <div className="w-full overflow-hidden rounded-lg">
                                    {savedHeader.type === 'dark_cyan_header' && <DarkCyanHeader block={savedHeader} onUpdate={updateHeaderContent} pageTargets={publishedPageTargets} onLogoClick={savedHeader.logo_image_url ? () => setIsLogoSizeOpen(true) : null} />}
                                    {savedHeader.type === 'glassmorphism_header' && <GlassmorphismHeader block={savedHeader} onUpdate={updateHeaderContent} globalTheme={globalTheme} pageTargets={publishedPageTargets} onLogoClick={savedHeader.logo_image_url ? () => setIsLogoSizeOpen(true) : null} />}
                                </div>
                            ) : (
                                <div className="py-7 text-center text-sm text-slate-500">
                                    Choose a header layout to preview it here.
                                </div>
                            )}
                        </div>

                        {savedHeader && (
                            <div className="mb-6 border-t border-white/10 pt-5">
                                <div className="mb-5 rounded-xl border border-white/10 bg-white/[0.025] p-3.5">
                                    <h3 className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Brand</h3>
                                    <p className="mt-1 text-xs leading-5 text-slate-400">Logo text appears when no logo image has been uploaded.</p>

                                    <label className="mt-3 block">
                                        <span className="mb-1 block text-[10px] font-medium uppercase tracking-[0.12em] text-slate-500">Logo text</span>
                                        <input
                                            type="text"
                                            value={savedHeader.logo_text || ''}
                                            onChange={(event) => updateHeaderContent({ logo_text: event.target.value })}
                                            placeholder={website.name}
                                            className="w-full rounded-md border border-white/10 bg-black/20 px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20"
                                        />
                                    </label>

                                    <div className="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p className="text-xs font-medium text-slate-200">Logo image <span className="font-normal text-slate-500">(optional)</span></p>
                                            <p className="mt-1 text-xs text-slate-500">SVG, PNG, JPG, or WebP. Up to 2 MB. Your logo keeps its original brand colors.</p>
                                        </div>
                                        <label className="inline-flex shrink-0 cursor-pointer items-center justify-center rounded-lg border border-white/10 bg-white px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus-within:ring-2 focus-within:ring-violet-400">
                                            <span>{isLogoUploading ? 'Uploading...' : 'Upload logo'}</span>
                                            <input
                                                type="file"
                                                accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp"
                                                onChange={uploadHeaderLogo}
                                                disabled={isLogoUploading || isSaving}
                                                className="sr-only"
                                            />
                                        </label>
                                    </div>

                                    {savedHeader.logo_image_url && (
                                        <div className="mt-3 flex flex-wrap items-center gap-3 rounded-lg border border-white/10 bg-black/20 p-2.5">
                                            <div className="flex h-11 min-w-24 items-center rounded-md bg-white px-3">
                                                <img src={savedHeader.logo_image_url} alt="Uploaded website logo" style={{ height: `${Math.min(60, Math.max(24, Number(savedHeader.logo_height || 40)))}px` }} className="w-auto max-w-[250px] object-contain" />
                                            </div>
                                            <button type="button" onClick={() => setIsLogoSizeOpen(true)} disabled={isSaving || isLogoUploading} className="text-xs font-medium text-violet-300 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">Adjust size</button>
                                            <button
                                                type="button"
                                                onClick={() => updateHeaderContent({ logo_image_url: null })}
                                                disabled={isSaving || isLogoUploading}
                                                className="text-xs font-medium text-slate-400 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400"
                                            >
                                                Use logo text instead
                                            </button>
                                        </div>
                                    )}
                                </div>

                                <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <h3 className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Menu links</h3>
                                        <p className="mt-1 text-xs leading-5 text-slate-400">
                                            Use the exact published page slug for static links. <span className="text-slate-300">home</span> opens the homepage; <span className="text-slate-300">about</span> becomes <span className="text-slate-300">/about</span> after Push to live.
                                        </p>
                                    </div>
                                    <p className="text-[11px] text-slate-500">External URLs and #section anchors stay unchanged.</p>
                                </div>

                                <HeaderMenuEditor menu={savedHeader.menu || []} onChange={updateHeaderMenu} targetOptions={publishedPageTargets} />

                                {savedHeader.type === 'glassmorphism_header' && (
                                    <div className="mt-2 grid grid-cols-1 gap-2 rounded-lg border border-violet-400/15 bg-violet-400/[0.035] p-2.5 sm:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                                        <label className="min-w-0">
                                            <span className="cosmic-header-cta-field-label mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-violet-200/70">CTA label</span>
                                            <input
                                                type="text"
                                                value={savedHeader.cta_label || 'Get Started'}
                                                onChange={(event) => updateHeaderContent({ cta_label: event.target.value })}
                                                className="w-full rounded-md border border-white/10 bg-black/20 px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20"
                                            />
                                        </label>
                                        <label className="min-w-0">
                                            <span className="cosmic-header-cta-field-label mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-violet-200/70">CTA link target</span>
                                            <input
                                                type="text"
                                                list="published-page-slugs"
                                                value={savedHeader.cta_url || '#'}
                                                onChange={(event) => updateHeaderContent({ cta_url: event.target.value })}
                                                placeholder="contact, #contact, or https://..."
                                                className="w-full rounded-md border border-white/10 bg-black/20 px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20"
                                            />
                                        </label>
                                    </div>
                                )}

                                {publishedPageTargets.length === 0 && (
                                    <p className="mt-3 text-xs text-amber-200/80">Publish a page before its slug can be included in a live static navigation link.</p>
                                )}
                            </div>
                        )}

                        {/* BLUEPRINTS ARCHIVE */}
                        <div className="border-t border-white/10 pt-5">
                            <h3 className="mb-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Header layouts</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                
                                {/* TEMPLATE INJECT BUTTON 1 */}
                                <div className={`flex flex-col justify-between space-y-3 rounded-xl border p-4 transition ${savedHeader?.type === 'dark_cyan_header' ? 'border-violet-400/70 bg-violet-400/[0.07] ring-1 ring-violet-400/30' : 'border-white/10 bg-white/[0.03] hover:border-white/20'}`}>
                                    <div>
                                        <h4 className="text-sm font-semibold text-white">Minimal navigation</h4>
                                        <p className="mt-1 text-xs leading-5 text-slate-400">A simple logo and navigation layout.</p>
                                    </div>
                                    <button 
                                        type="button"
                                        onClick={() => updateHeaderContent({
                                            type: 'dark_cyan_header',
                                            logo_text: website.name,
                                            menu: [{ label: 'Home', url: 'home' }, { label: 'About', url: '#' }, { label: 'Services', url: '#' }]
                                        })}
                                        className="w-full rounded-lg border border-white/10 bg-white px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        {savedHeader?.type === 'dark_cyan_header' ? 'Selected' : 'Use layout'}
                                    </button>
                                </div>

                                {/* TEMPLATE INJECT BUTTON 2 */}
                                <div className={`flex flex-col justify-between space-y-3 rounded-xl border p-4 transition ${savedHeader?.type === 'glassmorphism_header' ? 'border-violet-400/70 bg-violet-400/[0.07] ring-1 ring-violet-400/30' : 'border-white/10 bg-white/[0.03] hover:border-white/20'}`}>
                                    <div>
                                        <h4 className="text-sm font-semibold text-white">CTA navigation</h4>
                                        <p className="mt-1 text-xs leading-5 text-slate-400">Navigation with a highlighted call-to-action.</p>
                                    </div>
                                    <button 
                                        type="button"
                                        onClick={() => updateHeaderContent({
                                            type: 'glassmorphism_header',
                                            logo_text: website.name,
                                            cta_label: 'Get Started',
	                                            cta_url: '#',
                                            menu: [{ label: 'Home', url: 'home' }, { label: 'About', url: '#' }, { label: 'Services', url: '#' }, { label: 'Blog', url: '#' }]
                                        })}
                                        className="w-full rounded-lg border border-white/10 bg-white px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        {savedHeader?.type === 'glassmorphism_header' ? 'Selected' : 'Use layout'}
                                    </button>
                                </div>

                            </div>
                        </div>

                        {/* MASTER SUBMIT CONTROL SYSTEM PANEL */}
                        <div className="mt-6 flex justify-end gap-2 border-t border-white/10 pt-4">
                            <button 
                                type="button" 
                                onClick={() => setIsHeaderModalOpen(false)}
                                className="rounded-lg px-4 py-2 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400"
                            >
                                Cancel
                            </button>
                            <button 
                                type="button"
                                onClick={saveHeaderToDatabase}
                                disabled={isSaving || isLogoUploading || !savedHeader}
                                className="rounded-lg bg-white px-4 py-2 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {isSaving ? 'Saving...' : pendingMenuCreditCost > 0 ? <CreditPrice label="Save header ·" amount={pendingMenuCreditCost} /> : 'Save header'}
                            </button>
                        </div>

                    </div>
                </div>
            )}


            {isLogoSizeOpen && savedHeader?.logo_image_url && (
                <div className="fixed inset-0 z-[130] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
                    <button type="button" onClick={() => setIsLogoSizeOpen(false)} className="absolute inset-0" aria-label="Close logo size settings" />
                    <section role="dialog" aria-modal="true" className="cosmic-logo-size-modal relative z-10 w-full max-w-md rounded-2xl border border-white/10 bg-[#18181d] p-5 text-slate-100 shadow-2xl">
                        <div className="flex items-start justify-between gap-4"><div><p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-violet-300">Global header</p><h3 className="mt-1 text-lg font-semibold text-white">Logo size</h3><p className="mt-1 text-xs leading-5 text-slate-400">Adjust the logo height. The aspect ratio stays unchanged and the width remains capped at 250px.</p></div><button type="button" onClick={() => setIsLogoSizeOpen(false)} className="rounded-lg px-2 py-1 text-slate-400 hover:bg-white/5 hover:text-white">×</button></div>
                        <div className="mt-5 rounded-xl border border-white/10 bg-white p-4"><img src={savedHeader.logo_image_url} alt="Logo size preview" style={{ height: `${Math.min(60, Math.max(24, Number(savedHeader.logo_height || 40)))}px` }} className="mx-auto w-auto max-w-[250px] object-contain" /></div>
                        <label className="mt-5 block"><span className="flex items-center justify-between text-xs font-medium text-slate-300"><span>Logo height</span><span>{Math.min(60, Math.max(24, Number(savedHeader.logo_height || 40)))}px</span></span><input type="range" min="24" max="60" step="1" value={Math.min(60, Math.max(24, Number(savedHeader.logo_height || 40)))} onChange={(event) => updateHeaderContent({ logo_height: Number(event.target.value), logo_max_width: 250 })} className="mt-3 w-full accent-violet-500" /></label>
                        <div className="mt-5 flex justify-between gap-2"><button type="button" onClick={() => updateHeaderContent({ logo_height: 40, logo_max_width: 250 })} className="rounded-lg border border-white/10 px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/5">Use default</button><button type="button" onClick={() => setIsLogoSizeOpen(false)} className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-slate-200">Done</button></div>
                    </section>
                </div>
            )}

            {/* GLOBAL FOOTER MODAL POPUP SYSTEM */}
            {isFooterModalOpen && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/80 p-3 backdrop-blur-md sm:p-5">
                    <button type="button" aria-label="Close footer dialog" onClick={() => !isSaving && setIsFooterModalOpen(false)} className="absolute inset-0 cursor-default" />
                    <div role="dialog" aria-modal="true" aria-labelledby="edit-footer-title" id="cosmic-shell-mega-footer-editor" className="cosmic-global-footer-premium-modal relative max-h-[calc(100dvh-1.5rem)] w-[97vw] max-w-[1540px] overflow-y-auto rounded-[1.75rem] border border-white/10 bg-[#111216] text-slate-100 shadow-[0_40px_120px_rgba(0,0,0,.6)]">
                        <div className="sticky top-0 z-20 flex items-start justify-between gap-5 border-b border-white/10 bg-[#111216]/95 px-5 py-5 backdrop-blur-xl sm:px-7">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.22em] text-emerald-300">Website shell</p>
                                <div className="mt-1 flex flex-wrap items-center gap-3">
                                    <h2 id="edit-footer-title" className="text-xl font-semibold tracking-[-.02em] text-white sm:text-2xl">Edit global footer</h2>
                                    <span className="rounded-full border border-white/10 bg-white/[0.05] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.14em] text-slate-400">Shared across every page</span>
                                </div>
                                <p className="mt-1.5 max-w-2xl text-sm leading-6 text-slate-400">Configure the same Mega Footer experience used in the Builder, with clean hover editing and one shared saved state.</p>
                            </div>
                            <button type="button" disabled={isSaving} onClick={() => setIsFooterModalOpen(false)} className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/[0.03] text-lg text-slate-400 transition hover:border-white/20 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-emerald-400 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Close">×</button>
                        </div>

                        <div className="space-y-5 p-5 sm:p-7">
                            <div className="grid gap-4 xl:grid-cols-[1fr_auto]">
                                <div className="rounded-2xl border border-white/10 bg-white/[0.035] p-4 sm:p-5">
                                    <div className="flex items-center justify-between gap-5">
                                        <div>
                                            <div className="flex items-center gap-2"><span className={`h-2 w-2 rounded-full ${savedFooter?.mega_enabled ? 'bg-emerald-400' : 'bg-slate-600'}`} /><h3 className="text-sm font-semibold text-white">Enable Mega Footer</h3></div>
                                            <p className="mt-1.5 text-xs leading-5 text-slate-400">Adds the global multi-column footer above the legal footer on every page. Turning it off keeps all menu content saved.</p>
                                        </div>
                                        <button
                                            type="button"
                                            role="switch"
                                            aria-checked={Boolean(savedFooter?.mega_enabled)}
                                            onClick={() => {
                                                const enabled = !savedFooter?.mega_enabled;
                                                updateFooterContent({
                                                    type: 'minimal_footer',
                                                    mega_enabled: enabled,
                                                    mega_footer: {
                                                        ...(savedFooter?.mega_footer || defaultMegaFooter),
                                                        enabled,
                                                    },
                                                });
                                            }}
                                            className={`relative h-7 w-12 shrink-0 rounded-full transition focus:outline-none focus:ring-2 focus:ring-emerald-400 ${savedFooter?.mega_enabled ? 'bg-emerald-500' : 'bg-slate-700'}`}
                                        >
                                            <span className={`absolute top-1 h-5 w-5 rounded-full bg-white shadow transition ${savedFooter?.mega_enabled ? 'left-6' : 'left-1'}`} />
                                        </button>
                                    </div>
                                </div>

                                <div className="rounded-2xl border border-white/10 bg-white/[0.035] p-4 sm:min-w-[360px]">
                                    <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Mega Footer appearance</p>
                                    <div className="mt-3 grid grid-cols-4 gap-2">
                                        {[['auto','Auto'],['primary','Primary'],['white','White'],['surface','Surface']].map(([value,label]) => {
                                            const active = (savedFooter?.mega_footer?.theme || 'auto') === value;
                                            return <button key={value} type="button" onClick={() => updateFooterContent({ mega_footer: { ...(savedFooter?.mega_footer || defaultMegaFooter), theme: value, enabled: Boolean(savedFooter?.mega_enabled) } })} className={`cosmic-mega-appearance-option rounded-xl border px-3 py-2 text-xs font-semibold transition ${active ? 'is-active border-emerald-400/60 bg-emerald-400/10 text-emerald-200' : 'border-white/10 bg-black/10 text-slate-400 hover:border-white/20 hover:text-white'}`}>{label}</button>;
                                        })}
                                    </div>
                                </div>
                            </div>

                            <div className="cosmic-footer-preview overflow-hidden rounded-[1.5rem] border border-white/10 bg-[#0b0c10] shadow-inner shadow-black/30">
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3 sm:px-5">
                                    <div><p className="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Global footer preview</p><p className="mt-0.5 text-xs text-slate-400">Hover the brand, column, or menu item to edit it. Add controls appear only in context.</p></div>
                                    <span className="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-[10px] font-bold uppercase tracking-[.14em] text-slate-400">Maximum 4 columns</span>
                                </div>
                                <div className="w-full overflow-hidden bg-white">
                                    <MinimalFooter
                                            block={savedFooter}
                                            onUpdate={updateFooterContent}
                                            editorMode
                                            resolvedTheme={(savedFooter?.mega_footer?.theme && savedFooter.mega_footer.theme !== 'auto') ? savedFooter.mega_footer.theme : 'primary'}
                                        />
                                </div>
                            </div>

                            <div className="rounded-2xl border border-white/10 bg-white/[0.025] px-4 py-3.5 sm:px-5">
                                <p className="text-xs leading-5 text-slate-400"><span className="font-semibold text-slate-200">One global footer system.</span> When Mega Footer is enabled, the website logo moves into it and the legal footer below switches to Privacy Policy, Terms & Conditions, and copyright. Builder and Website Shell share the same content and theme state.</p>
                            </div>

                            <div className="sticky bottom-0 z-20 -mx-5 -mb-5 flex justify-end gap-2 border-t border-white/10 bg-[#111216]/95 px-5 py-4 backdrop-blur-xl sm:-mx-7 sm:-mb-7 sm:px-7">
                                <button type="button" disabled={isSaving} onClick={() => setIsFooterModalOpen(false)} className="rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-emerald-400 disabled:cursor-not-allowed disabled:opacity-50">Cancel</button>
                                <button type="button" disabled={isSaving || !savedFooter} onClick={saveFooterToDatabase} className="rounded-xl bg-emerald-500 px-5 py-2.5 text-xs font-bold text-white shadow-lg shadow-emerald-950/20 transition hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-50">
                                    {isSaving ? 'Saving...' : 'Save footer'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </WebsiteWorkspaceShell>
    );
}
