import React, { useState , useEffect } from 'react';
import { usePage } from '@inertiajs/react';

import axios from 'axios';
import { showCosmicNotification } from '../../Components/CosmicNotification';
import { colorFamilies } from "../../theme/colorFamilies";

export const getEffectiveTheme = (blockTheme, globalSelections) => {
    if (!blockTheme) return colorFamilies.emerald;

    if (["primary", "secondary", "tertiary"].includes(blockTheme)) {
        return colorFamilies[globalSelections[blockTheme]] || colorFamilies.emerald;
    }

    return colorFamilies[blockTheme] || colorFamilies.emerald;
};

// GLOBAL POPUP OVERLAY MODAL PARA SA MGA TEXT/HEADINGS
function EditableText({ value, onSave, className, isTextArea = false }) {
    const [isEditing, setIsEditing] = useState(false);
    const [currentValue, setCurrentValue] = useState(value || '');

    return (
        <>
            {/* STATIC PREVIEW WITH HOVER EFFECT */}
            <div className="relative group/text cursor-pointer max-w-full block w-full" onClick={() => setIsEditing(true)}>
                <span className={className}>{value || 'Click to add text'}</span>
                <span className="absolute -top-2 right-2 hidden group-hover/text:inline-block bg-indigo-600 text-white text-[10px] px-1.5 py-0.5 rounded shadow-md font-sans z-30">
                    ✏️ Edit
                </span>
            </div>

            {/* OVERLAY MODAL: Fixed portal para dili ma-distort ang layout */}
            {isEditing && (
                <div className="cosmic-inline-edit-overlay fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-[9999] p-4">
                    <div className="cosmic-inline-edit-modal bg-slate-900 border border-slate-800 p-6 rounded-2xl w-full max-w-lg shadow-2xl text-slate-100 font-sans space-y-4">
                        <div className="flex justify-between items-center border-b border-slate-800 pb-2">
                            <h3 className="text-sm font-bold text-slate-400 tracking-wider uppercase">✨ Update Text Content</h3>
                            <button type="button" onClick={() => setIsEditing(false)} className="text-lg text-slate-500 hover:text-white">✕</button>
                        </div>

                        <div>
                            {isTextArea ? (
                                <textarea 
                                    className="w-full bg-slate-950 text-white p-3 text-sm rounded-xl border border-slate-700 focus:outline-none focus:border-emerald-500 font-sans"
                                    rows={5}
                                    value={currentValue}
                                    onChange={(e) => setCurrentValue(e.target.value)}
                                    autoFocus
                                />
                            ) : (
                                <input 
                                    type="text"
                                    className="w-full bg-slate-950 text-white p-3 text-sm rounded-xl border border-slate-700 focus:outline-none focus:border-emerald-500 font-sans"
                                    value={currentValue}
                                    onChange={(e) => setCurrentValue(e.target.value)}
                                    autoFocus
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            onSave(currentValue);
                                            setIsEditing(false);
                                        }
                                    }}
                                />
                            )}
                        </div>

                        <div className="flex justify-end gap-3 text-xs pt-2">
                            <button 
                                type="button"
                                onClick={() => setIsEditing(false)} 
                                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-300 font-medium transition"
                            >
                                Cancel
                            </button>
                            <button 
                                type="button"
                                onClick={() => { onSave(currentValue); setIsEditing(false); }} 
                                className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-white font-bold transition shadow-lg shadow-emerald-900/20"
                            >
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

// MODAL OVERLAY PARA SA MGA BUTTONS (LABEL & URL)
function EditableButton({ label, url, onSave, className }) {
    const [isEditing, setIsEditing] = useState(false);
    const [currentLabel, setCurrentLabel] = useState(label || 'Get Started');
    const [currentUrl, setCurrentUrl] = useState(url || '#');

    return (
        <>
            <div className="relative group/btn inline-block">
                <button 
                    type="button"
                    onClick={() => setIsEditing(true)} 
                    className={className}
                >
                    {label || 'Get Started'}
                </button>
                <span className="absolute -top-3 -right-3 hidden group-hover/btn:inline-block bg-indigo-600 text-white text-[9px] px-1 rounded-full p-0.5 shadow-md z-30">
                    ✏️
                </span>
            </div>

            {/* MODAL CONFIG OVERLAY */}
            {isEditing && (
                <div className="cosmic-inline-edit-overlay fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-[9999] p-4">
                    <div className="cosmic-inline-edit-modal bg-slate-900 border border-slate-700 p-6 rounded-2xl shadow-2xl text-left w-full max-w-sm space-y-4 font-sans text-slate-100">
                        <div className="flex justify-between items-center border-b border-slate-800 pb-2">
                            <h3 className="text-xs font-bold text-slate-400 tracking-wider uppercase">🔗 Button Configuration</h3>
                            <button type="button" onClick={() => setIsEditing(false)} className="text-slate-500 hover:text-white">✕</button>
                        </div>
                        
                        <div>
                            <label className="text-[10px] font-bold text-gray-400 block mb-1 tracking-wider">BUTTON LABEL</label>
                            <input 
                                type="text" 
                                className="w-full bg-slate-950 text-sm text-white p-2.5 rounded-xl border border-slate-800 focus:outline-none focus:border-indigo-500"
                                value={currentLabel}
                                onChange={(e) => setCurrentLabel(e.target.value)}
                            />
                        </div>
                        <div>
                            <label className="text-[10px] font-bold text-gray-400 block mb-1 tracking-wider">REDIRECT URL</label>
                            <input 
                                type="text" 
                                className="w-full bg-slate-950 text-sm text-white p-2.5 rounded-xl border border-slate-800 focus:outline-none focus:border-indigo-500"
                                value={currentUrl}
                                onChange={(e) => setCurrentUrl(e.target.value)}
                            />
                        </div>

                        <div className="flex justify-end gap-2 text-xs pt-2">
                            <button 
                                type="button"
                                onClick={() => setIsEditing(false)} 
                                className="px-3 py-2 bg-slate-800 text-slate-300 rounded-lg"
                            >
                                Cancel
                            </button>
                            <button 
                                type="button"
                                onClick={() => { onSave(currentLabel, currentUrl); setIsEditing(false); }} 
                                className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-lg transition"
                            >
                                Apply Updates
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

/// 1. MODERN HERO BLOCK
export function HeroCenteredCTA({ block, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.theme, globalTheme);

    return (
        <section className={`w-full py-24 px-7 md:px-8 text-center ${theme.bg} relative overflow-hidden border-b ${theme.border} transition-colors duration-500`}>
            <div className="max-w-4xl mx-auto space-y-6 relative z-10 flex flex-col items-center">
                <EditableText 
                    value={block.tagline || 'LOREM IPSUM DOLOR'} 
                    className={`text-xs font-bold ${theme.text} tracking-widest uppercase block opacity-80`}
                    onSave={(val) => onUpdate({ tagline: val })}
                />
                <EditableText 
                    value={block.heading} 
                    className={`text-4xl md:text-5xl font-extrabold ${theme.text} leading-tight block`}
                    onSave={(val) => onUpdate({ heading: val })}
                />
                <EditableText 
                    value={block.subheading || block.text} 
                    isTextArea={true}
                    className={`text-base md:text-lg ${theme.sub} max-w-2xl mx-auto leading-relaxed block`}
                    onSave={(val) => onUpdate({ subheading: val, text: val })}
                />
                <EditableButton 
                    label={block.button_label || 'Get Started'} 
                    url={block.button_url || '#'} 
                    // Gi-update ang button classes para gamiton ang theme.text ug theme.bg (or invert)
                    className={`inline-block ${theme.text} ${theme.bg} border ${theme.border} px-8 py-3 rounded-full font-bold shadow-lg hover:opacity-90 transition`}
                    onSave={(label, url) => onUpdate({ button_label: label, button_url: url })}
                />
            </div>
        </section>
    );
}

// 2. CORE SERVICES GRID BLOCK
export function ServicesCardsBlock({ block, onUpdate, globalTheme }) {
    const cardData = block.cards || [
        {
            title: 'Website Development',
            desc: 'Modern, fast, and scalable websites tailored for your business.'
        },
        {
            title: 'UI / UX Design',
            desc: 'Beautiful user experiences focused on clarity and conversion.'
        },
        {
            title: 'Digital Strategy',
            desc: 'Helping businesses grow through thoughtful digital solutions.'
        }
    ];

    const icons = ['⚡', '💻', '🚀', '📈', '🛡️', '💡', '🎯', '✨'];

    const theme = getEffectiveTheme(block.theme, globalTheme);

    const updateCard = (cardIndex, field, newValue) => {
        const updatedCards = [...cardData];
        updatedCards[cardIndex] = {
            ...updatedCards[cardIndex],
            [field]: newValue
        };

        onUpdate({
            cards: updatedCards
        });
    };

    return (
        <section
            className={`w-full py-32 px-7 md:px-8 transition-colors duration-500 ${theme.bg}`}
        >
            <div className="max-w-7xl mx-auto">

                {/* Header */}

                <div className="max-w-3xl mx-auto text-center mb-20">

                    <EditableText
                        value={block.tagline || 'WHAT WE OFFER'}
                        className={`text-xs font-semibold tracking-[0.35em] uppercase ${theme.text} opacity-70 block`}
                        onSave={(val) => onUpdate({ tagline: val })}
                    />

                    <EditableText
                        value={block.heading || 'Solutions Designed To Help Your Business Grow'}
                        className={`mt-5 text-5xl md:text-6xl font-bold tracking-tight leading-tight ${theme.text} block`}
                        onSave={(val) => onUpdate({ heading: val })}
                    />

                    <EditableText
                        value={
                            block.description ||
                            'We combine strategy, design, and technology to create digital experiences that help businesses grow with confidence.'
                        }
                        isTextArea={true}
                        className={`mt-6 text-lg leading-8 ${theme.sub} block`}
                        onSave={(val) =>
                            onUpdate({
                                description: val
                            })
                        }
                    />

                </div>

                {/* Cards */}

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

                    {cardData.map((card, i) => (

                        <div key={i} className={`${theme.card} border ${theme.border} rounded-3xl p-8 h-full flex flex-col transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl`}>

                            {/* Icon */}

                            <div
                                className={`
                                    w-16
                                    h-16
                                    rounded-2xl
                                    border
                                    ${theme.border}
                                    bg-white/5
                                    flex
                                    items-center
                                    justify-center
                                    text-2xl
                                    mb-6
                                `}
                            >
                                {icons[i % icons.length]}
                            </div>

                            {/* Title */}

                            <EditableText
                                value={card.title}
                                className={`text-2xl font-bold tracking-tight ${theme.text} block`}
                                onSave={(val) =>
                                    updateCard(i, 'title', val)
                                }
                            />

                            {/* Divider */}

                            <div
                                className={`w-14 h-px mt-5 mb-5 ${theme.border} border-t`}
                            />

                            {/* Description */}

                            <EditableText
                                value={card.desc}
                                isTextArea={true}
                                className={`text-base leading-8 ${theme.sub} block flex-grow`}
                                onSave={(val) =>
                                    updateCard(i, 'desc', val)
                                }
                            />

                            {/* Footer */}

                            <div className="mt-8">

                                <span
                                    className={`inline-flex items-center gap-2 text-sm font-semibold ${theme.text} opacity-80`}
                                >
                                    Learn More

                                    <span className="transition-transform duration-300 group-hover:translate-x-1">
                                        →
                                    </span>

                                </span>

                            </div>

                        </div>

                    ))}

                </div>

            </div>
        </section>
    );
}

// EDITABLE IMAGE COMPONENT
export function EditableImage({ websiteId, blockIndex, onSave, className, src }) {
    const [isEditing, setIsEditing] = useState(false);
    const [selectedFile, setSelectedFile] = useState(null);
    const [preview, setPreview] = useState(src);
    const [uploading, setUploading] = useState(false);

    useEffect(() => {
        return () => {
            if (preview && preview.startsWith('blob:')) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setSelectedFile(file);
            setPreview(URL.createObjectURL(file));
        }
    };

    const handleSave = async () => {
        if (!selectedFile) return;
        setUploading(true);

        const formData = new FormData();
        formData.append('website_id', websiteId);
        formData.append('block_index', blockIndex);
        formData.append('image', selectedFile);

        try {
            const response = await axios.post('/api/update-block-data', formData);
            onSave(response.data.url); 
            setIsEditing(false);
            setSelectedFile(null);
        } catch (error) {
            console.error("Error saving:", error);
            showCosmicNotification({ title: 'Unable to save image', message: 'The image could not be saved. Please try again.', tone: 'error' });
        } finally {
            setUploading(false);
        }
    };

    return (
        <div className="relative group cursor-pointer" onClick={() => setIsEditing(true)}>
            <img src={preview} className={className} alt="Editable" />
            
            {isEditing && (
                <div className="fixed inset-0 bg-black/80 flex items-center justify-center z-[9999] p-4">
                    <div className="bg-slate-900 p-6 rounded-2xl w-full max-w-sm pointer-events-auto z-[10000] relative" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-white font-bold mb-4">Media Manager</h3>
                        <img src={preview} className="w-full h-32 object-cover rounded-lg mb-4" />
                        
                        <div className="flex gap-2">
                            <label className="flex-1 bg-blue-600 py-2 rounded-lg text-white text-center cursor-pointer hover:bg-blue-700">
                                Select Image
                                <input type="file" className="hidden" onChange={handleFileChange} />
                            </label>
                            
                            {selectedFile && (
                                <button 
                                    type="button"
                                    onClick={handleSave} 
                                    className="flex-1 bg-emerald-600 py-2 rounded-lg text-white font-bold hover:bg-emerald-700"
                                >
                                    {uploading ? 'Saving...' : 'Save Changes'}
                                </button>
                            )}
                            
                            <button 
                                type="button"
                                onClick={(e) => { 
                                    e.stopPropagation();
                                    setPreview(src);
                                    setSelectedFile(null);
                                    setIsEditing(false);
                                }} 
                                className="px-4 bg-slate-800 rounded-lg text-white hover:bg-slate-700"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

// 3. FEATURE BLOCK
export function FeatureImageLeftBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.theme, globalTheme);

    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;

    return (
        <section className={`relative py-32 px-7 overflow-hidden ${theme.bg} transition-colors duration-500`}>

            <div className="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-20">

                {/* Image */}
                <div className="w-full md:w-1/2">
                    <div className="rounded-3xl overflow-hidden shadow-2xl ring-1 ring-white/10 transition-transform duration-500 hover:scale-[1.02]">
                        <EditableImage
                            websiteId={websiteId}
                            blockIndex={blockIndex}
                            src={block.image_url || "https://picsum.photos/800/600"}
                            className="w-full h-auto object-cover"
                            onSave={(url) => onUpdate({ image_url: url })}
                        />
                    </div>
                </div>

                {/* Content */}
                <div className="w-full md:w-1/2 space-y-8">

                    <EditableText
                        value={block.category || 'CATEGORY'}
                        className={`block text-xs font-semibold uppercase tracking-[0.30em] ${theme.sub}`}
                        onSave={(val) => onUpdate({ category: val })}
                    />

                    <EditableText
                        value={block.heading || 'Heading Title'}
                        className={`block text-5xl md:text-6xl font-bold leading-tight tracking-tight ${theme.text}`}
                        onSave={(val) => onUpdate({ heading: val })}
                    />

                    <EditableText
                        value={block.text || 'Add your description here...'}
                        className={`block text-lg leading-8 max-w-xl ${theme.sub}`}
                        onSave={(val) => onUpdate({ text: val })}
                    />

                    <EditableButton
                        label={block.button_label || 'Read More'}
                        url={block.button_url || '#'}
                        className={`inline-flex items-center gap-2 font-semibold transition-all duration-300 hover:gap-3 ${theme.text}`}
                        onSave={(label, url) =>
                            onUpdate({
                                button_label: label,
                                button_url: url
                            })
                        }
                    />

                </div>

            </div>

        </section>
    );
}

// 4. FEATURE BLOCK REVERSE
export function FeatureImageRightBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.theme, globalTheme);
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;

    return (
        <section className={`relative py-32 px-7 overflow-hidden ${theme.bg} transition-colors duration-500`}>

            <div className="max-w-7xl mx-auto flex flex-col md:flex-row-reverse items-center justify-between gap-20">

                {/* Image */}
                <div className="w-full md:w-1/2">
                    <div className="rounded-3xl overflow-hidden shadow-2xl ring-1 ring-white/10 transition-transform duration-500 hover:scale-[1.02]">
                        <EditableImage
                            websiteId={websiteId}
                            blockIndex={blockIndex}
                            src={block.image_url || "https://picsum.photos/800/600"}
                            className="w-full h-auto object-cover"
                            onSave={(url) => onUpdate({ image_url: url })}
                        />
                    </div>
                </div>

                {/* Content */}
                <div className="w-full md:w-1/2 space-y-8">

                    <EditableText
                        value={block.category || 'CATEGORY'}
                        className={`block text-xs font-semibold uppercase tracking-[0.30em] ${theme.sub}`}
                        onSave={(val) => onUpdate({ category: val })}
                    />

                    <EditableText
                        value={block.heading || 'Heading Title'}
                        className={`block text-5xl md:text-6xl font-bold leading-tight tracking-tight ${theme.text}`}
                        onSave={(val) => onUpdate({ heading: val })}
                    />

                    <EditableText
                        value={block.text || 'Add your description here...'}
                        className={`block text-lg leading-8 max-w-xl ${theme.sub}`}
                        onSave={(val) => onUpdate({ text: val })}
                    />

                    <EditableButton
                        label={block.button_label || 'Read More'}
                        url={block.button_url || '#'}
                        className={`inline-flex items-center gap-2 font-semibold transition-all duration-300 hover:gap-3 ${theme.text}`}
                        onSave={(label, url) =>
                            onUpdate({
                                button_label: label,
                                button_url: url
                            })
                        }
                    />

                </div>

            </div>

        </section>
    );
}


export const HeroHeadlineSchema = {

    type: "hero_headline",

    title: "Hero Headline",

    category: "Hero",

    purpose: "Landing page hero",

    description:
        "Large hero section with subtitle, heading, description and two CTA buttons.",

    tags: [
        "hero",
        "landing",
        "headline",
        "cta"
    ],

    defaults: {

        subtitle: "WELCOME TO THE FUTURE",

        heading: "Build Better Digital Reality.",

        text:
            "Create a polished website with reusable sections and complete editorial control.",

        btn1_label: "Get Started",

        btn1_url: "#",

        btn2_label: "View Docs",

        btn2_url: "#"
    },

    fields: [

        {
            key:"subtitle",
            type:"text"
        },

        {
            key:"heading",
            type:"text"
        },

        {
            key:"text",
            type:"textarea"
        },

        {
            key:"btn1_label",
            type:"text"
        },

        {
            key:"btn1_url",
            type:"url"
        },

        {
            key:"btn2_label",
            type:"text"
        },

        {
            key:"btn2_url",
            type:"url"
        }

    ]

};

export function HeroHeadlineBlock({ block, blockIndex, onUpdate, globalTheme }) {

    const theme = getEffectiveTheme(block.theme, globalTheme);

    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;

    // Merge defaults gikan sa schema
    const data = {
        ...HeroHeadlineSchema.defaults,
        ...block
    };

    const primaryBtn = {
        bg: "bg-white",
        text: "text-slate-900"
    };

    const primaryTheme = colorFamilies[globalTheme.primary];

    const coloredBtn = {
        bg: primaryTheme?.bg || "bg-indigo-600",
        text: primaryTheme?.text || "text-white"
    };

    const activeStyle =
        (block.theme === "secondary" || block.theme === "tertiary")
            ? coloredBtn
            : primaryBtn;

    return (
        <section
            className={`relative w-full py-24 px-[8%] ${theme.bg} overflow-hidden transition-colors duration-500`}
        >

            {/* Background */}
            <div className="absolute top-[-120px] right-[-120px] w-[650px] h-[650px] rounded-full bg-gradient-to-br from-white/25 via-white/10 to-transparent blur-[180px]" />

            <div className="relative z-10 max-w-4xl">

                <EditableText
                    value={data.subtitle}
                    className={`font-bold tracking-widest uppercase text-sm block ${theme.sub}`}
                    onSave={(val) => onUpdate({ subtitle: val })}
                />

                <h1 className="text-6xl md:text-8xl font-extrabold mt-6 leading-[1.1]">
                    <EditableText
                        value={data.heading}
                        className={`block ${theme.text}`}
                        onSave={(val) => onUpdate({ heading: val })}
                    />
                </h1>

                <div className="mt-8 text-xl max-w-2xl">
                    <EditableText
                        value={data.text}
                        className={`block ${theme.sub}`}
                        onSave={(val) => onUpdate({ text: val })}
                    />
                </div>

                <div className="mt-12 flex gap-4">

                    <EditableButton
                        label={data.btn1_label}
                        url={data.btn1_url}
                        className={`px-8 py-4 rounded-full font-bold transition !opacity-100 ${activeStyle.bg} ${activeStyle.text}`}
                        onSave={(label, url) =>
                            onUpdate({
                                btn1_label: label,
                                btn1_url: url
                            })
                        }
                    />

                    <EditableButton
                        label={data.btn2_label}
                        url={data.btn2_url}
                        className={`border px-8 py-4 rounded-full font-bold transition ${theme.border || "border-slate-700"} ${theme.text}`}
                        onSave={(label, url) =>
                            onUpdate({
                                btn2_label: label,
                                btn2_url: url
                            })
                        }
                    />

                </div>

            </div>

        </section>
    );
}
