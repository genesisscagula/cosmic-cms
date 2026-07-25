import { useState, useEffect } from 'react';
import { Head, useForm, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { DarkCyanHeader, GlassmorphismHeader } from './GenerateHeader';
import { MinimalFooter, DetailedFooter } from './GenerateFooter';
import WebsiteWorkspaceHeader from './Components/WebsiteWorkspaceHeader';
import NewPagePanel from './Components/NewPagePanel';
import PageList from './Components/PageList';
import PageEmptyState from './Components/PageEmptyState';
import WebsiteLaunchGuide from './Components/WebsiteLaunchGuide';
import { confirmCosmicAction, showCosmicNotification } from '../../Components/CosmicNotification';

const WebsiteWorkspaceShell = ({ children }) => <>{children}</>;
const legacyHeaderLogoSamples = new Set(['AkongLogo', 'DesignKaBai', 'CosmicCMS']);

const replaceLegacyHeaderLogo = (header, websiteName) => {
    if (!header || !legacyHeaderLogoSamples.has((header.logo_text || '').trim())) {
        return header;
    }

    return { ...header, logo_text: websiteName };
};

export default function Index({ website, pages, globalHeaderBlock, globalFooterBlock }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
    });

    const [isHeaderModalOpen, setIsHeaderModalOpen] = useState(false);
    const [isNewPageOpen, setIsNewPageOpen] = useState(false);
    const [savedHeader, setSavedHeader] = useState(() => replaceLegacyHeaderLogo(globalHeaderBlock, website.name));
    const [isSaving, setIsSaving] = useState(false);
    const [isPushingLive, setIsPushingLive] = useState(false);
    // 2. Add state para sa footer modal[cite: 2]
    const [isFooterModalOpen, setIsFooterModalOpen] = useState(false);
    
    // I-set ang default nga object kung null ang globalFooterBlock
    const [savedFooter, setSavedFooter] = useState(globalFooterBlock || { 
        type: 'minimal_footer', 
        logo_text: 'CosmicCMS', 
        copyright: '© 2026. All rights reserved.' 
    });

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
            setSavedFooter(globalFooterBlock);
        }
    }, [globalFooterBlock]);

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
                setIsNewPageOpen(false);
            },
        });
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

    const updateHeaderMenuItem = (index, field, value) => {
        setSavedHeader((currentHeader) => {
            const menu = Array.isArray(currentHeader?.menu) ? [...currentHeader.menu] : [];
            menu[index] = { ...menu[index], [field]: value };

            return { ...currentHeader, menu };
        });
    };

    const publishedPageTargets = pages
        .filter((page) => page.status === 'published')
        .map((page) => ({ title: page.title, slug: page.slug }));

    const saveHeaderToDatabase = async () => {
        setIsSaving(true);
        
        try {
            const response = await axios.post(`/websites/${website.id}/global-header/save`, {
                header_block: savedHeader
            });
            
            if (response.data.status === 'success') {
                showCosmicNotification({ title: 'Header saved', message: 'Use Push live update when you are ready to send this header to the live site.', tone: 'success' });
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
                showCosmicNotification({ title: 'Footer saved', message: 'Use Push live update when you are ready to send this footer to the live site.', tone: 'success' });
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

    const pushLiveUpdate = async () => {
        if (!await confirmCosmicAction({ title: 'Push live update?', message: `All published pages for ${website.name} will be sent to the connected live site.`, confirmLabel: 'Push update', tone: 'info' })) return;

        setIsPushingLive(true);

        try {
            const response = await axios.post(route('websites.deployment-connector.push', website.id));
            showCosmicNotification({ title: 'Live site updated', message: response.data.message, tone: 'success' });
        } catch (error) {
            showCosmicNotification({ title: 'Live update failed', message: error.response?.data?.message || 'The live update could not be pushed.', tone: 'error' });
        } finally {
            setIsPushingLive(false);
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

            <div className="min-h-screen bg-[#0a0a0b] px-4 py-6 text-slate-100 sm:px-6 lg:px-10 lg:py-10">
                <div className="mx-auto max-w-6xl space-y-7">
                    <WebsiteWorkspaceHeader website={website} pageCount={pages?.length || 0} themeSummary={themeSummary} onNewPage={() => setIsNewPageOpen(true)} onPushLive={pushLiveUpdate} pushingLive={isPushingLive} />

                    <WebsiteLaunchGuide pages={pages || []} onNewPage={() => setIsNewPageOpen(true)} />

                    <section className="rounded-2xl border border-white/10 bg-white/[0.035] p-4 sm:flex sm:items-center sm:justify-between sm:gap-5">
                        <div><p className="text-sm font-semibold text-white">Website shell</p><p className="mt-1 text-sm text-slate-400">Configure the shared header and footer used across this website.</p></div>
                        <div className="mt-4 flex gap-2 sm:mt-0"><button type="button" onClick={() => { setSavedHeader(replaceLegacyHeaderLogo(globalHeaderBlock, website.name)); setIsHeaderModalOpen(true); }} className="rounded-lg border border-white/10 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">Edit Header</button><button type="button" onClick={() => setIsFooterModalOpen(true)} className="rounded-lg border border-white/10 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">Edit Footer</button></div>
                    </section>

                    <section><div className="mb-3 flex items-center justify-between"><div><p className="text-sm font-semibold text-white">Pages</p><p className="mt-1 text-sm text-slate-400">Open a page in Builder to edit its blocks and layout.</p></div><span className="text-xs text-slate-500">{pages?.length || 0} total</span></div>{pages?.length ? <PageList pages={pages} /> : <PageEmptyState onNewPage={() => setIsNewPageOpen(true)} />}</section>

                    <NewPagePanel open={isNewPageOpen} onClose={() => setIsNewPageOpen(false)} data={data} setData={setData} errors={errors} processing={processing} onSubmit={handleSubmit} />
                    
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
                    <div role="dialog" aria-modal="true" aria-labelledby="edit-header-title" className="relative h-[min(88dvh,900px)] max-h-[calc(100dvh-2rem)] w-full max-w-5xl overflow-y-auto rounded-2xl border border-white/10 bg-[#151519] p-5 text-slate-100 shadow-2xl shadow-black/50 sm:p-6 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-700 hover:[&::-webkit-scrollbar-thumb]:bg-violet-500/70">
                        <div className="mb-5 flex items-start justify-between gap-4">
                            <div>
                                <h2 id="edit-header-title" className="text-xl font-semibold text-white">Edit global header</h2>
                                <p className="mt-1 text-sm text-slate-400">Choose the header used across this website. Publish a page when you are ready to send changes live.</p>
                            </div>
                            <button type="button" disabled={isSaving} onClick={() => setIsHeaderModalOpen(false)} className="flex h-9 w-9 items-center justify-center rounded-lg text-lg text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Close">×</button>
                        </div>

                        {/* LIVE PREVIEW FIELD */}
                        <div className="mb-6 rounded-xl border border-white/10 bg-black/20 p-3">
                            <h3 className="mb-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Preview</h3>
                            {savedHeader ? (
                                <div className="w-full">
                                    {savedHeader.type === 'dark_cyan_header' && <DarkCyanHeader block={savedHeader} onUpdate={updateHeaderContent} />}
                                    {savedHeader.type === 'glassmorphism_header' && <GlassmorphismHeader block={savedHeader} onUpdate={updateHeaderContent} globalTheme={globalTheme} />}
                                </div>
                            ) : (
                                <div className="py-7 text-center text-sm text-slate-500">
                                    Choose a header layout to preview it here.
                                </div>
                            )}
                        </div>

                        {savedHeader && (
                            <div className="mb-6 border-t border-white/10 pt-5">
                                <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <h3 className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Menu links</h3>
                                        <p className="mt-1 text-xs leading-5 text-slate-400">
                                            Use the exact published page slug for static links. <span className="text-slate-300">home</span> opens the homepage; <span className="text-slate-300">about</span> becomes <span className="text-slate-300">/about</span> after Push live update.
                                        </p>
                                    </div>
                                    <p className="text-[11px] text-slate-500">External URLs and #section anchors stay unchanged.</p>
                                </div>

                                <datalist id="published-page-slugs">
                                    {publishedPageTargets.map((page) => (
                                        <option key={page.slug} value={page.slug}>{page.title}</option>
                                    ))}
                                </datalist>

                                <div className="mt-3 space-y-2">
                                    {(savedHeader.menu || []).map((item, index) => (
                                        <div key={`${item.label || 'menu'}-${index}`} className="grid grid-cols-1 gap-2 rounded-lg border border-white/10 bg-white/[0.025] p-2.5 sm:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                                            <label className="min-w-0">
                                                <span className="mb-1 block text-[10px] font-medium uppercase tracking-[0.12em] text-slate-500">Menu label</span>
                                                <input
                                                    type="text"
                                                    value={item.label || ''}
                                                    onChange={(event) => updateHeaderMenuItem(index, 'label', event.target.value)}
                                                    className="w-full rounded-md border border-white/10 bg-black/20 px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20"
                                                />
                                            </label>
                                            <label className="min-w-0">
                                                <span className="mb-1 block text-[10px] font-medium uppercase tracking-[0.12em] text-slate-500">Link target</span>
                                                <input
                                                    type="text"
                                                    list="published-page-slugs"
                                                    value={item.url || ''}
                                                    onChange={(event) => updateHeaderMenuItem(index, 'url', event.target.value)}
                                                    placeholder="home, about, #contact, or https://..."
                                                    className="w-full rounded-md border border-white/10 bg-black/20 px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20"
                                                />
                                            </label>
                                        </div>
                                    ))}
                                </div>

                                {savedHeader.type === 'glassmorphism_header' && (
                                    <div className="mt-2 grid grid-cols-1 gap-2 rounded-lg border border-violet-400/15 bg-violet-400/[0.035] p-2.5 sm:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                                        <label className="min-w-0">
                                            <span className="mb-1 block text-[10px] font-medium uppercase tracking-[0.12em] text-violet-200/70">CTA label</span>
                                            <input
                                                type="text"
                                                value={savedHeader.cta_label || 'Get Started'}
                                                onChange={(event) => updateHeaderContent({ cta_label: event.target.value })}
                                                className="w-full rounded-md border border-white/10 bg-black/20 px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20"
                                            />
                                        </label>
                                        <label className="min-w-0">
                                            <span className="mb-1 block text-[10px] font-medium uppercase tracking-[0.12em] text-violet-200/70">CTA link target</span>
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
                                disabled={isSaving || !savedHeader}
                                className="rounded-lg bg-white px-4 py-2 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {isSaving ? 'Saving...' : 'Save header'}
                            </button>
                        </div>

                    </div>
                </div>
            )}


            {/* GLOBAL FOOTER MODAL POPUP SYSTEM */}
            {isFooterModalOpen && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
                    <button type="button" aria-label="Close footer dialog" onClick={() => !isSaving && setIsFooterModalOpen(false)} className="absolute inset-0 cursor-default" />
                    <div role="dialog" aria-modal="true" aria-labelledby="edit-footer-title" className="relative max-h-[calc(100dvh-2rem)] w-full max-w-3xl overflow-y-auto rounded-2xl border border-white/10 bg-[#151519] p-5 text-slate-100 shadow-2xl shadow-black/50 sm:p-6">
                        <div className="mb-5 flex items-start justify-between gap-4">
                            <div>
                                <h2 id="edit-footer-title" className="text-xl font-semibold text-white">Edit global footer</h2>
                                <p className="mt-1 text-sm text-slate-400">Choose the footer used across this website.</p>
                            </div>
                            <button type="button" disabled={isSaving} onClick={() => setIsFooterModalOpen(false)} className="flex h-9 w-9 items-center justify-center rounded-lg text-lg text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Close">×</button>
                        </div>

                        {/* LIVE PREVIEW FIELD */}
                        <div className="mb-6 rounded-xl border border-white/10 bg-black/20 p-3">
                            <h3 className="mb-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Preview</h3>
                            {savedFooter ? (
                                <div className="w-full">
                                    {savedFooter.type === 'minimal_footer' && <MinimalFooter block={savedFooter} onUpdate={updateFooterContent} />}
                                    {savedFooter.type === 'detailed_footer' && <DetailedFooter block={savedFooter} onUpdate={updateFooterContent} />}
                                </div>
                            ) : (
                                <div className="py-7 text-center text-sm text-slate-500">
                                    Choose a footer layout to preview it here.
                                </div>
                            )}
                        </div>

                        {/* BLUEPRINTS ARCHIVE */}
                        <div className="border-t border-white/10 pt-5">
                            <h3 className="mb-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Footer layouts</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                
                                {/* TEMPLATE 1: MINIMAL */}
                                <div className={`flex flex-col justify-between space-y-3 rounded-xl border p-4 transition ${savedFooter?.type === 'minimal_footer' ? 'border-violet-400/70 bg-violet-400/[0.07] ring-1 ring-violet-400/30' : 'border-white/10 bg-white/[0.03] hover:border-white/20'}`}>
                                    <div>
                                        <h4 className="text-sm font-semibold text-white">Minimal footer</h4>
                                        <p className="mt-1 text-xs leading-5 text-slate-400">A compact footer with brand and copyright.</p>
                                    </div>
                                    <button 
                                        type="button"
                                        onClick={() => updateFooterContent({ 
                                            type: 'minimal_footer', 
                                            copyright: '© 2026. All rights reserved.' // I-usab ni
                                        })}
                                        className="w-full rounded-lg border border-white/10 bg-white px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        {savedFooter?.type === 'minimal_footer' ? 'Selected' : 'Use layout'}
                                    </button>
                                </div>

                                {/* TEMPLATE 2: DETAILED */}
                                <div className={`flex flex-col justify-between space-y-3 rounded-xl border p-4 transition ${savedFooter?.type === 'detailed_footer' ? 'border-violet-400/70 bg-violet-400/[0.07] ring-1 ring-violet-400/30' : 'border-white/10 bg-white/[0.03] hover:border-white/20'}`}>
                                    <div>
                                        <h4 className="text-sm font-semibold text-white">Detailed footer</h4>
                                        <p className="mt-1 text-xs leading-5 text-slate-400">A footer with additional navigation links.</p>
                                    </div>
                                    <button 
                                        type="button"
                                        onClick={() => updateFooterContent({ type: 'detailed_footer', description: 'Sample description' })}
                                        className="w-full rounded-lg border border-white/10 bg-white px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        {savedFooter?.type === 'detailed_footer' ? 'Selected' : 'Use layout'}
                                    </button>
                                </div>

                            </div>
                        </div>

                        {/* MASTER SUBMIT */}
                        <div className="mt-6 flex justify-end gap-2 border-t border-white/10 pt-4">
                            <button type="button" disabled={isSaving} onClick={() => setIsFooterModalOpen(false)} className="rounded-lg px-4 py-2 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50">Cancel</button>
                            <button type="button" disabled={isSaving || !savedFooter} onClick={saveFooterToDatabase} className="rounded-lg bg-white px-4 py-2 text-xs font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50">
                                {isSaving ? 'Saving...' : 'Save footer'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </WebsiteWorkspaceShell>
    );
}
