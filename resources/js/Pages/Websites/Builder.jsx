import { useEffect, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';

import AddSectionModal from "./Components/AddSectionModal";

import ThemeSelector from "./Theme/ThemeSelector";

import { BlockRegistry } from "./BlockRegistry";
import { DarkCyanHeader, GlassmorphismHeader } from './GenerateHeader';

import { MinimalFooter, DetailedFooter } from './GenerateFooter';



export default function Builder({ page, website }) {
    const { props } = usePage();
    const defaultHeader = {
        type: 'glassmorphism_header',
        logo_text: website?.name || 'Your Website',
        cta_label: 'Get Started',
        menu: [
            { label: 'Home', url: '#' },
            { label: 'About', url: '#' },
            { label: 'Services', url: '#' },
        ],
    };
    // Gi-apil na ang global_header sa form state
    const { data, setData, setDefaults, isDirty } = useForm({
        blocks: page.blocks || [],
        global_header: props.globalHeaderBlock || page.website?.global_header || defaultHeader,
        global_footer: props.globalFooterBlock || page.website?.global_footer || { 
            type: 'minimal_footer', 
            logo_text: website?.name || 'Your Website', 
            copyright: '© 2026. All rights reserved.' 
        }
    });

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [aiResult, setAiResult] = useState(null);
    const [aiLoading, setAiLoading] = useState(false);

    const [themeMenu, setThemeMenu] = useState(null);
    const [isSaving, setIsSaving] = useState(false);
    const [isPublishing, setIsPublishing] = useState(false);
    const [saveError, setSaveError] = useState('');
    const [hasUnsavedTheme, setHasUnsavedTheme] = useState(false);
    const [pageStatus, setPageStatus] = useState(page.status || 'draft');
    const [publishError, setPublishError] = useState(page.publish_error || '');
    const hasUnsavedChanges = isDirty || hasUnsavedTheme;

    useEffect(() => {
        const warnBeforeLeaving = (event) => {
            if (!hasUnsavedChanges || isSaving || isPublishing) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', warnBeforeLeaving);

        return () => window.removeEventListener('beforeunload', warnBeforeLeaving);
    }, [hasUnsavedChanges, isPublishing, isSaving]);

    // Update logic para sa mga blocks
    const updateBlockContent = (index, updatedFields) => {
        const updatedBlocks = [...data.blocks];
        updatedBlocks[index] = { ...updatedBlocks[index], ...updatedFields };
        setData('blocks', updatedBlocks);
    };

    // Bag-ong logic para ma-update ang global header
    const updateHeader = (updatedFields) => {
        setData('global_header', { ...data.global_header, ...updatedFields });
    };

    const updateFooter = (updatedFields) => {
        setData('global_footer', { ...data.global_footer, ...updatedFields });
    };

    const replaceBlocks = (newBlocks) => {

        setData(
            "blocks",
            newBlocks.map(block => ({
                ...block,
                _renderKey: crypto.randomUUID()
            }))
        );

        setIsModalOpen(false);
    };

    const addBlock = (block) => {

        const newBlock = {
            ...block,
            theme: block.theme || "auto"
        };

        setData("blocks", [
            ...data.blocks,
            newBlock
        ]);

        setIsModalOpen(false);

    };
    const removeBlock = (index) => {

        const updatedBlocks = data.blocks.filter((_, i) => i !== index);

        console.log("Before:", data.blocks.length);
        console.log("After:", updatedBlocks.length);

        setData("blocks", updatedBlocks);

    };

    const generateWithAI = async (prompt) => {
        setAiLoading(true);
        try {
            const response = await axios.post('/ai/generate', { prompt });
            setAiResult(response.data);
        } catch (error) {
            alert('Error generating AI block.');
        } finally {
            setAiLoading(false);
        }
    };

    const saveDraft = async () => {
        setIsSaving(true);
        setSaveError('');

        try {
            await axios.post(route('pages.builder.save', page.id), {
                blocks: data.blocks,
                global_header: data.global_header,
                global_footer: data.global_footer,
                theme_settings: globalSelections,
            });

            setDefaults();
            setHasUnsavedTheme(false);
            return true;
        } catch (error) {
            console.error(error);
            setSaveError(
                error.response?.data?.message ||
                'Unable to save your changes. Please try again.'
            );
            return false;
        } finally {
            setIsSaving(false);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        await saveDraft();
    };

    const handlePublish = async () => {
        setPublishError('');

        if (hasUnsavedChanges) {
            const saved = await saveDraft();

            if (!saved) {
                return;
            }
        }

        setIsPublishing(true);

        try {
            const response = await axios.post(route('pages.publish', page.id));
            setPageStatus(response.data.status || 'published');
        } catch (error) {
            setPublishError(
                error.response?.data?.message ||
                'Publishing failed. Your previous live version is still available.'
            );
        } finally {
            setIsPublishing(false);
        }
    };

    const moveBlock = (index, direction) => {
        const newBlocks = [...data.blocks];
        const targetIndex = direction === 'up' ? index - 1 : index + 1;

        // Check kung valid ang move
        if (targetIndex >= 0 && targetIndex < newBlocks.length) {
            [newBlocks[index], newBlocks[targetIndex]] = [newBlocks[targetIndex], newBlocks[index]];
            setData('blocks', newBlocks);
        }
    };

    const [globalTheme, setGlobalTheme] = useState('dark');

    const [globalSelections, setGlobalSelections] = useState(() => {
        // 1. Define ang imong mga default
        const defaults = {
            primary: 'emerald',
            secondary: 'white',
            tertiary: 'stone',

            auto: true
        };

        let savedSettings = {};

        // 2. Sulayi og parse ang gikan sa DB
        if (typeof website.theme_settings === 'string') {
            try {
                savedSettings = JSON.parse(website.theme_settings);
            } catch (e) {
                console.error("Error parsing theme_settings:", e);
            }
        } else if (typeof website.theme_settings === 'object' && website.theme_settings !== null) {
            savedSettings = website.theme_settings;
        }

        // 3. I-merge ang defaults ug ang savedSettings
        // Ang savedSettings ang mo-override sa defaults kon naa na silay value
        return { ...defaults, ...savedSettings, secondary: 'white', tertiary: 'stone'};
    });


    const resolveBlockTheme = (block, index) => {

        if (block.theme && block.theme !== "auto") {
            return block.theme;
        }

        const pattern = [
            "primary",
            "white",
            "surface",
            "white"
        ];

        return pattern[index % pattern.length];

    };

    const duplicateBlock = (index) => {

        const blocks = [...data.blocks];

        const duplicated = structuredClone
            ? structuredClone(blocks[index])
            : JSON.parse(JSON.stringify(blocks[index]));

        // keep auto as default if missing
        duplicated.theme = duplicated.theme || "auto";

        // insert directly below current block
        blocks.splice(index + 1, 0, duplicated);

        setData("blocks", blocks);

    };


    const renderBlock = (block, index) => {

        const resolvedTheme = resolveBlockTheme(block, index);

        const blockProps = {

            block: {
                ...block,
                resolvedTheme
            },

            globalTheme: globalSelections,

            onUpdate: (fields) => updateBlockContent(index, fields),

            blockIndex: index

        };


        const registryItem = BlockRegistry[block.type];

        if (registryItem) {
            const Component = registryItem.component;

            return (
                <Component
                    key={block._renderKey || index}
                    {...blockProps}
                />
            );
        }

        switch (block.type) {

            case "hero":

                const heroClass =
                    globalSelections?.primary === "emerald"
                        ? "bg-emerald-900"
                        : "bg-slate-900";

                return (
                    <section
                        key={index}
                        className={`py-20 px-8 text-center text-white shadow-xl w-full ${heroClass}`}
                    >
                        <h1 className="text-5xl font-extrabold mb-4">
                            {block.heading}
                        </h1>

                        <p className="text-xl text-slate-200">
                            {block.subheading}
                        </p>

                    </section>
                );

            case "content":

                return (
                    <section
                        key={index}
                        className="py-10 px-8 bg-white border-b border-slate-200 w-full"
                    >
                        <p className="text-lg text-slate-700 leading-relaxed">
                            {block.text}
                        </p>
                    </section>
                );

            default:

                return (
                    <div
                        key={index}
                        className="p-4 text-center text-xs text-red-400"
                    >
                        Unknown Block: {block.type}
                    </div>
                );

        }

    };
    
    return (
        <>
            <Head title={`Builder — ${page.title}`} />
            <div className="min-h-screen bg-[#09090b] text-slate-100">
                <header className="sticky top-0 z-[60] border-b border-white/10 bg-[#0d0d10]/95 backdrop-blur-xl">
                    <div className="mx-auto flex min-h-16 max-w-[1600px] flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                        <Link
                            href={route('pages.index', website.id)}
                            onClick={(event) => {
                                if (hasUnsavedChanges && !window.confirm('You have unsaved changes. Leave the Builder without saving them?')) {
                                    event.preventDefault();
                                }
                            }}
                            className="inline-flex items-center gap-2 rounded-lg px-2 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400"
                        >
                            <span aria-hidden="true">←</span>
                            <span className="hidden sm:inline">Back to Pages</span>
                        </Link>

                        <div className="min-w-0 border-l border-white/10 pl-3 sm:pl-4">
                            <p className="truncate text-xs font-medium text-slate-400">{website.name || 'Cosmic CMS'}</p>
                            <div className="flex min-w-0 items-center gap-2">
                                <span className="truncate text-sm font-semibold text-white">{page.title || 'Untitled page'}</span>
                                <span className="hidden text-xs text-slate-500 sm:inline">/{page.slug}</span>
                            </div>
                        </div>

                        <div className="ml-auto flex flex-wrap items-center justify-end gap-2">
                            <span title={publishError || saveError || undefined} className={`hidden items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium sm:inline-flex ${isPublishing ? 'border-sky-400/30 bg-sky-400/10 text-sky-200' : publishError ? 'border-red-400/30 bg-red-400/10 text-red-200' : pageStatus === 'published' ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' : 'border-amber-400/30 bg-amber-400/10 text-amber-200'}`}>
                                <span className={`h-1.5 w-1.5 rounded-full ${isPublishing ? 'bg-sky-300 animate-pulse' : publishError ? 'bg-red-300' : pageStatus === 'published' ? 'bg-emerald-300' : 'bg-amber-300'}`} />
                                {isPublishing ? 'Publishing...' : publishError ? 'Publish Failed' : pageStatus === 'published' ? 'Published' : 'Draft'}
                            </span>
                            {hasUnsavedChanges && !isSaving && !isPublishing && (
                                <span className="hidden items-center gap-1.5 rounded-full border border-amber-400/30 bg-amber-400/10 px-2.5 py-1 text-[11px] font-medium text-amber-100 lg:inline-flex">
                                    <span className="h-1.5 w-1.5 rounded-full bg-amber-300" />
                                    Unsaved changes
                                </span>
                            )}
                            <span className="hidden rounded-lg border border-white/10 px-2.5 py-1 text-[11px] font-medium text-slate-400 md:inline-flex">{data.blocks.length} blocks</span>
                            <ThemeSelector
                                compact
                                value={globalSelections.primary}
                                onChange={(theme) => {
                                    setGlobalSelections(prev => ({ ...prev, primary: theme }));
                                    setHasUnsavedTheme(true);
                                }}
                            />
                            <button
                                type="button"
                                onClick={() => setIsModalOpen(true)}
                                className="inline-flex h-9 items-center rounded-lg border border-violet-400/30 bg-violet-500/15 px-3 text-xs font-semibold text-violet-100 transition hover:bg-violet-500/25 focus:outline-none focus:ring-2 focus:ring-violet-400"
                            >
                                <span className="mr-1" aria-hidden="true">+</span> Add Section
                            </button>
                            <form onSubmit={handleSubmit}>
                                <button
                                    type="submit"
                                    disabled={isSaving || isPublishing}
                                    className="inline-flex h-9 items-center rounded-lg border border-white/15 bg-white/[0.08] px-3 text-xs font-bold text-white transition hover:bg-white/[0.14] focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {isSaving ? 'Saving…' : 'Save Draft'}
                                </button>
                            </form>
                            <button
                                type="button"
                                onClick={handlePublish}
                                disabled={isSaving || isPublishing}
                                className="inline-flex h-9 items-center rounded-lg bg-emerald-500 px-3 text-xs font-bold text-white transition hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {isPublishing ? 'Publishing...' : 'Publish'}
                            </button>
                        </div>
                    </div>
                </header>

                <main className="px-4 py-5 sm:px-6 sm:py-8">
                    <div className="mx-auto w-full max-w-[1560px] overflow-visible rounded-xl border border-white/10 bg-white shadow-2xl shadow-black/30 lg:w-[min(86vw,1560px)]">
                        <div className="w-full overflow-hidden rounded-[11px] flex flex-col items-stretch">
                    
                    {/* GI-PASSED ANG UPDATED STATE UG FUNCTION SA HEADER */}
                    {data.global_header && (
                        <div className="w-full bg-white z-40">
                            {data.global_header.type === 'dark_cyan_header' && (
                                <DarkCyanHeader block={data.global_header} onUpdate={updateHeader} />
                            )}
                            {data.global_header.type === 'glassmorphism_header' && (
                                <GlassmorphismHeader
                                    block={data.global_header}
                                    onUpdate={updateHeader}
                                    globalTheme={globalSelections}
                                />
                            )}
                        </div>
                    )}

                    {data.blocks.map((block, index) => (

                        <div
                            key={block._renderKey || index}
                            className="relative group w-full transition-all duration-300 focus-within:z-20"
                        >

                            {/* Hover Toolbar */}

                            <div className="absolute top-5 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-all duration-300 z-50">

                                <div className="flex items-center gap-2 rounded-full bg-slate-900/90 backdrop-blur-xl border border-slate-700 shadow-2xl px-3 py-2">

                                    {/* Block Label */}

                                    <div className="flex flex-col leading-none px-2">

                                        <span className="text-[10px] uppercase tracking-[0.2em] text-slate-400 font-bold">

                                            {block.type.replaceAll("_"," ")}

                                        </span>

                                        <span className="mt-1 text-[9px] uppercase tracking-[0.2em] font-bold text-amber-300">

                                            {block.theme === "auto" && "✨ Auto"}
                                            {block.theme === "primary" && "🟦 Primary"}
                                            {block.theme === "white" && "⬜ White"}
                                            {block.theme === "surface" && "🩶 Surface"}
                                            {block.theme === "accent" && "🟪 Accent"}

                                        </span>

                                    </div>

                                    <div className="w-px h-5 bg-slate-700" />

                                    {/* Move Up */}

                                    <button
                                        type="button"
                                        aria-label="Move block up"
                                        onClick={() => moveBlock(index,"up")}
                                        disabled={index===0}
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 disabled:opacity-30 transition focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        ↑
                                    </button>

                                    {/* Move Down */}

                                    <button
                                        type="button"
                                        aria-label="Move block down"
                                        onClick={() => moveBlock(index,"down")}
                                        disabled={index===data.blocks.length-1}
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 disabled:opacity-30 transition focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        ↓
                                    </button>

                                    {/* Duplicate */}

                                    <button
                                        type="button"
                                        aria-label="Duplicate block"
                                        onClick={() => duplicateBlock(index)}
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 transition focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        ⧉
                                    </button>

                                    {/* Theme */}

                                    <button
                                        type="button"
                                        aria-label="Change block theme"
                                        onClick={() =>
                                            setThemeMenu(themeMenu === index ? null : index)
                                        }
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 transition focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        🎨
                                    </button>

                                    {
                                    themeMenu === index && (

                                        <div className="absolute top-12 right-0 w-48 rounded-xl bg-slate-900 border border-slate-700 shadow-2xl overflow-hidden">

                                            {[
                                                ["auto","✨ Auto"],
                                                ["primary","🟦 Primary"],
                                                ["white","⬜ White"],
                                                ["surface","🩶 Surface"],
                                                ["accent","🟪 Accent"]
                                            ].map(([value,label]) => (

                                                <button

                                                    key={value}
                                                    type="button"

                                                    onClick={() => {

                                                        const blocks=[...data.blocks];

                                                        blocks[index]={
                                                            ...blocks[index],
                                                            theme:value
                                                        };

                                                        setData("blocks",blocks);

                                                        setThemeMenu(null);

                                                    }}

                                                    className="w-full text-left px-4 py-3 hover:bg-slate-800 text-sm text-slate-200 transition focus:outline-none focus:bg-slate-800"

                                                >

                                                    {label}

                                                </button>

                                            ))}

                                        </div>

                                    )
                                }

                                    {/* Delete */}

                                    <button
                                        type="button"
                                        aria-label="Delete block"
                                        onClick={() => removeBlock(index)}
                                        className="w-8 h-8 rounded-lg hover:bg-red-500/20 text-red-400 transition focus:outline-none focus:ring-2 focus:ring-red-400"
                                    >
                                        🗑
                                    </button>

                                </div>

                            </div>

                            {/* Selected Outline */}

                            <div className="group-hover:ring-2 group-hover:ring-violet-500/40 transition-all">

                                {renderBlock(block,index)}

                            </div>

                        </div>

                    ))}

                    {data.blocks.length === 0 && (
                        <section className="flex min-h-[300px] items-center justify-center border-y border-slate-200 bg-slate-100 px-6 py-12 text-center">
                            <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white/75 px-6 py-7 shadow-sm">
                                <span className="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-lg text-white" aria-hidden="true">+</span>
                                <h2 className="mt-4 text-lg font-semibold text-slate-900">Start building this page</h2>
                                <p className="mt-2 text-sm leading-6 text-slate-500">Generate a complete layout with AI or add a section manually. Your global header and footer are already in place.</p>
                                <button type="button" onClick={() => setIsModalOpen(true)} className="mt-5 inline-flex h-10 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-violet-400">
                                    Generate or add a section
                                </button>
                            </div>
                        </section>
                    )}

                    {/* FOOTER RENDERER */}
                    {data.global_footer && (
                        <div className="relative group w-full mt-auto">
                            
                            {/* Footer Theme Dropdown (Upper Left) */}

                            {/* Footer Components */}
                            {data.global_footer.type === 'minimal_footer' && (
                                <MinimalFooter block={data.global_footer} onUpdate={updateFooter} />
                            )}
                            {data.global_footer.type === 'detailed_footer' && (
                                <DetailedFooter block={data.global_footer} onUpdate={updateFooter} />
                            )}
                        </div>
                    )}
                </div>


                    </div>
                </main>

                <div className="hidden max-w-7xl w-full mx-auto px-6 mt-10">

                    <div className="bg-white border border-slate-200 rounded-2xl shadow-lg px-6 py-5">

                        <div className="flex flex-wrap items-center justify-between gap-5">

                            {/* Theme */}
                            {/*<div className="flex items-center gap-3 min-w-[260px]">
                                <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
                                    🎨 Theme
                                </span>

                                <select
                                    className="flex-1 bg-slate-100 border border-slate-300 rounded-xl px-4 py-2.5 font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    value={globalSelections.primary}
                                    onChange={(e) => {
                                        setGlobalSelections(prev => ({
                                            ...prev,
                                            primary: e.target.value
                                        }));
                                    }}
                                >
                                    <option value="midnight">Midnight Blue</option>
                                    <option value="obsidian">Obsidian Black</option>
                                    <option value="terracotta">Terracotta</option>
                                    <option value="asphalt">Asphalt Grey</option>
                                    <option value="espresso">Espresso Brown</option>
                                    <option value="navy">Classic Navy</option>
                                    <option value="void">Void Deep Blue</option>
                                    <option value="emerald">Emerald Forest</option>
                                    <option value="coffee">Coffee Bean</option>
                                    <option value="rose">Rose Bloom</option>
                                    <option value="indigo">Royal Indigo</option>
                                    <option value="amber">Golden Amber</option>
                                    <option value="charcoal">Charcoal Grey</option>
                                    <option value="violet">Deep Violet</option>
                                    <option value="teal">Coastal Teal</option>
                                    <option value="ruby">Ruby Red</option>
                                    <option value="forest">Moss Forest</option>
                                    <option value="sapphire">Sapphire Blue</option>
                                    <option value="plum">Royal Plum</option>
                                    <option value="olive">Olive Grove</option>
                                </select>
                            </div>*/}

                            <ThemeSelector
                                value={globalSelections.primary}
                                onChange={(theme) => {

                                    setGlobalSelections(prev => ({
                                        ...prev,
                                        primary: theme
                                    }));

                                    setHasUnsavedTheme(true);

                                }}
                            />

                            {/* AI */}
                            <button
                                type="button"
                                onClick={() => setIsModalOpen(true)}
                                className="px-6 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:scale-[1.02] hover:shadow-xl transition text-white font-bold"
                            >
                                ✨ Generate
                            </button>

                            {/* Builder */}
                            <div className="text-sm">
                                <div className="text-slate-400 uppercase tracking-wider text-[10px]">
                                    Builder Node
                                </div>

                                <div className="font-bold text-indigo-600">
                                    /{page.slug}
                                </div>
                            </div>

                            {/* Save */}
                            <form onSubmit={handleSubmit}>
                                <button
                                    type="submit"
                                    disabled={isSaving || isPublishing}
                                    className="px-8 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-lg transition disabled:opacity-50"
                                >
                                    {isSaving
                                        ? "Saving..."
                                        : "Save Draft"}
                                </button>
                            </form>

                        </div>

                    </div>

                </div>


            </div>
            
            <AddSectionModal
                open={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                onAdd={addBlock}
                onReplace={replaceBlocks}
            />

            {/* AI MODAL INJECTOR CONFIG */}
            
        </>
    );
}
