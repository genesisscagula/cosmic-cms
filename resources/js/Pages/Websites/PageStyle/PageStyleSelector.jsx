import { useEffect, useState } from 'react';
import axios from 'axios';
import { showCosmicNotification } from '../../../Components/CosmicNotification';

const directionLabels = {
    clean: 'Clean & Professional',
    premium: 'Premium & Elegant',
};

export default function PageStyleSelector({
    pageId,
    currentStyle,
    suggestions = [],
    blocks,
    disabled = false,
    trialMode = false,
    darkMode = true,
    trialToken = null,
    creditBalance = 0,
    creditCost = 20,
    onApplied,
}) {
    const [open, setOpen] = useState(false);
    const [applying, setApplying] = useState('');
    const current = ['balanced', 'clean', 'premium'].includes(String(currentStyle || '').toLowerCase())
        ? String(currentStyle).toLowerCase()
        : 'balanced';
    const [draftStyle, setDraftStyle] = useState(current);
    const light = trialMode || !darkMode;

    useEffect(() => {
        if (!open) return undefined;
        setDraftStyle(current);
        const escape = (event) => {
            if (event.key === 'Escape' && !applying) setOpen(false);
        };
        document.addEventListener('keydown', escape);
        return () => document.removeEventListener('keydown', escape);
    }, [open, current, applying]);

    const closeModal = () => {
        if (applying) return;
        setDraftStyle(current);
        setOpen(false);
    };

    const applyStyle = async () => {
        const style = suggestions.find((item) => item.key === draftStyle);
        if (!style || style.key === current) {
            setOpen(false);
            return;
        }

        setApplying(style.key);
        try {
            const targetRoute = trialMode
                ? route('trial-pages.style.apply', { trial: trialToken, page: pageId })
                : route('pages.style.apply', pageId);
            const response = await axios.post(targetRoute, {
                style: style.key,
                blocks,
            });
            onApplied?.(response.data);
            setOpen(false);
            showCosmicNotification({
                title: `${style.label} applied`,
                message: 'Page Style updated. Your Spark content and explicit section themes were preserved.',
                tone: 'success',
            });
        } catch (error) {
            showCosmicNotification({
                title: 'Page Style unavailable',
                message: error.response?.data?.message || 'Cosmic could not apply this page style.',
                tone: 'error',
            });
        } finally {
            setApplying('');
        }
    };

    return (
        <>
            {/* Toolbar navigation owns the visible trigger. Keep this hook hidden so
                Design -> Page Style can open the full popup without a duplicate control. */}
            <button
                type="button"
                disabled={disabled || applying !== ''}
                onClick={() => {
                    setDraftStyle(current);
                    setOpen(true);
                }}
                className="cosmic-page-style-trigger absolute h-px w-px overflow-hidden opacity-0 pointer-events-none"
                tabIndex={-1}
                aria-hidden="true"
            >
                Page Style
            </button>

            {open && (
                <div
                    className="cosmic-app-modal-backdrop cosmic-page-style-backdrop fixed inset-0 z-[10080] flex items-center justify-center p-4"
                    data-cosmic-modal-backdrop="page-style"
                    data-appearance={light ? 'light' : 'dark'}
                    style={{ backgroundColor: 'rgba(2, 6, 23, 0.58)', WebkitBackdropFilter: 'blur(8px)', backdropFilter: 'blur(8px)' }}
                    onMouseDown={(event) => {
                        if (event.target === event.currentTarget) closeModal();
                    }}
                >
                    <div
                        id="cosmic-page-style-modal"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="cosmic-page-style-title"
                        data-cosmic-app-modal="page-style"
                        data-appearance={light ? 'light' : 'dark'}
                        className={`cosmic-page-style-menu w-full max-w-3xl overflow-hidden rounded-3xl border shadow-2xl ${light ? 'border-slate-200 bg-white text-slate-900' : 'border-white/10 bg-[#111318] text-white'}`}
                    >
                        <div className={`flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 ${light ? 'border-slate-200' : 'border-white/10'}`}>
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[.18em] text-violet-500">Design</p>
                                <h3 id="cosmic-page-style-title" className={`mt-1 text-lg font-semibold ${light ? 'text-slate-950' : 'text-white'}`}>Page Style</h3>
                                <p className={`mt-1 max-w-xl text-xs leading-5 ${light ? 'text-slate-600' : 'text-slate-400'}`}>Choose the overall page rhythm. Your selection stays in this popup until you click Apply.</p>
                            </div>
                            <button type="button" onClick={closeModal} disabled={Boolean(applying)} className={`h-9 w-9 rounded-xl text-lg transition disabled:opacity-40 ${light ? 'text-slate-500 hover:bg-slate-100 hover:text-slate-900' : 'text-slate-400 hover:bg-white/10 hover:text-white'}`} aria-label="Close Page Style">×</button>
                        </div>

                        <div className="grid gap-3 p-5 sm:grid-cols-3 sm:p-6">
                            {suggestions.map((style) => {
                                const selected = style.key === draftStyle;
                                const applied = style.key === current;
                                return (
                                    <button
                                        key={style.key}
                                        type="button"
                                        disabled={Boolean(applying)}
                                        onClick={() => setDraftStyle(style.key)}
                                        className={`cosmic-page-style-option min-h-[138px] rounded-2xl border p-4 text-left transition focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:opacity-50 ${selected
                                            ? light
                                                ? 'is-selected border-violet-400 bg-violet-50 shadow-sm'
                                                : 'is-selected border-violet-400/55 bg-violet-500/15'
                                            : light
                                                ? 'border-slate-200 bg-slate-50 hover:border-violet-300 hover:bg-white'
                                                : 'border-white/10 bg-white/[0.025] hover:border-white/20 hover:bg-white/[0.055]'
                                        }`}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="cosmic-page-style-direction text-[10px] font-bold uppercase tracking-[0.16em] text-violet-500">{directionLabels[style.direction] || style.direction || 'Balanced'}</p>
                                                <p className={`cosmic-page-style-title mt-2 text-sm font-bold ${light ? 'text-slate-950' : 'text-white'}`}>{style.label}</p>
                                            </div>
                                            <span className={`cosmic-page-style-cost rounded-full px-2 py-1 text-[9px] font-bold ${selected ? 'bg-violet-500/15 text-violet-500' : light ? 'bg-white text-slate-500' : 'bg-white/[0.06] text-slate-400'}`}>
                                                {selected ? 'Selected' : applied ? 'Current' : 'Free'}
                                            </span>
                                        </div>
                                        <p className={`mt-4 text-[11px] leading-5 ${light ? 'text-slate-600' : 'text-slate-400'}`}>
                                            {style.key === 'balanced' && 'Premium defaults with a balanced mix of whitespace, surfaces, and brand color.'}
                                            {style.key === 'clean' && 'A quieter, minimal direction with light surfaces and restrained visual weight.'}
                                            {style.key === 'premium' && 'More expressive hierarchy, stronger contrast, and richer premium section rhythm.'}
                                        </p>
                                    </button>
                                );
                            })}
                        </div>

                        <div className={`flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4 sm:px-6 ${light ? 'border-slate-200 bg-slate-50' : 'border-white/10 bg-white/[0.02]'}`}>
                            <p className={`text-[11px] ${light ? 'text-slate-500' : 'text-slate-500'}`}>Balanced is the premium default. Content and explicit Spark themes are preserved.</p>
                            <div className="flex items-center gap-2">
                                <button type="button" onClick={closeModal} disabled={Boolean(applying)} className={`rounded-xl border px-4 py-2 text-xs font-bold transition disabled:opacity-40 ${light ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' : 'border-white/10 text-slate-300 hover:bg-white/5'}`}>Cancel</button>
                                <button type="button" onClick={applyStyle} disabled={Boolean(applying) || draftStyle === current} className="cosmic-page-style-apply rounded-xl bg-violet-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-40">
                                    {applying ? 'Applying…' : draftStyle === current ? 'Applied' : 'Apply Style'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
