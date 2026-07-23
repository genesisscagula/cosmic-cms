import React, { useState } from 'react';
import { getEffectiveTheme } from '../../theme/Theme';

function EditableText({ value, onSave, className }) {
    const [isEditing, setIsEditing] = useState(false);
    const [currentValue, setCurrentValue] = useState(value || '');

    return (
        <>
            <div className="relative group/text cursor-pointer" onClick={() => setIsEditing(true)}>
                <span className={className}>{value || 'Click to add text'}</span>
                <span className="absolute -top-2 -right-6 hidden group-hover/text:inline-block bg-indigo-600 text-white text-[9px] px-1 rounded shadow">✏️</span>
            </div>
            {isEditing && (
                <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-[9999] p-4">
                    <div className="bg-slate-900 p-6 rounded-2xl w-full max-w-md border border-slate-800 text-slate-100 font-sans space-y-4">
                        <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider">✏️ Update Nav Element</h3>
                        <input 
                            type="text"
                            className="w-full bg-slate-950 text-white p-3 text-sm rounded-xl border border-slate-700 focus:border-emerald-500 focus:outline-none"
                            value={currentValue}
                            onChange={(e) => setCurrentValue(e.target.value)}
                            autoFocus
                            onKeyDown={(e) => e.key === 'Enter' && (onSave(currentValue), setIsEditing(false))}
                        />
                        <div className="flex justify-end gap-2 text-xs">
                            <button type="button" onClick={() => setIsEditing(false)} className="px-3 py-1.5 bg-slate-800 rounded">Cancel</button>
                            <button type="button" onClick={() => { onSave(currentValue); setIsEditing(false); }} className="px-4 py-1.5 bg-emerald-600 rounded font-bold">Save</button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

export function DarkCyanHeader({ block, onUpdate }) {
    const menuItems = block.menu || [{ label: 'Home', url: '#' }, { label: 'About', url: '#' }, { label: 'Services', url: '#' }];

    // Theme Config for Light Mode
    const theme = block.theme === 'white' ? 'bg-white border-slate-200' : 'bg-[#f8fafc] border-slate-200';
    const textColor = block.theme === 'white' ? 'text-slate-900' : 'text-slate-800';
    const subColor = 'text-slate-500';
    const accent = 'text-emerald-600';

    const updateMenuLabel = (idx, newLabel) => {
        const updatedMenu = [...menuItems];
        updatedMenu[idx].label = newLabel;
        onUpdate({ menu: updatedMenu });
    };

    return (
        <header className={`w-full ${theme} py-5 px-8 flex justify-between items-center border-b transition-colors duration-500`}>
            <EditableText 
                value={block.logo_text || 'AkongLogo'} 
                className={`text-2xl font-bold ${accent} cursor-pointer`}
                onSave={(val) => onUpdate({ logo_text: val })}
            />
            <nav>
                <ul className="flex list-none gap-[30px] items-center">
                    {menuItems.map((item, i) => (
                        <li key={i}>
                            <EditableText 
                                value={item.label} 
                                className={`${textColor} text-base hover:${accent} transition cursor-pointer`}
                                onSave={(val) => updateMenuLabel(i, val)}
                            />
                        </li>
                    ))}
                </ul>
            </nav>
        </header>
    );
}

export function GlassmorphismHeader({ block, onUpdate, globalTheme }) {
    const menuItems = block.menu || [
        { label: 'Home', url: '#' }, 
        { label: 'About', url: '#' }, 
        { label: 'Services', url: '#' }, 
        { label: 'Blog', url: '#' }
    ];

    // Theme Config for Light/Neutral
    const theme = block.theme === 'white' ? 'bg-white' : 'bg-[#f8fafc]';
    const textColor = 'text-slate-900';
    const subColor = 'text-slate-500';

    const primaryTheme = getEffectiveTheme('primary', globalTheme);

    const updateMenuLabel = (idx, newLabel) => {
        const updatedMenu = [...menuItems];
        updatedMenu[idx].label = newLabel;
        onUpdate({ menu: updatedMenu });
    };

    return (
        <header className={`w-full ${theme} py-6 px-[8%] flex justify-between items-center border-b border-slate-200`}>
            <EditableText 
                value={block.logo_text || 'DesignKaBai'} 
                className={`text-xl font-extrabold tracking-wide ${textColor} cursor-pointer`}
                onSave={(val) => onUpdate({ logo_text: val })}
            />
            <nav className="flex items-center gap-10">
                <ul className="flex list-none gap-[40px]">
                    {menuItems.map((item, i) => (
                        <li key={i}>
                            <EditableText 
                                value={item.label} 
                                className={`${subColor} text-[15px] font-medium hover:${textColor} transition cursor-pointer`}
                                onSave={(val) => updateMenuLabel(i, val)}
                            />
                        </li>
                    ))}
                </ul>
                <div
                    className={`
                        ${primaryTheme.bg}
                        ${primaryTheme.text}
                        px-[22px]
                        py-[10px]
                        rounded-full
                        text-sm
                        font-semibold
                        cursor-pointer
                        hover:opacity-90
                        transition
                    `}
                >
                    <EditableText 
                        value={block.cta_label || 'Get Started'} 
                        className="text-white font-bold"
                        onSave={(val) => onUpdate({ cta_label: val })}
                    />
                </div>
            </nav>
        </header>
    );
}
