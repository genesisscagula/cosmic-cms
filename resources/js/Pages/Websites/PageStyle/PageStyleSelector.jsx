import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { confirmCosmicAction, showCosmicNotification } from '../../../Components/CosmicNotification';

const directionLabels = {
    clean: 'Clean & Professional',
    premium: 'Premium & Elegant',
    bold: 'Bold & Creative',
};

export default function PageStyleSelector({
    pageId,
    currentStyle,
    suggestions = [],
    blocks,
    disabled = false,
    trialMode = false,
    trialToken = null,
    creditBalance = 0,
    creditCost = 20,
    onApplied,
}) {
    const [open, setOpen] = useState(false);
    const [applying, setApplying] = useState('');
    const rootRef = useRef(null);
    const current = currentStyle || 'auto';
    const currentLabel = current === 'auto'
        ? 'Auto'
        : suggestions.find((style) => style.key === current)?.label || current.replaceAll('_', ' ');

    useEffect(() => {
        if (!open) return undefined;
        const close = (event) => {
            if (!rootRef.current?.contains(event.target)) setOpen(false);
        };
        const escape = (event) => {
            if (event.key === 'Escape') setOpen(false);
        };
        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', escape);
        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', escape);
        };
    }, [open]);

    const applyStyle = async (style) => {
        if (style.key === current) return;

        const confirmed = await confirmCosmicAction({
            title: `Apply ${style.label}?`,
            message: trialMode
                ? `Cosmic will reset every Spark to Auto and preview this creative direction. Cost: ${creditCost} Cosmic Credits. Balance: ${creditBalance} → ${Math.max(0, Number(creditBalance || 0) - creditCost)}.`
                : `Cosmic will reset every Spark to Auto and apply this creative direction across the page. Cost: ${creditCost} Credits.`,
            confirmLabel: `Use ${creditCost} Credits`,
        });
        if (!confirmed) return;

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
                message: 'Every Spark is back on Auto and the new page rhythm is ready.',
                tone: 'success',
            });
        } catch (error) {
            showCosmicNotification({
                title: 'Style generation failed',
                message: error.response?.data?.message || 'Cosmic could not apply this creative direction.',
                tone: 'error',
            });
        } finally {
            setApplying('');
        }
    };

    return (
        <div ref={rootRef} className={`relative hidden min-w-0 lg:block ${trialMode ? 'ml-2' : 'ml-5'}`}>
            <button
                type="button"
                disabled={disabled || applying !== ''}
                onClick={() => setOpen((value) => !value)}
                className={`cosmic-page-style-trigger inline-flex h-9 max-w-[190px] items-center gap-2 rounded-lg border px-3 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50 ${trialMode ? 'border-slate-300 bg-slate-100 text-slate-700 hover:bg-slate-200' : 'border-white/10 bg-white/[0.045] text-slate-200 hover:border-violet-400/35 hover:bg-violet-500/10'}`}
                title="Creative Direction"
            >
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" className="h-3.5 w-3.5 shrink-0 text-violet-300">
                    <path d="M10 2.25c.26 3.66 1.59 4.99 5.25 5.25-3.66.26-4.99 1.59-5.25 5.25-.26-3.66-1.59-4.99-5.25-5.25 3.66-.26 4.99-1.59 5.25-5.25Zm5.1 9.8c.09 1.25.55 1.71 1.8 1.8-1.25.09-1.71.55-1.8 1.8-.09-1.25-.55-1.71-1.8-1.8 1.25-.09 1.71-.55 1.8-1.8Z" />
                </svg>
                <span className="truncate capitalize">{applying ? 'Reimagining…' : currentLabel}</span>
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.8" className={`ml-auto h-3.5 w-3.5 shrink-0 text-slate-400 transition ${open ? 'rotate-180 text-violet-300' : ''}`}>
                    <path d="m5.5 7.5 4.5 4.5 4.5-4.5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </button>

            {open && (
                <div className="cosmic-page-style-menu absolute left-0 top-11 z-[90] w-[360px] overflow-hidden rounded-2xl border border-white/10 bg-[#15151a] shadow-2xl shadow-black/60">
                    <div className="border-b border-white/10 px-4 py-3.5">
                        <p className="text-sm font-bold text-white">Make AI style your page</p>
                        <p className="mt-1 text-xs leading-5 text-slate-400">{trialMode ? `Three curated directions based on this website’s industry. Applying one costs ${creditCost} Guest Cosmic Credits.` : `Three curated directions based on this website’s industry. Applying one costs ${creditCost} Credits.`}</p>
                    </div>
                    <div className="space-y-2 p-3">
                        {suggestions.map((style) => {
                            const selected = style.key === current;
                            return (
                                <button
                                    key={style.key}
                                    type="button"
                                    disabled={selected || applying !== ''}
                                    onClick={() => applyStyle(style)}
                                    className={`cosmic-page-style-option w-full rounded-xl border p-3 text-left transition focus:outline-none focus:ring-2 focus:ring-violet-400 ${selected ? 'is-selected border-violet-400/40 bg-violet-500/12' : 'border-white/8 bg-white/[0.025] hover:border-white/15 hover:bg-white/[0.055]'}`}
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="cosmic-page-style-direction text-[10px] font-bold uppercase tracking-[0.18em] text-violet-300">{directionLabels[style.direction] || style.direction}</p>
                                            <p className="cosmic-page-style-title mt-1 text-sm font-bold text-white">{style.label}</p>
                                        </div>
                                        <span className={`cosmic-page-style-cost rounded-full px-2 py-1 text-[10px] font-bold ${selected ? 'bg-violet-400/15 text-violet-200' : 'bg-white/[0.06] text-slate-400'}`}>
                                            {selected ? 'Current' : `${creditCost} Credits`}
                                        </span>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                    <div className="border-t border-white/10 bg-white/[0.02] px-4 py-3 text-[11px] text-slate-500">
                        Auto remains the safe default. Manual Spark colors are reset only after you confirm a new style.
                    </div>
                </div>
            )}
        </div>
    );
}
