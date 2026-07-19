import BlockPreviewCard from "./BlockPreviewCard";
import HeroHeadlinePreview from "./Previews/HeroHeadlinePreview";

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

                        <BlockPreviewCard
                            onAdd={onAdd}
                            title="Hero"
                            buttonLabel="Install"
                            buttonClass="bg-rose-600 hover:bg-rose-500" 
                            preview={<HeroHeadlinePreview />}
                            payload={{
                                type: 'hero_headline',
                                subtitle: 'WELCOME TO THE FUTURE',
                                heading: 'Build Better Digital Reality.',
                                text: 'Focus sa logic, biya-i ang manual coding. Ang imong website, automated na sa atong custom CMS logic.',
                                btn1_label: 'Get Started',
                                btn1_url: '#',
                                btn2_label: 'View Docs',
                                btn2_url: '#'
                            }}
                        />

                        {/* Modern Hero Preview */}
                        <div className="border border-slate-800 bg-slate-950 p-6 rounded-2xl space-y-4">
                            <div className="h-40 bg-slate-900 rounded-xl border border-slate-800 flex items-center justify-center text-slate-600 font-bold overflow-hidden relative">
                                 <div className="text-center scale-75">
                                    <div className="text-emerald-500 text-xs font-bold uppercase mb-2">TAGLINE</div>
                                    <div className="text-xl font-black text-white mb-2">Main Headline</div>
                                    <div className="w-16 h-8 bg-emerald-600 rounded-full mx-auto"></div>
                                 </div>
                            </div>
                            <h4 className="text-lg font-bold text-white">Hero: Accent Focus</h4>
                            <button 
                                onClick={() => onAdd({ type: 'hero_centered_cta', heading: aiResult?.heading || 'Build Modern Websites Fast', tagline: 'GET STARTED' })}
                                className="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-3 rounded-xl font-bold transition"
                            >
                                🚀 Install Accent Focus
                            </button>
                        </div>

                        {/* Services Grid Preview */}
                        <div className="border border-slate-800 bg-slate-950 p-6 rounded-2xl space-y-4">
                            <div className="h-40 bg-slate-900 rounded-xl border border-slate-800 flex items-center justify-center text-slate-600 font-bold overflow-hidden">
                                 <div className="grid grid-cols-3 gap-2 w-full px-4 scale-75">
                                    <div className="h-20 bg-slate-800 rounded"></div>
                                    <div className="h-20 bg-slate-800 rounded"></div>
                                    <div className="h-20 bg-slate-800 rounded"></div>
                                 </div>
                            </div>
                            <h4 className="text-lg font-bold text-white">Services Grid</h4>
                            <button 
                                onClick={() => onAdd({ type: 'services_cards', heading: aiResult?.heading || 'Our Services', tagline: 'WHAT WE OFFER' })}
                                className="w-full bg-blue-600 hover:bg-blue-500 text-white py-3 rounded-xl font-bold transition"
                            >
                                💠 Install Grid
                            </button>
                        </div>

                        {/* Feature Block Preview - Updated to Dark Mode */}
                        <div className="border border-slate-800 bg-slate-950 p-6 rounded-2xl space-y-4">
                            {/* Dark Mode Preview: Two Column Layout */}
                            <div className="h-40 bg-slate-900 rounded-xl border border-slate-800 flex overflow-hidden">
                                {/* Left: Image Box */}
                                <div className="w-1/2 h-full bg-slate-800 border-r border-slate-700 flex items-center justify-center">
                                    <span className="text-slate-600 text-[10px] uppercase font-bold">Image</span>
                                </div>
                                {/* Right: Text Lines */}
                                <div className="w-1/2 p-3 space-y-2 flex flex-col justify-center">
                                    <div className="h-2 w-16 bg-slate-700 rounded-full"></div>
                                    <div className="h-3 w-full bg-slate-600 rounded-full"></div>
                                    <div className="h-2 w-3/4 bg-slate-700 rounded-full"></div>
                                </div>
                            </div>

                            <h4 className="text-lg font-bold text-white">Feature Block</h4>
                            
                            <button 
                                onClick={() => onAdd({ 
                                    type: 'feature_image_left', 
                                    category: 'CATEGORY', 
                                    heading: 'Lorem ipsum dolor sit amet', 
                                    text: 'Consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
                                    button_label: 'Read more →',
                                    button_url: '#',
                                    image_url: 'https://picsum.photos/800/400'
                                })}
                                className="w-full bg-blue-600 hover:bg-blue-500 text-white py-3 rounded-xl font-bold transition"
                            >
                                🖼️ Install Feature Block
                            </button>
                        </div>

                        {/* Feature Block Reverse Preview */}
                        <div className="border border-slate-800 bg-slate-950 p-6 rounded-2xl space-y-4">
                            <div className="h-40 bg-slate-900 rounded-xl border border-slate-800 flex overflow-hidden">
                                {/* Text Lines (Now on the Left) */}
                                <div className="w-1/2 p-3 space-y-2 flex flex-col justify-center border-r border-slate-700">
                                    <div className="h-2 w-16 bg-slate-700 rounded-full"></div>
                                    <div className="h-3 w-full bg-slate-600 rounded-full"></div>
                                    <div className="h-2 w-3/4 bg-slate-700 rounded-full"></div>
                                </div>
                                {/* Image Box (Now on the Right) */}
                                <div className="w-1/2 h-full bg-slate-800 flex items-center justify-center">
                                    <span className="text-slate-600 text-[10px] uppercase font-bold">Image</span>
                                </div>
                            </div>

                            <h4 className="text-lg font-bold text-white">Feature Block Reverse</h4>
                            
                            <button 
                                onClick={() => onAdd({ 
                                    type: 'feature_image_right', 
                                    category: 'CATEGORY', 
                                    heading: 'Lorem ipsum dolor sit amet', 
                                    text: 'Consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
                                    button_label: 'Read more →',
                                    button_url: '#',
                                    image_url: 'https://picsum.photos/800/400'
                                })}
                                className="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-3 rounded-xl font-bold transition"
                            >
                                🖼️ Install Reverse Block
                            </button>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    );
}

