import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';

import AddSectionModal from "./Components/AddSectionModal";

import { BlockRegistry } from "./BlockRegistry";
import { DarkCyanHeader, GlassmorphismHeader } from './GenerateHeader';

import { MinimalFooter, DetailedFooter } from './GenerateFooter';



export default function Builder({ page, website }) {
    const { props } = usePage();

    // Gi-apil na ang global_header sa form state
    const { data, setData, post, processing } = useForm({
        blocks: page.blocks || [],
        global_header: props.globalHeaderBlock || page.website?.global_header || null,
        global_footer: props.globalFooterBlock || page.website?.global_footer || { 
            type: 'minimal_footer', 
            logo_text: 'CosmicCMS', 
            copyright: '© 2026. All rights reserved.' 
        }
    });

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [aiResult, setAiResult] = useState(null);
    const [aiLoading, setAiLoading] = useState(false);

    const [themeMenu, setThemeMenu] = useState(null);

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
        console.log("newBlocks:", newBlocks);
        console.log("isArray:", Array.isArray(newBlocks));

        setData("blocks", newBlocks);

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

    const handleSubmit = async (e) => {
        e.preventDefault();

        try {
            await axios.post(route('websites.global-footer.save', page.website_id), {
                footer_block: data.global_footer
            });

            await axios.post(route('websites.update-theme', page.website_id), {
                theme_settings: globalSelections
            });

            post(route('pages.builder.update', page.id), {
                preserveScroll: true,
            });

        } catch (error) {
            console.error(error);
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
                    key={index}
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
        <AuthenticatedLayout>
            <Head title="Page Builder" />
            <div className="w-full min-h-screen bg-slate-50 flex flex-col justify-between pt-0 pb-12">
                <div className="w-full flex flex-col items-stretch">
                    
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
                            key={index}
                            className="relative group w-full transition-all duration-300"
                        >

                            {/* Hover Toolbar */}

                            <div className="absolute top-5 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-all duration-300 z-50">

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
                                        onClick={() => moveBlock(index,"up")}
                                        disabled={index===0}
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 disabled:opacity-30 transition"
                                    >
                                        ↑
                                    </button>

                                    {/* Move Down */}

                                    <button
                                        onClick={() => moveBlock(index,"down")}
                                        disabled={index===data.blocks.length-1}
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 disabled:opacity-30 transition"
                                    >
                                        ↓
                                    </button>

                                    {/* Duplicate */}

                                    <button
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 transition"
                                    >
                                        ⧉
                                    </button>

                                    {/* Theme */}

                                    <button
                                        onClick={() =>
                                            setThemeMenu(themeMenu === index ? null : index)
                                        }
                                        className="w-8 h-8 rounded-lg hover:bg-slate-800 text-slate-300 transition"
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

                                                    onClick={() => {

                                                        const blocks=[...data.blocks];

                                                        blocks[index]={
                                                            ...blocks[index],
                                                            theme:value
                                                        };

                                                        setData("blocks",blocks);

                                                        setThemeMenu(null);

                                                    }}

                                                    className="w-full text-left px-4 py-3 hover:bg-slate-800 text-sm text-slate-200 transition"

                                                >

                                                    {label}

                                                </button>

                                            ))}

                                        </div>

                                    )
                                }

                                    {/* Delete */}

                                    <button
                                        onClick={() => removeBlock(index)}
                                        className="w-8 h-8 rounded-lg hover:bg-red-500/20 text-red-400 transition"
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


                <div className="max-w-7xl w-full mx-auto px-6 mt-10">

                    <div className="bg-white border border-slate-200 rounded-2xl shadow-lg px-6 py-5">

                        <div className="flex flex-wrap items-center justify-between gap-5">

                            {/* Theme */}
                            <div className="flex items-center gap-3 min-w-[260px]">
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
                            </div>

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
                                    disabled={processing}
                                    className="px-8 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-lg transition disabled:opacity-50"
                                >
                                    {processing
                                        ? "Saving..."
                                        : "💾 Publish"}
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
            
        </AuthenticatedLayout>
    );
}