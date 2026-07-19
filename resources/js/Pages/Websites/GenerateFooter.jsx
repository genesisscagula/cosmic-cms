import React from 'react';
import { EditableText } from "./Blocks/Shared/EditableText";

export const themeConfig = {
    // Existing
    dark: { bg: 'bg-[#0b0f19]', text: 'text-white', sub: 'text-[#94a3b8]', card: 'bg-[#111827]', border: 'border-slate-800' },
    midnight: { bg: 'bg-[#1e293b]', text: 'text-slate-100', sub: 'text-slate-300', card: 'bg-[#334155]', border: 'border-slate-600' },
    charcoal: { bg: 'bg-[#1a1a1a]', text: 'text-gray-100', sub: 'text-gray-400', card: 'bg-[#262626]', border: 'border-gray-700' },
    'slate-light': { bg: 'bg-[#475569]', text: 'text-white', sub: 'text-slate-200', card: 'bg-[#64748b]', border: 'border-slate-500' },
    white: { bg: 'bg-white', text: 'text-slate-900', sub: 'text-slate-600', card: 'bg-slate-50', border: 'border-slate-200' },
    stone: { bg: 'bg-[#f8fafc]', text: 'text-slate-800', sub: 'text-slate-500', card: 'bg-white', border: 'border-slate-200' },
    sky: { bg: 'bg-[#f0f9ff]', text: 'text-slate-900', sub: 'text-slate-600', card: 'bg-white', border: 'border-blue-100' },
    'slate-950': { bg: 'bg-[#0f172a]', text: 'text-white', sub: 'text-slate-400', card: 'bg-[#1e293b]', border: 'border-slate-700' },
    
    // New Color Families
    emerald: { bg: 'bg-[#064e3b]', text: 'text-emerald-50', sub: 'text-emerald-200', card: 'bg-[#065f46]', border: 'border-emerald-800' },
    rose: { bg: 'bg-[#881337]', text: 'text-rose-50', sub: 'text-rose-200', card: 'bg-[#9f1239]', border: 'border-rose-900' },
    violet: { bg: 'bg-[#2e1065]', text: 'text-violet-50', sub: 'text-violet-200', card: 'bg-[#4c1d95]', border: 'border-violet-800' },
    coffee: { bg: 'bg-[#422006]', text: 'text-amber-50', sub: 'text-amber-200', card: 'bg-[#78350f]', border: 'border-amber-900' },
};


export function MinimalFooter({ block, onUpdate }) {
    // Gigamit nato ang 'stone' isip default neutral base
    const isWhite = block.theme === 'white';
    const bg = isWhite ? 'bg-white' : 'bg-[#f8fafc]'; // White o Stone
    const text = 'text-slate-900';
    const sub = 'text-slate-500';

    return (
        <footer className={`w-full ${bg} py-12 px-8 flex justify-between items-center border-t border-slate-200 transition-colors duration-500`}>
            <div className="w-auto flex-shrink-0">
                <EditableText 
                    value={block.logo_text || 'CosmicCMS'} 
                    className={`font-bold cursor-pointer ${text} transition whitespace-nowrap`}
                    onSave={(val) => onUpdate({ logo_text: val })}
                />
            </div>
            <div className="text-sm min-w-0">
                <EditableText 
                    value={block.copyright || '© 2026. All rights reserved.'} 
                    className={`cursor-pointer ${sub} transition whitespace-nowrap`}
                    onSave={(val) => onUpdate({ copyright: val })}
                />
            </div>
        </footer>
    );
}

export function DetailedFooter({ block, onUpdate }) {
    const isWhite = block.theme === 'white';
    const bg = isWhite ? 'bg-white' : 'bg-[#f8fafc]';
    const text = 'text-slate-900';
    const sub = 'text-slate-500';

    const links = block.links || [
        { label: 'Privacy Policy' },
        { label: 'Terms of Service' },
        { label: 'Support' }
    ];

    const updateLink = (idx, newLabel) => {
        const updatedLinks = [...links];
        updatedLinks[idx].label = newLabel;
        onUpdate({ links: updatedLinks });
    };

    return (
        <footer className={`w-full ${bg} border-t border-slate-200 py-12 px-8 transition-colors duration-500`}>
            <div className="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-8">
                <div className="space-y-2">
                    <EditableText 
                        value={block.logo_text || 'CosmicCMS'} 
                        className={`${text} font-bold text-lg cursor-pointer`}
                        onSave={(val) => onUpdate({ logo_text: val })}
                    />
                    <p className={`text-sm italic ${sub}`}>It's just logical.</p>
                </div>
                
                <div className="md:col-span-3 flex flex-wrap gap-8">
                    {links.map((link, i) => (
                        <EditableText 
                            key={i}
                            value={link.label} 
                            className={`text-sm ${sub} hover:${text} cursor-pointer transition whitespace-nowrap`}
                            onSave={(val) => updateLink(i, val)}
                        />
                    ))}
                </div>
            </div>
        </footer>
    );
}