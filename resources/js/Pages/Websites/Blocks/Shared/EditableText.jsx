import { useState } from "react";
import { createPortal } from "react-dom";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";


export function EditableText({ value, onSave, className, isTextArea = false }) {
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
            {isEditing && createPortal(
                <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-[9999] p-4">
                    <div className="bg-slate-900 border border-slate-800 p-6 rounded-2xl w-full max-w-lg shadow-2xl text-slate-100 font-sans space-y-4">
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
            , document.body)}
        </>
    );
}
