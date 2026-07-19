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

    const addBlock = (newBlock) => {
        setData('blocks', [...data.blocks, newBlock]);
        setIsModalOpen(false);
        setAiResult(null);
    };

    const removeBlock = (index) => {
        setData('blocks', data.blocks.filter((_, i) => i !== index));
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

        // 1. I-save una ang global_footer via axios
        try {
            await axios.post(route('websites.global-footer.save', page.website_id), {
                footer_block: data.global_footer
            });
        } catch (error) {
            console.error("Failed to save footer:", error);
            alert("Error sa pag-save sa footer!");
            return; 
        }

        // 2. I-save ang global_theme settings sa websites table (Pinaagi sa bag-ong API route)
        try {
            await axios.post(route('websites.update-theme', page.website_id), {
                theme_settings: globalSelections // Kini dapat { primary: 'emerald', ... }
            });
        } catch (error) {
            console.error("Failed to save theme:", error);
        }

        // 3. Unya i-submit ang page builder data via Inertia
        post(route('pages.builder.update', page.id), {
            data: {
                ...data,
                global_header: data.global_header,
                global_footer: data.global_footer 
            }
        });
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
        const defaults = { primary: 'emerald', secondary: 'white', tertiary: 'stone' };

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


    const renderBlock = (block, index) => {

        const blockProps = {
            block,
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
    
    // const renderBlock = (block, index) => {
    // // Siguroha nga ang globalSelections kay accessible dinhi

    //     const themeKey = JSON.stringify(globalSelections);

    //     const blockProps = {
    //         block: block,
    //         globalTheme: globalSelections, 
    //         onUpdate: (f) => updateBlockContent(index, f),
    //         blockIndex: index
    //     };
        
    //     switch (block.type) {
    //         case 'modern_hero':
    //             return <HeroCenteredCTA key={index} {...blockProps} />;
    //         case 'services_grid':
    //             return <ServicesCardsBlock key={index} {...blockProps} />;
    //         case 'feature_image_left':
    //             return <FeatureImageLeftBlock key={index} {...blockProps} />;
    //         case 'feature_image_right':
    //             return <FeatureImageRightBlock key={index} {...blockProps} />;
    //         case 'hero_headline':
    //             return <HeroHeadlineBlock key={index} {...blockProps} />;
    //         case 'hero':
    //             // Gigamit ang globalTheme para sa theme consistency
    //             const heroClass = globalSelections?.primary === 'emerald' ? 'bg-emerald-900' : 'bg-slate-900';
    //             return (
    //                 <section key={index} className={`py-20 px-8 text-center text-white shadow-xl w-full ${heroClass}`}>
    //                     <h1 className="text-5xl font-extrabold mb-4">{block.heading}</h1>
    //                     <p className="text-xl text-slate-200">{block.subheading}</p>
    //                 </section>
    //             );
    //         case 'content':
    //             return (
    //                 <section key={index} className="py-10 px-8 bg-white border-b border-slate-200 w-full">
    //                     <p className="text-lg text-slate-700 leading-relaxed">{block.text}</p>
    //                 </section>
    //             );
    //         default:
    //             return <div key={index} className="p-4 text-center text-xs text-red-400">Unknown Block: {block.type}</div>;
    //     }
    // };

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
                        <div key={index} className="relative group w-full p-0 m-0">
                            
                            {/* Container para sa dropdown ug move buttons sa upper left */}
                            <div className="absolute top-4 left-4 z-40 flex items-center gap-2">
                                
                                {/* Move Buttons */}
                                <div className="flex flex-col gap-0.5">
                                    <button 
                                        type="button"
                                        onClick={() => moveBlock(index, 'up')}
                                        disabled={index === 0}
                                        className="bg-white/90 border border-gray-300 rounded-sm px-1 text-[8px] hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed"
                                    >▲</button>
                                    <button 
                                        type="button"
                                        onClick={() => moveBlock(index, 'down')}
                                        disabled={index === data.blocks.length - 1}
                                        className="bg-white/90 border border-gray-300 rounded-sm px-1 text-[8px] hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed"
                                    >▼</button>
                                </div>

                                {/* Theme Dropdown */}
                                {/*<select 
                                    className="bg-white/90 backdrop-blur border border-gray-300 text-xs font-bold px-2 py-1.5 rounded shadow-lg focus:outline-none cursor-pointer"
                                    value={block.theme || 'dark'}
                                    onChange={(e) => updateBlockContent(index, { theme: e.target.value })}
                                >
                                    <option value="dark">Deep Slate (Default)</option>
                                    <option value="slate-950">Ultra Slate</option>
                                    <option value="midnight">Midnight Blue</option>
                                    <option value="charcoal">Soft Charcoal</option>
                                    <option value="slate-light">Cool Slate</option>
                                    <option value="white">Pure Clean</option>
                                    <option value="stone">Stone Gray</option>
                                    <option value="sky">Cloud Sky</option>
                                    <option value="emerald">Emerald Forest</option>
                                    <option value="rose">Rose Bloom</option>
                                    <option value="violet">Deep Violet</option>
                                    <option value="coffee">Coffee Bean</option>
                                </select>*/}
                                {/*<select 
                                    className="px-2 py-1 pr-7 text-sm border rounded" // I-adjust ni nga mga classes
                                    value={block.theme || 'primary'}
                                    onChange={(e) => updateBlockContent(index, { theme: e.target.value })}
                                >
                                    <option value="primary">Primary</option>
                                    <option value="secondary">Secondary</option>
                                    <option value="tertiary">Tertiary</option>
                                </select>*/}
                            </div>

                            {renderBlock(block, index)}

                            <button 
                                type="button"
                                onClick={() => removeBlock(index)}
                                className="absolute top-4 right-4 bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded shadow-xl hidden group-hover:block z-30 transition font-semibold text-xs"
                            >
                                🗑️ Delete Block
                            </button>
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
                <div className="max-w-4xl w-full mx-auto px-4 mt-12">
                    <div className="mt-8 mb-5 p-6 bg-white border border-slate-200 rounded-xl shadow-sm text-center">
                        <label className="block text-xs font-bold text-gray-500 uppercase mb-4">Select Global Theme</label>
                        <select 
                            className="w-full bg-slate-100 border border-slate-300 px-4 py-3 rounded-lg font-bold"
                            value={globalSelections.primary} 
                            onChange={(e) => {
                                const newPrimary = e.target.value;
                                // Gamita ang functional update aron masiguro nga update ang state
                                setGlobalSelections(prev => ({ 
                                    ...prev, 
                                    primary: newPrimary 
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
                    <div className="flex flex-col">
                        <div className="mb-6">
                            <button 
                                type="button" 
                                onClick={() => setIsModalOpen(true)}
                                className="w-full py-4 border-2 border-dashed border-gray-300 rounded-xl bg-white text-gray-500 font-bold hover:border-indigo-500 hover:text-indigo-500 transition shadow-sm"
                            >
                                + Generate AI Block
                            </button>
                        </div>

                        <div className="text-center border-t border-gray-200 pt-6 mb-6">
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Current Page Configuration</p>
                            <h2 className="text-lg font-bold text-gray-700">Builder Node: <span className="text-indigo-600">/{page.slug}</span></h2>
                        </div>

                        <div>
                            <form onSubmit={handleSubmit}>
                                <button 
                                    type="submit" 
                                    disabled={processing}
                                    className="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold shadow-lg transition disabled:opacity-50"
                                >
                                    {processing ? 'Saving Configurations...' : '💾 Save & Publish Content'}
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
            />

            {/* AI MODAL INJECTOR CONFIG */}
            
        </AuthenticatedLayout>
    );
}