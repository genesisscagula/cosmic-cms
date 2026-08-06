import { useState } from "react";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";


export function EditableButton({ label, url, onSave, className }) {
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