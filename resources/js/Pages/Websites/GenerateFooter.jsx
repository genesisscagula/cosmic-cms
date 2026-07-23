import React from 'react';
import { EditableText } from "./Blocks/Shared/EditableText";
import themeCatalog from "../../../theme/theme-families.json";

export const themeConfig = themeCatalog.legacyFooterFamilies;


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
