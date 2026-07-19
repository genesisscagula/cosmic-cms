import { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { DarkCyanHeader, GlassmorphismHeader } from './GenerateHeader';
import { MinimalFooter, DetailedFooter } from './GenerateFooter';

export default function Index({ website, pages, globalHeaderBlock, globalFooterBlock }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
    });

    const [isHeaderModalOpen, setIsHeaderModalOpen] = useState(false);
    const [savedHeader, setSavedHeader] = useState(globalHeaderBlock || null);
    const [isSaving, setIsSaving] = useState(false);
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
        setSavedHeader(globalHeaderBlock || null);
    }, [globalHeaderBlock]);


    useEffect(() => {
        // Kung naay gipasa nga props, i-update ang state
        if (globalFooterBlock) {
            setSavedFooter(globalFooterBlock);
        }
    }, [globalFooterBlock]);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('pages.store', website.id), {
            onSuccess: () => reset(),
        });
    };

    const updateHeaderContent = (updatedFields) => {
        setSavedHeader(prev => ({ ...prev, ...updatedFields }));
    };

    const saveHeaderToDatabase = async () => {
        setIsSaving(true);
        
        // I-LOG NATO ARON MAKITA ANG VALUE SULOD SA CONSOLE
        console.log("Checking target website ID packet, bai:", website);
        console.log("Axios Target URL:", `/websites/${website?.id}/global-header/save`);

        try {
            const response = await axios.post(`/websites/${website.id}/global-header/save`, {
                header_block: savedHeader
            });
            
            if (response.data.status === 'success') {
                alert('Global Header updated and synchronized completely, Bai!');
                router.reload({ 
                    only: ['globalHeaderBlock'],
                    onSuccess: () => {
                        setIsHeaderModalOpen(false);
                    }
                });
            }
        } catch (error) {
            console.error("Full Axios Error Context:", error.response || error);
            alert('Failed to synchronize global configuration matrix.');
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
                alert('Footer updated and synchronized completely, Bai!');
                router.reload({ 
                    only: ['globalFooterBlock'],
                    onSuccess: () => {
                        setIsFooterModalOpen(false);
                    }
                });
            }
        } catch (error) {
            console.error("Full Axios Error Context:", error.response || error);
            alert('Failed to synchronize global footer configuration.');
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <AuthenticatedLayout
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

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    
                    {/* INPUT FORM PANEL */}
                    <div className="p-6 bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100">
                        <h3 className="text-lg font-medium text-gray-900 mb-1">Create New Dynamic Page</h3>
                        <p className="text-xs text-gray-500 mb-4">I-add ang ngalan sa page (e.g., Home, About Us) aron automatic mag-generate og dynamic route packet.</p>
                        
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
                    <div className="p-6 bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg border border-slate-800 text-white">
                        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="px-2 py-0.5 text-[10px] uppercase tracking-wider bg-purple-500/20 text-purple-300 rounded font-bold font-mono">Global Layout Matrix</span>
                                    <span className="animate-ping w-2 h-2 rounded-full bg-emerald-400"></span>
                                </div>
                                <h3 className="text-lg font-bold mt-1 text-white">Website Shell: Global Header & Footer</h3>
                                <p className="text-xs text-slate-400 max-w-xl mt-0.5">
                                    I-configure ang AI generated layouts, brand logos, ug custom footers nga mo-salida sa tibuok system.
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
                    <div className="p-6 bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100">
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
                <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
                    <div className="bg-slate-900 border border-slate-800 p-6 md:p-8 rounded-2xl w-full max-w-4xl shadow-2xl text-slate-100 max-h-[90vh] overflow-y-auto font-sans">
                        <div className="flex justify-between items-center mb-6">
                            <div>
                                <h2 className="text-xl font-extrabold text-white">⚙️ Global Header Architecture Controller</h2>
                                <p className="text-xs text-slate-400 mt-1">Pili ug i-edit ang layout nga gamiton sa tibuok website configuration.</p>
                            </div>
                            <button onClick={() => setIsHeaderModalOpen(false)} className="text-slate-400 hover:text-white">✕</button>
                        </div>

                        {/* LIVE PREVIEW FIELD */}
                        <div className="mb-8 bg-slate-950 p-4 rounded-xl border border-slate-800">
                            <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Live Structural Frame Preview</h3>
                            {savedHeader ? (
                                <div className="w-full">
                                    {savedHeader.type === 'dark_cyan_header' && <DarkCyanHeader block={savedHeader} onUpdate={updateHeaderContent} />}
                                    {savedHeader.type === 'glassmorphism_header' && <GlassmorphismHeader block={savedHeader} onUpdate={updateHeaderContent} />}
                                </div>
                            ) : (
                                <div className="text-center py-8 text-sm text-slate-500">
                                    No header setup currently active. Select a blueprint option down below to initialize.
                                </div>
                            )}
                        </div>

                        {/* BLUEPRINTS ARCHIVE */}
                        <div className="border-t border-slate-800 pt-6">
                            <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Available Layout Architecture Options</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                
                                {/* TEMPLATE INJECT BUTTON 1 */}
                                <div className="border border-slate-800 bg-slate-950/30 p-4 rounded-xl flex flex-col justify-between space-y-3">
                                    <div>
                                        <h4 className="text-sm font-bold text-white">Dark Cyan minimal Navigation</h4>
                                        <p className="text-xs text-slate-400 mt-1">Clean slate framework utilizing teal neon elements and standard static navigation trees.</p>
                                    </div>
                                    <button 
                                        type="button"
                                        onClick={() => updateHeaderContent({
                                            type: 'dark_cyan_header',
                                            logo_text: 'AkongLogo',
                                            menu: [{ label: 'Home', url: '#' }, { label: 'About', url: '#' }, { label: 'Services', url: '#' }]
                                        })}
                                        className="w-full bg-cyan-600 hover:bg-cyan-500 text-white text-xs py-2 rounded-md font-bold transition"
                                    >
                                        🛠️ Apply/Switch to Layout
                                    </button>
                                </div>

                                {/* TEMPLATE INJECT BUTTON 2 */}
                                <div className="border border-slate-800 bg-slate-950/30 p-4 rounded-xl flex flex-col justify-between space-y-3">
                                    <div>
                                        <h4 className="text-sm font-bold text-white">Glassmorphism tracking Shell</h4>
                                        <p className="text-xs text-slate-400 mt-1">Sophisticated responsive system that packages customizable high-conversion Action Links.</p>
                                    </div>
                                    <button 
                                        type="button"
                                        onClick={() => updateHeaderContent({
                                            type: 'glassmorphism_header',
                                            logo_text: 'DesignKaBai',
                                            cta_label: 'Get Started',
                                            menu: [{ label: 'Home', url: '#' }, { label: 'About', url: '#' }, { label: 'Services', url: '#' }, { label: 'Blog', url: '#' }]
                                        })}
                                        className="w-full bg-rose-600 hover:bg-rose-500 text-white text-xs py-2 rounded-md font-bold transition"
                                    >
                                        🛠️ Apply/Switch to Layout
                                    </button>
                                </div>

                            </div>
                        </div>

                        {/* MASTER SUBMIT CONTROL SYSTEM PANEL */}
                        <div className="mt-8 border-t border-slate-800 pt-4 flex justify-end gap-3">
                            <button 
                                type="button" 
                                onClick={() => setIsHeaderModalOpen(false)}
                                className="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-semibold"
                            >
                                Cancel
                            </button>
                            <button 
                                type="button"
                                onClick={saveHeaderToDatabase}
                                disabled={isSaving || !savedHeader}
                                className="px-6 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-emerald-900/40"
                            >
                                {isSaving ? 'Synchronizing Node...' : '💾 Save Shell configuration'}
                            </button>
                        </div>

                    </div>
                </div>
            )}


            {/* GLOBAL FOOTER MODAL POPUP SYSTEM */}
            {isFooterModalOpen && (
                <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
                    <div className="bg-slate-900 border border-slate-800 p-6 md:p-8 rounded-2xl w-full max-w-4xl shadow-2xl text-slate-100 max-h-[90vh] overflow-y-auto font-sans">
                        <div className="flex justify-between items-center mb-6">
                            <div>
                                <h2 className="text-xl font-extrabold text-white">⚙️ Global Footer Architecture Controller</h2>
                                <p className="text-xs text-slate-400 mt-1">Pili ug i-edit ang footer layout para sa tibuok website.</p>
                            </div>
                            <button onClick={() => setIsFooterModalOpen(false)} className="text-slate-400 hover:text-white">✕</button>
                        </div>

                        {/* LIVE PREVIEW FIELD */}
                        <div className="mb-8 bg-slate-950 p-4 rounded-xl border border-slate-800">
                            <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Live Structural Frame Preview</h3>
                            {savedFooter ? (
                                <div className="w-full">
                                    {savedFooter.type === 'minimal_footer' && <MinimalFooter block={savedFooter} onUpdate={updateFooterContent} />}
                                    {savedFooter.type === 'detailed_footer' && <DetailedFooter block={savedFooter} onUpdate={updateFooterContent} />}
                                </div>
                            ) : (
                                <div className="text-center py-8 text-sm text-slate-500">
                                    No footer setup active. Select a blueprint below to initialize.
                                </div>
                            )}
                        </div>

                        {/* BLUEPRINTS ARCHIVE */}
                        <div className="border-t border-slate-800 pt-6">
                            <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Available Layout Architecture Options</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                
                                {/* TEMPLATE 1: MINIMAL */}
                                <div className="border border-slate-800 bg-slate-950/30 p-4 rounded-xl flex flex-col justify-between space-y-3">
                                    <div>
                                        <h4 className="text-sm font-bold text-white">Minimal Footer</h4>
                                        <p className="text-xs text-slate-400 mt-1">Simple, clean layout focused on essential navigation and branding.</p>
                                    </div>
                                    <button 
                                        type="button"
                                        onClick={() => updateFooterContent({ 
                                            type: 'minimal_footer', 
                                            copyright: '© 2026. All rights reserved.' // I-usab ni
                                        })}
                                        className="w-full bg-cyan-600 hover:bg-cyan-500 text-white text-xs py-2 rounded-md font-bold transition"
                                    >
                                        🛠️ Apply/Switch to Layout
                                    </button>
                                </div>

                                {/* TEMPLATE 2: DETAILED */}
                                <div className="border border-slate-800 bg-slate-950/30 p-4 rounded-xl flex flex-col justify-between space-y-3">
                                    <div>
                                        <h4 className="text-sm font-bold text-white">Detailed Footer</h4>
                                        <p className="text-xs text-slate-400 mt-1">Comprehensive footer with multi-column links and newsletter subscription.</p>
                                    </div>
                                    <button 
                                        type="button"
                                        onClick={() => updateFooterContent({ type: 'detailed_footer', description: 'Sample description' })}
                                        className="w-full bg-rose-600 hover:bg-rose-500 text-white text-xs py-2 rounded-md font-bold transition"
                                    >
                                        🛠️ Apply/Switch to Layout
                                    </button>
                                </div>

                            </div>
                        </div>

                        {/* MASTER SUBMIT */}
                        <div className="mt-8 border-t border-slate-800 pt-4 flex justify-end gap-3">
                            <button onClick={() => setIsFooterModalOpen(false)} className="px-4 py-2 bg-slate-800 rounded-xl text-xs font-semibold">Cancel</button>
                            <button onClick={saveFooterToDatabase} className="px-6 py-2 bg-emerald-600 rounded-xl text-xs font-bold transition">
                                💾 Save Footer configuration
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}