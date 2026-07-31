import { useEffect, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { confirmCosmicAction, showCosmicNotification } from '../../Components/CosmicNotification';
import CreditBalanceBadge from '../../Components/CosmicCredits/CreditBalanceBadge';
import { useCreditBalance } from '../../Components/CosmicCredits/CreditBalanceContext';

import AddSectionModal from "./Components/AddSectionModal";

import ThemeSelector from "./Theme/ThemeSelector";

import { BlockRegistry } from "./BlockRegistry";
import { BLOG_SPARK_GROUPS, FREE_BLOG_SPARKS } from "./Sparks/Blog";
import { DarkCyanHeader, GlassmorphismHeader } from './GenerateHeader';

import { MinimalFooter, DetailedFooter } from './GenerateFooter';



export default function Builder({ page, website, blogPosts: initialBlogPosts = [], hasWebsiteContent = false, websiteContext = "", websitePages = [], trialMode = false, trialToken = null, trialCapabilities = {}, cosmicPricing = {} }) {
    const { props } = usePage();
    const { balance: creditBalance, setBalance: setCreditBalance } = useCreditBalance();

    const capabilities = {
        canNavigateAway: !trialMode,
        canChangeTheme: !trialMode,
        canGenerateAi: !trialMode,
        canPublish: !trialMode,
        canManageBlocks: !trialMode,
        canEditGlobalShell: !trialMode,
        canSave: true,
        canPurchase: trialMode,
        ...trialCapabilities,
    };
    const defaultHeader = {
        type: 'glassmorphism_header',
        logo_text: website?.name || 'Your Website',
        cta_label: 'Get Started',
        cta_url: '#',
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
    const [layoutMenu, setLayoutMenu] = useState(null);
    const [layoutApplying, setLayoutApplying] = useState(null);
    const [sparkCatalog, setSparkCatalog] = useState([]);
    const [sparkInsertTarget, setSparkInsertTarget] = useState(null);
    const [isSaving, setIsSaving] = useState(false);
    const [isPublishing, setIsPublishing] = useState(false);
    const [saveError, setSaveError] = useState('');
    const [hasUnsavedTheme, setHasUnsavedTheme] = useState(false);
    const [pageStatus, setPageStatus] = useState(page.status || 'draft');
    const [publishError, setPublishError] = useState(page.publish_error || '');
    const [blogPosts, setBlogPosts] = useState(initialBlogPosts);
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

    useEffect(() => {
        if (!capabilities.canManageBlocks) return;

        axios.get('/sparks/catalog')
            .then(({ data: responseData }) => setSparkCatalog(responseData.sparks || []))
            .catch(() => setSparkCatalog([]));
    }, [capabilities.canManageBlocks]);

    // Update logic para sa mga blocks
    const updateBlockContent = (index, updatedFields) => {
        const updatedBlocks = [...data.blocks];
        updatedBlocks[index] = { ...updatedBlocks[index], ...updatedFields };
        setData('blocks', updatedBlocks);
    };

    // Bag-ong logic para ma-update ang global header
    const updateHeader = (updatedFields) => {
        if (!capabilities.canEditGlobalShell) return;
        setData('global_header', { ...data.global_header, ...updatedFields });
    };

    const updateFooter = (updatedFields) => {
        if (!capabilities.canEditGlobalShell) return;
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
            theme: block.theme || "auto",
            _renderKey: crypto.randomUUID(),
        };

        const blocks = [...data.blocks];

        if (sparkInsertTarget) {
            const insertionIndex = sparkInsertTarget.position === 'above'
                ? sparkInsertTarget.index
                : sparkInsertTarget.index + 1;
            blocks.splice(insertionIndex, 0, newBlock);
        } else {
            blocks.push(newBlock);
        }

        setData("blocks", blocks);
        setSparkInsertTarget(null);
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
            showCosmicNotification({ title: 'Generation failed', message: 'Cosmic AI could not generate this block. Please try again.', tone: 'error' });
        } finally {
            setAiLoading(false);
        }
    };

    const saveDraft = async () => {
        setIsSaving(true);
        setSaveError('');

        try {
            const saveUrl = trialMode
                ? `${route('pages.builder.save', page.id)}?token=${encodeURIComponent(trialToken)}`
                : route('pages.builder.save', page.id);

            const response = await axios.post(saveUrl, {
                blocks: data.blocks,
                global_header: data.global_header,
                global_footer: data.global_footer,
                theme_settings: globalSelections,
            });

            setPageStatus(response.data.page_status || 'draft');
            setCreditBalance(response.data.credit_balance);
            setPublishError('');
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

        // Always persist the exact Builder state first. This keeps publishing
        // reliable after AI generation, adding/removing blocks, layout changes,
        // header/footer edits, and theme changes—even when React's dirty state
        // has not finished updating yet.
        const saved = await saveDraft();

        if (!saved) {
            showCosmicNotification({
                title: 'Save required before publishing',
                message: 'Your latest Builder changes could not be saved, so publishing was stopped.',
                tone: 'error',
            });
            return;
        }

        setIsPublishing(true);

        try {
            const response = await axios.post(route('pages.publish', page.id));
            setPageStatus(response.data.status || 'published');
            setCreditBalance(response.data.credit_balance);
            setPublishError('');
            showCosmicNotification({
                title: 'Page published',
                message: 'The latest Builder version is now live.',
                tone: 'success',
            });
        } catch (error) {
            const message = error.response?.data?.message ||
                'Publishing failed. Your previous live version is still available.';
            setPublishError(message);
            showCosmicNotification({
                title: 'Publishing failed',
                message,
                tone: 'error',
            });
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

    const getCompatibleLayouts = (blockType) => {
        const blogSparkGroup = BLOG_SPARK_GROUPS[blockType];

        // Blog sections use three free visual variants within the same block type.
        // They are always available and do not depend on Spark ownership.
        if (blogSparkGroup) {
            return FREE_BLOG_SPARKS
                .filter((spark) => spark.group === blogSparkGroup)
                .map((spark) => ({
                    id: spark.id,
                    type: blockType,
                    title: spark.title,
                    layoutVariant: spark.payload.layout_variant,
                    description: spark.description,
                    preview: spark.preview,
                    kind: 'blog-variant',
                }));
        }

        const currentBlock = BlockRegistry[blockType];
        const category = currentBlock?.schema?.category;
        const ownedKeys = new Set(
            sparkCatalog.filter((spark) => spark.owned).map((spark) => spark.key)
        );

        if (!category) {
            return [];
        }

        return Object.entries(BlockRegistry)
            .filter(([type, registryItem]) =>
                registryItem?.schema?.category === category &&
                (type === blockType || ownedKeys.has(type))
            )
            .map(([type, registryItem]) => ({
                id: type,
                type,
                title: registryItem.schema?.title || type.replaceAll('_', ' '),
                kind: 'block-type',
            }));
    };

    const isCurrentLayout = (block, layout) => layout.kind === 'blog-variant'
        ? (block.layout_variant || BlockRegistry[block.type]?.schema?.defaults?.layout_variant) === layout.layoutVariant
        : block.type === layout.type;

    const changeBlockLayout = (index, layout) => {
        const currentBlock = data.blocks[index];

        if (!currentBlock || isCurrentLayout(currentBlock, layout)) {
            setLayoutMenu(null);
            return;
        }

        if (layout.kind === 'blog-variant') {
            setLayoutApplying(index);
            const blocks = [...data.blocks];
            blocks[index] = {
                ...currentBlock,
                layout_variant: layout.layoutVariant,
                _renderKey: crypto.randomUUID(),
            };

            setData('blocks', blocks);
            setLayoutMenu(null);
            window.setTimeout(() => setLayoutApplying((activeIndex) => activeIndex === index ? null : activeIndex), 450);
            return;
        }

        const nextLayout = BlockRegistry[layout.type];
        const defaults = nextLayout?.schema?.defaults;

        if (!defaults) {
            setLayoutMenu(null);
            return;
        }

        // Keep only content keys that both layouts understand. This retains the
        // shared copy/CTA fields without carrying incompatible layout-specific data.
        const sharedContent = Object.keys(defaults).reduce((preserved, key) => {
            if (key !== 'type' && key !== 'theme' && Object.hasOwn(currentBlock, key)) {
                preserved[key] = currentBlock[key];
            }

            return preserved;
        }, {});

        const blocks = [...data.blocks];
        blocks[index] = {
            ...defaults,
            ...sharedContent,
            type: layout.type,
            theme: currentBlock.theme || 'auto',
            _renderKey: crypto.randomUUID(),
        };

        setData('blocks', blocks);
        setLayoutMenu(null);
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

            blockIndex: index,
            blogPosts,
            blogWebsiteId: website.id,
            blogPageId: page.id,
            onBlogPostCreated: (post) => setBlogPosts((currentPosts) => [post, ...currentPosts]),
            onBlogPostUpdated: (post) => setBlogPosts((currentPosts) => currentPosts.map((currentPost) => currentPost.id === post.id ? post : currentPost)),
            onBlogPostDeleted: (postId) => setBlogPosts((currentPosts) => currentPosts.filter((post) => post.id !== postId)),

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
                <header className="sticky top-0 z-[60] border-b border-white/10 bg-[#09090b]/95 backdrop-blur-xl">
                    <div className="mx-auto grid min-h-[64px] max-w-[1760px] grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-4 px-4 py-2.5 sm:px-6">
                        <div className="flex min-w-0 items-center gap-3">
                            {capabilities.canNavigateAway && (
                                <Link
                                    href={route('pages.index', website.id)}
                                    onClick={async (event) => {
                                        if (!hasUnsavedChanges) return;

                                        event.preventDefault();
                                        const shouldLeave = await confirmCosmicAction({
                                            title: 'Leave without saving?',
                                            message: 'You have unsaved Builder changes. They will be lost if you leave this page.',
                                            confirmLabel: 'Leave Builder',
                                            tone: 'error',
                                        });

                                        if (shouldLeave) {
                                            window.location.assign(route('pages.index', website.id));
                                        }
                                    }}
                                    className="inline-flex h-9 shrink-0 items-center gap-2 rounded-lg px-2.5 text-xs font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400"
                                >
                                    <span aria-hidden="true">←</span>
                                    <span className="hidden sm:inline">Back to Pages</span>
                                </Link>
                            )}

                            <div className="min-w-0 border-l border-white/10 pl-3 sm:pl-4">
                                <p className="truncate text-[11px] font-medium text-slate-500">{website.name || 'Cosmic CMS'}</p>
                                <div className="flex min-w-0 items-center gap-2 leading-tight">
                                    <span className="truncate text-sm font-semibold text-white">{page.title || 'Untitled page'}</span>
                                    <span className="hidden text-[11px] text-slate-600 sm:inline">/{page.slug}</span>
                                </div>
                            </div>
                        </div>

                        <div className="hidden items-center gap-1 rounded-xl border border-white/10 bg-white/[0.035] p-1 xl:flex">
                            <span title={publishError || saveError || undefined} className={`inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-[11px] font-medium ${isPublishing ? 'text-sky-200' : publishError ? 'text-red-200' : pageStatus === 'published' ? 'text-emerald-200' : 'text-amber-200'}`}>
                                <span className={`h-1.5 w-1.5 rounded-full ${isPublishing ? 'animate-pulse bg-sky-300' : publishError ? 'bg-red-300' : pageStatus === 'published' ? 'bg-emerald-300' : 'bg-amber-300'}`} />
                                {isPublishing ? 'Publishing…' : publishError ? 'Publish failed' : pageStatus === 'published' ? 'Published' : 'Draft'}
                            </span>
                            <span className="h-4 w-px bg-white/10" aria-hidden="true" />
                            <span className="inline-flex h-8 items-center rounded-lg px-2.5 text-[11px] font-medium text-slate-400">
                                {data.blocks.length} Sparks
                            </span>
                            {!trialMode && (
                                <>
                                    <span className="h-4 w-px bg-white/10" aria-hidden="true" />
                                    <CreditBalanceBadge
                                        balance={creditBalance}
                                        className="h-8 border-0 bg-transparent px-2.5 hover:bg-white/[0.06]"
                                    />
                                </>
                            )}
                        </div>

                        <div className="flex min-w-0 items-center justify-end gap-2">
                            {capabilities.canChangeTheme && (
                                <ThemeSelector
                                    compact
                                    value={globalSelections.primary}
                                    onChange={(theme) => {
                                        setGlobalSelections(prev => ({ ...prev, primary: theme }));
                                        setHasUnsavedTheme(true);
                                    }}
                                />
                            )}

                            {capabilities.canGenerateAi && (
                                <button
                                    type="button"
                                    onClick={() => setIsModalOpen(true)}
                                    className="inline-flex h-9 shrink-0 items-center rounded-lg border border-violet-400/30 bg-violet-500/20 px-3 text-xs font-semibold text-violet-100 transition hover:bg-violet-500/30 focus:outline-none focus:ring-2 focus:ring-violet-400"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" className="mr-1.5 h-3.5 w-3.5">
                                        <path d="M10 2.5c.28 3.92 1.68 5.32 5.6 5.6-3.92.28-5.32 1.68-5.6 5.6-.28-3.92-1.68-5.32-5.6-5.6 3.92-.28 5.32-1.68 5.6-5.6Zm5.25 9.75c.1 1.4.6 1.9 2 2-1.4.1-1.9.6-2 2-.1-1.4-.6-1.9-2-2 1.4-.1 1.9-.6 2-2Z" />
                                    </svg>
                                    <span className="hidden sm:inline">Add Spark</span>
                                </button>
                            )}

                            {capabilities.canSave && (
                                <form onSubmit={handleSubmit}>
                                    <button
                                        type="submit"
                                        disabled={isSaving || isPublishing}
                                        className="inline-flex h-9 shrink-0 items-center rounded-lg border border-white/15 bg-white/[0.055] px-3 text-xs font-semibold text-white transition hover:bg-white/[0.11] focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {isSaving ? 'Saving…' : trialMode ? 'Save changes' : 'Save'}
                                    </button>
                                </form>
                            )}

                            {capabilities.canPurchase && trialToken && (
                                <a
                                    href={`${route('start')}?trial=${encodeURIComponent(trialToken)}`}
                                    className="inline-flex h-9 shrink-0 items-center rounded-lg bg-emerald-500 px-4 text-xs font-bold text-white transition hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300"
                                >
                                    Buy website
                                </a>
                            )}

                            {capabilities.canPublish && (
                                <button
                                    type="button"
                                    onClick={handlePublish}
                                    disabled={isSaving || isPublishing}
                                    className="inline-flex h-9 shrink-0 items-center rounded-lg bg-emerald-500 px-3.5 text-xs font-bold text-white transition hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {isPublishing ? 'Publishing…' : 'Publish'}
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center justify-between gap-3 border-t border-white/[0.06] px-4 py-1.5 sm:px-6 xl:hidden">
                        <div className="flex min-w-0 items-center gap-2 text-[11px]">
                            <span className={publishError ? 'text-red-300' : pageStatus === 'published' ? 'text-emerald-300' : 'text-amber-200'}>
                                {publishError ? 'Publish failed' : pageStatus === 'published' ? 'Published' : 'Draft'}
                            </span>
                            <span className="text-slate-600">•</span>
                            <span className="text-slate-500">{data.blocks.length} Sparks</span>
                            {hasUnsavedChanges && <span className="hidden text-amber-200 sm:inline">• Unsaved changes</span>}
                        </div>
                        {!trialMode && (
                            <CreditBalanceBadge
                                balance={creditBalance}
                                className="h-7 shrink-0"
                            />
                        )}
                    </div>

                    {(saveError || publishError) && (
                        <div className="border-t border-red-400/10 bg-red-400/[0.04] px-4 py-1.5 sm:px-6">
                            <p className="text-[11px] text-red-300">{saveError || publishError}</p>
                        </div>
                    )}
                </header>

                <main className={page.page_type === 'blog' ? "py-5 sm:py-8" : "px-4 py-5 sm:px-6 sm:py-8"}>
                    <div className={page.page_type === 'blog'
                        ? "mx-auto w-full max-w-[1760px] overflow-visible bg-white lg:w-[min(96vw,1760px)]"
                        : "mx-auto w-full max-w-[1560px] overflow-visible rounded-xl border border-white/10 bg-white shadow-2xl shadow-black/30 lg:w-[min(86vw,1560px)]"}>
                        <div className={page.page_type === 'blog'
                            ? "flex w-full flex-col items-stretch overflow-hidden"
                            : "flex w-full flex-col items-stretch overflow-hidden rounded-[11px]"}>
                    
                    {/* GI-PASSED ANG UPDATED STATE UG FUNCTION SA HEADER */}
                    {data.global_header && (
                        <div className="w-full bg-white z-40">
                            {data.global_header.type === 'dark_cyan_header' && (
                                <DarkCyanHeader block={data.global_header} onUpdate={updateHeader} pageTargets={websitePages} />
                            )}
                            {data.global_header.type === 'glassmorphism_header' && (
                                <GlassmorphismHeader
                                    block={data.global_header}
                                    onUpdate={updateHeader}
                                    globalTheme={globalSelections}
                                    pageTargets={websitePages}
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

                            {capabilities.canManageBlocks && (
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

                                    {/* Change Spark */}

                                    <button
                                        type="button"
                                        aria-label={`Change ${BlockRegistry[block.type]?.schema?.category || 'section'} Spark`}
                                        title={getCompatibleLayouts(block.type).length > 1
                                            ? `Change ${BlockRegistry[block.type]?.schema?.category || 'section'} Spark`
                                            : 'Add another owned Spark in this category to enable changing'}
                                        disabled={getCompatibleLayouts(block.type).length < 2}
                                        onClick={() => {
                                            setThemeMenu(null);
                                            setLayoutMenu(layoutMenu === index ? null : index);
                                        }}
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 transition focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-30"
                                    >
                                        <span aria-hidden="true">&#8644;</span>
                                    </button>

                                    {layoutMenu === index && (
                                        <div className="absolute top-12 right-10 z-50 w-64 overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
                                            <div className="border-b border-slate-700 px-4 py-3">
                                                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Change Spark</p>
                                                <p className="mt-1 text-xs font-semibold text-white">{BLOG_SPARK_GROUPS[block.type] ? 'Choose from 3 free Blog Sparks' : `Owned ${BlockRegistry[block.type]?.schema?.category || 'Section'} Sparks`}</p>
                                            </div>

                                            <div className="max-h-64 overflow-y-auto py-1 [scrollbar-color:rgb(100_116_139)_transparent] [scrollbar-width:thin] [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-700 hover:[&::-webkit-scrollbar-thumb]:bg-violet-500/70">
                                                {getCompatibleLayouts(block.type).map((layout) => {
                                                    const current = isCurrentLayout(block, layout);

                                                    return (
                                                        <button
                                                            key={layout.id}
                                                            type="button"
                                                            disabled={current}
                                                            onClick={() => changeBlockLayout(index, layout)}
                                                            aria-current={current ? "true" : undefined}
                                                            className={`group/layout flex w-full items-center gap-3 border-l-2 px-4 py-3 text-left text-sm transition-all duration-200 focus:outline-none ${current
                                                                ? 'cursor-default border-violet-400 bg-violet-500/10 text-white'
                                                                : 'border-transparent text-slate-200 hover:border-violet-400/60 hover:bg-slate-800 focus:bg-slate-800'}`}
                                                        >
                                                            {layout.kind === 'blog-variant' && (
                                                                <span className={`grid h-10 w-12 shrink-0 gap-1 rounded-lg border p-1.5 transition ${current ? 'border-violet-400/40 bg-violet-400/10' : 'border-slate-700 bg-slate-950 group-hover/layout:border-slate-500'}`} aria-hidden="true">
                                                                    <span className={`rounded-sm ${layout.preview === 'center' || layout.preview === 'compact' ? 'mx-auto w-7' : 'w-full'} bg-slate-500/70`} />
                                                                    <span className={`rounded-sm bg-slate-700 ${layout.preview === 'split' || layout.preview === 'stacked' ? 'w-2/3' : 'w-full'}`} />
                                                                    <span className={`rounded-sm bg-slate-700 ${layout.preview === 'magazine' || layout.preview === 'cards' ? 'grid grid-cols-2 gap-0.5' : ''}`} />
                                                                </span>
                                                            )}
                                                            <span className="min-w-0 flex-1">
                                                                <span className="block truncate font-semibold">{layout.title}</span>
                                                                {layout.kind === 'blog-variant' && (
                                                                    <span className="mt-0.5 block text-[10px] leading-4 text-slate-500">{layout.description || 'Free Blog Spark'}</span>
                                                                )}
                                                            </span>
                                                            {current && (
                                                                <span className="rounded-full bg-violet-400/15 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-violet-300">Current</span>
                                                            )}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}

                                    {/* Insert owned Spark above */}
                                    <button
                                        type="button"
                                        aria-label="Insert Spark above"
                                        title="Insert owned Spark above"
                                        onClick={() => {
                                            setThemeMenu(null);
                                            setLayoutMenu(null);
                                            setSparkInsertTarget({ index, position: 'above' });
                                            setIsModalOpen(true);
                                        }}
                                        className="w-8 h-8 rounded-lg hover:bg-emerald-500/15 text-emerald-300 transition focus:outline-none focus:ring-2 focus:ring-emerald-400"
                                    >
                                        <span aria-hidden="true">↥</span>
                                    </button>

                                    {/* Insert owned Spark below */}
                                    <button
                                        type="button"
                                        aria-label="Insert Spark below"
                                        title="Insert owned Spark below"
                                        onClick={() => {
                                            setThemeMenu(null);
                                            setLayoutMenu(null);
                                            setSparkInsertTarget({ index, position: 'below' });
                                            setIsModalOpen(true);
                                        }}
                                        className="w-8 h-8 rounded-lg hover:bg-violet-500/15 text-violet-300 transition focus:outline-none focus:ring-2 focus:ring-violet-400"
                                    >
                                        <span aria-hidden="true">↧</span>
                                    </button>

                                    {/* Theme */}

                                    <button
                                        type="button"
                                        aria-label="Change block theme"
                                        onClick={() => {
                                            setLayoutMenu(null);
                                            setThemeMenu(themeMenu === index ? null : index);
                                        }}
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
                            )}

                            {/* Selected Outline */}

                            <div className={`group-hover:ring-2 group-hover:ring-violet-500/40 transition-all duration-500 ${layoutApplying === index ? "scale-[0.997] opacity-80 ring-2 ring-violet-400/40" : "opacity-100"}`}>

                                {renderBlock(block,index)}

                            </div>

                        </div>

                    ))}

                    {data.blocks.length === 0 && (
                        <section className="flex min-h-[300px] items-center justify-center border-y border-slate-200 bg-slate-100 px-6 py-12 text-center">
                            <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white/75 px-6 py-7 shadow-sm">
                                <span className="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-lg text-white" aria-hidden="true">+</span>
                                <h2 className="mt-4 text-lg font-semibold text-slate-900">Start building this page</h2>
                                <p className="mt-2 text-sm leading-6 text-slate-500">Add an owned Spark with quick content, or personalize it with Cosmic AI. Your global header and footer are already in place.</p>
                                {capabilities.canGenerateAi && (
                                    <button type="button" onClick={() => setIsModalOpen(true)} className="mt-5 inline-flex h-10 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-violet-400">
                                        Add your first Spark
                                    </button>
                                )}
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
                                ✨ Add Spark
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
            
            {capabilities.canGenerateAi && (
                <AddSectionModal
                    open={isModalOpen}
                    onClose={() => { setIsModalOpen(false); setSparkInsertTarget(null); }}
                    onAdd={addBlock}
                    onReplace={replaceBlocks}
                    hasBlocks={(data.blocks?.length ?? 0) > 0}
                    hasWebsiteContent={hasWebsiteContent}
                    websiteContext={websiteContext}
                    websiteId={website?.id}
                    cosmicPricing={cosmicPricing}
                    websiteTheme={globalSelections}
                    ownedOnly={Boolean(sparkInsertTarget)}
                    contextLabel={sparkInsertTarget ? `Insert Spark ${sparkInsertTarget.position}` : null}
                    onOwnershipChanged={(sparkKey) => setSparkCatalog((current) => current.map((spark) => spark.key === sparkKey ? { ...spark, owned: true } : spark))}
                />
            )}

            {/* AI MODAL INJECTOR CONFIG */}
            
        </>
    );
}
