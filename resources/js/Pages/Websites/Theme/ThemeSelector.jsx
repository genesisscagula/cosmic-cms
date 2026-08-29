import { useEffect, useState } from "react";

import ThemeModal from "./ThemeModal";
import themeMetadata from "./ThemeMetadata";

export default function ThemeSelector({
    value,
    onChange,
    compact = false,
    themeAccess = null,
    signupUrl = null,
    customTheme = null,
    hasLogo = false,
    brandMatchNeeded = false,
    onMatchBrandToLogo = null,
    brandMatchBusy = false,
    onOpenSession = null,
    onCancelSession = null,
    onApplySession = null,
    onAskLuna = null,
    onThemeLunaIntent = null,
    onGenerateCustomTheme = null,
    lunaBusy = false,
}) {

    const [open, setOpen] = useState(false);
    const [draftTheme, setDraftTheme] = useState(value);
    const [draftGeneratedTheme, setDraftGeneratedTheme] = useState(null);

    useEffect(() => {
        if (!open) {
            setDraftTheme(value);
            setDraftGeneratedTheme(null);
        }
    }, [value, open]);

    const customThemeMetadata = customTheme ? {
        id: 'my-brand',
        name: customTheme.name || 'My Brand Theme',
    } : null;
    const currentTheme = value === 'my-brand'
        ? customThemeMetadata
        : themeMetadata.find(theme => theme.id === value);

    return (
        <>
            <button
                type="button"
                onClick={() => {
                    setDraftTheme(value);
                    setDraftGeneratedTheme(null);
                    onOpenSession?.();
                    setOpen(true);
                }}
                aria-label={`Choose theme: ${currentTheme?.name || value}`}
                className={compact
                    ? "flex h-10 w-40 items-center justify-between gap-2 rounded-lg border border-slate-300 bg-slate-100 px-3 text-left transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400"
                    : "min-w-[260px] flex items-center justify-between gap-4 rounded-xl border border-slate-300 bg-slate-100 px-4 py-2.5 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400"
                }
            >

                <div className={`flex min-w-0 items-center ${compact ? 'gap-2' : 'gap-3'}`}>

                    <span>
                        🎨
                    </span>

                    <div className="min-w-0 text-left">

                        {!compact && (
                            <div className="text-[9px] uppercase tracking-wider text-slate-400 font-bold">
                                Theme
                            </div>
                        )}

                        <div className={`truncate font-semibold text-slate-700 ${compact ? 'text-xs' : ''}`}>
                            {currentTheme?.name || value}
                        </div>

                    </div>

                </div>

                <span className="text-[10px] leading-none text-slate-400">
                    ▼
                </span>

            </button>


            <ThemeModal
                open={open}
                onClose={() => { onCancelSession?.(); setOpen(false); }}
                onApply={() => {
                    onApplySession?.({ selectedTheme: draftTheme, generatedTheme: draftGeneratedTheme });
                    setOpen(false);
                }}
                selectedTheme={draftTheme}
                onSelect={(theme) => { setDraftGeneratedTheme(null); setDraftTheme(theme); }}
                onSelectGeneratedTheme={(theme) => { setDraftGeneratedTheme(theme); setDraftTheme(theme?.key || 'luna-generated'); }}
                themeAccess={themeAccess}
                signupUrl={signupUrl}
                customTheme={customTheme}
                hasLogo={hasLogo}
                brandMatchNeeded={brandMatchNeeded}
                onMatchBrandToLogo={onMatchBrandToLogo}
                brandMatchBusy={brandMatchBusy}
                onAskLuna={onAskLuna}
                onThemeLunaIntent={onThemeLunaIntent}
                onGenerateCustomTheme={onGenerateCustomTheme}
                lunaBusy={lunaBusy}
            />

        </>
    );
}
