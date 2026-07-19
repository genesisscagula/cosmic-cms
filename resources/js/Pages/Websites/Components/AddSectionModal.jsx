import BlockPreviewCard from "./BlockPreviewCard";

import HeroHeadlinePreview from "./Previews/HeroHeadlinePreview";
import ServicesGridPreview from "./Previews/ServicesGridPreview";
import FeatureLeftPreview from "./Previews/FeatureLeftPreview";
import FeatureRightPreview from "./Previews/FeatureRightPreview";
import HeroCenteredPreview from "./Previews/HeroCenteredPreview";
import ServicesBentoPreview from "./Previews/ServicesBentoPreview";


export const BlockRegistry = [
    {
        type: "hero_headline",
        title: "Hero",
        buttonLabel: "Install",
        buttonClass: "bg-rose-600 hover:bg-rose-500",
        preview: HeroHeadlinePreview,
        payload: {
            type: "hero_headline",
            subtitle: "WELCOME TO THE FUTURE",
            heading: "Build Better Digital Reality.",
            text: "Focus sa logic, biya-i ang manual coding. Ang imong website, automated na sa atong custom CMS logic.",
            btn1_label: "Get Started",
            btn1_url: "#",
            btn2_label: "View Docs",
            btn2_url: "#"
        }
    },

    {
        type: "services_cards",
        title: "Services Grid",
        buttonLabel: "Install Grid",
        buttonClass: "bg-blue-600 hover:bg-blue-500",
        preview: ServicesGridPreview,
        payload: {
            type: "services_cards",
            heading: "Our Services",
            tagline: "WHAT WE OFFER"
        }
    },

    {
        type: "feature_image_left",
        title: "Feature Image Left",
        buttonLabel: "Install Feature Block",
        buttonClass: "bg-blue-600 hover:bg-blue-500",
        preview: FeatureLeftPreview,
        payload: {
            type: "feature_image_left",
            category: "CATEGORY",
            heading: "Lorem ipsum dolor sit amet",
            text: "Consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
            button_label: "Read more →",
            button_url: "#",
            image_url: "https://picsum.photos/800/400"
        }
    },

    {
        type: "feature_image_right",
        title: "Feature Image Right",
        buttonLabel: "Install Reverse Block",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: FeatureRightPreview,
        payload: {
            type: "feature_image_right",
            category: "CATEGORY",
            heading: "Lorem ipsum dolor sit amet",
            text: "Consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
            button_label: "Read more →",
            button_url: "#",
            image_url: "https://picsum.photos/800/400"
        }
    },

    {
        type: "hero_centered_cta",
        title: "Hero: Accent Focus",
        buttonLabel: "Install Accent Focus",
        buttonClass: "bg-emerald-600 hover:bg-emerald-500",
        preview: HeroCenteredPreview,
        payload: {
            type: "hero_centered_cta",
            heading: "Build Modern Websites Fast",
            tagline: "GET STARTED"
        }
    },

    {
        type: "services_bento",
        title: "Services Bento",
        buttonLabel: "Install Bento",
        buttonClass: "bg-violet-600 hover:bg-violet-500",
        preview: ServicesBentoPreview,
        payload: {
            type: "services_bento",
            heading: "Solutions Built Around Your Business",
            tagline: "OUR SERVICES"
        }
    },
];

export default function AddSectionModal({
    open,
    onClose,
    onAdd
}) {

    const aiResult = {};

    const generateWithAI = () => {};



    if (!open) return null;

    return (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div className="bg-slate-900 border border-slate-700 p-8 rounded-3xl w-full max-w-5xl shadow-2xl text-slate-100 max-h-[90vh] overflow-y-auto">
                <div className="flex justify-between items-center mb-8">
                    <h2 className="text-2xl font-extrabold text-white flex items-center gap-2">✨ AI Layout Injector</h2>
                    <button  onClick={onClose} className="text-slate-400 hover:text-white text-2xl">✕</button>
                </div>

                <div className="space-y-4 mb-8">
                    <input 
                        type="text" 
                        placeholder="Describe the block you want to generate..."
                        className="w-full bg-slate-950 border border-slate-700 p-4 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
                        onKeyDown={(e) => e.key === 'Enter' && generateWithAI(e.target.value)}
                    />
                </div>

                <div className="border-t border-slate-800 pt-8">
                    <h3 className="text-sm font-semibold tracking-wider text-slate-400 uppercase mb-6">Select Available Layout Options</h3>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">

                        {BlockRegistry.map((block) => (
                            <BlockPreviewCard
                                key={block.type}
                                onAdd={onAdd}
                                title={block.title}
                                buttonLabel={block.buttonLabel}
                                buttonClass={block.buttonClass}
                                preview={block.preview}
                                payload={block.payload}
                            />
                        ))}          
                        
                    </div>
                </div>
            </div>
        </div>
    );
}

