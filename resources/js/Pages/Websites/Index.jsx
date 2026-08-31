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

export default function Index({ website, pages, inquiryCount = 0, recentInquiries = [], globalHeaderBlock, globalFooterBlock, commerce = {}, contentWorkspace = { types: [] }, starterSite = { installed: false, status: 'idle', pages: [] } }) {
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

                    {/* Global header/footer editing moved into each page Builder. */}

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
                            return <div><div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p className="cosmic-pages-section-title text-sm font-semibold">Standard Pages</p><p className="cosmic-pages-section-copy mt-1 text-sm">Open a page in Builder to edit its sections, global header, and global footers and layout.</p></div><div className="flex flex-wrap items-center gap-2"><span className="text-xs text-slate-500">{visiblePages.length} total</span>{(pages || []).length === 0 ? <button type="button" onClick={() => window.dispatchEvent(new CustomEvent('cosmic:luna-open-starter-site',{detail:{websiteId:website.id,websiteName:website.name,starterSite}}))} className="inline-flex h-9 items-center rounded-xl border border-violet-400/25 bg-violet-400/[0.08] px-3.5 text-xs font-semibold text-violet-200 transition hover:border-violet-300/50 hover:bg-violet-400/[0.14]">{['queued', 'building'].includes(starterSite?.status) ? `✦ Building ${starterSite.progress || 0}%` : starterSite?.installed ? 'View Starter Pages' : 'Install Starter Pages'}</button> : null}</div></div>{visiblePages.length ? <PageList pages={visiblePages} onDelete={deletePage} onAddChild={openNewPage} onEditTitle={setEditingPage} onClone={clonePage} /> : <PageEmptyState onNewPage={() => openNewPage()} />}</div>;
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
                        <h3 className="text-lg font-semibold text-gray-900 mb-1">Create New Dynamic Page</h3>
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

                    {/* Header and footer editing now lives exclusively inside Builder. */}

                    {/* PAGES ARCHITECTURE LIST */}
                    <div className="hidden p-6 bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100">
                        <h3 className="text-lg font-semibold text-gray-900 mb-4">Website Pages Architecture</h3>
                        
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
            


            

            {/* GLOBAL FOOTER MODAL POPUP SYSTEM */}
            
        </WebsiteWorkspaceShell>
    );
}
