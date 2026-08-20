import axios from "axios";
import { useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";

import { showCosmicNotification } from "@/Components/CosmicNotification";
import { useCreditBalance } from "@/Hooks/useCreditBalance";

const AI_TEXT_COST = 10;

function resolvePageId() {
    if (typeof window === 'undefined') return null;
    const match = window.location.pathname.match(/\/pages\/(\d+)\/builder(?:\/|$)/);
    return match ? Number(match[1]) : null;
}

function inferFieldRole(className = '', isTextArea = false) {
    const classes = String(className || '');
    if (isTextArea) return 'supporting paragraph';
    if (/text-(?:4xl|5xl|6xl|7xl|8xl|9xl)|text-\[.*rem\]/.test(classes)) return 'main heading';
    if (/uppercase|tracking-\[/.test(classes)) return 'eyebrow or short label';
    if (/text-(?:2xl|3xl)/.test(classes)) return 'section heading';
    if (/text-(?:xs|sm)/.test(classes)) return 'short label';
    return 'website text';
}

export function EditableText({ value, onSave, className, isTextArea = false, style = undefined }) {
    const [isEditing, setIsEditing] = useState(false);
    const [currentValue, setCurrentValue] = useState(value || '');
    const [aiInstruction, setAiInstruction] = useState('');
    const [aiDraft, setAiDraft] = useState('');
    const [aiBusy, setAiBusy] = useState(false);
    const { balance, setBalance } = useCreditBalance();

    useEffect(() => {
        if (!isEditing) setCurrentValue(value || '');
    }, [value, isEditing]);

    const mode = currentValue.trim() ? 'rewrite' : 'generate';
    const aiLabel = mode === 'rewrite' ? 'Rewrite with Cosmic AI' : 'Generate with Cosmic AI';
    const fieldRole = useMemo(() => inferFieldRole(className, isTextArea), [className, isTextArea]);

    const closeEditor = () => {
        setIsEditing(false);
        setAiDraft('');
        setAiInstruction('');
    };

    const generateAiText = async () => {
        if (aiBusy) return;
        const pageId = resolvePageId();
        if (!pageId) {
            showCosmicNotification({
                title: 'Cosmic AI is unavailable here',
                message: 'This text is not connected to a Builder page yet.',
                tone: 'error',
            });
            return;
        }

        if (Number.isFinite(Number(balance)) && Number(balance) < AI_TEXT_COST) {
            showCosmicNotification({
                title: 'Not enough Cosmic Credits',
                message: `Cosmic AI text generation costs ${AI_TEXT_COST} credits.`,
                tone: 'error',
            });
            return;
        }

        setAiBusy(true);
        try {
            const token = new URLSearchParams(window.location.search).get('token');
            const response = await axios.post(
                `/pages/${pageId}/ai/text${token ? `?token=${encodeURIComponent(token)}` : ''}`,
                {
                    mode,
                    current_value: currentValue,
                    instruction: aiInstruction.trim(),
                    field_kind: isTextArea ? 'textarea' : 'text',
                    field_role: fieldRole,
                    presentation_hint: String(className || '').slice(0, 450),
                    token: token || undefined,
                },
                { headers: { Accept: 'application/json' } },
            );

            const text = String(response.data?.text || '').trim();
            if (!text) throw new Error('No text returned.');
            setAiDraft(text);

            const nextBalance = response.data?.credit_balance ?? response.data?.balance;
            if (Number.isFinite(Number(nextBalance))) setBalance(Number(nextBalance));

            showCosmicNotification({
                title: mode === 'rewrite' ? 'Rewrite ready' : 'Content ready',
                message: `${AI_TEXT_COST} Cosmic Credits used. Review the suggestion before replacing your text.`,
                tone: 'success',
            });
        } catch (error) {
            console.error(error);
            showCosmicNotification({
                title: 'Cosmic AI could not generate text',
                message: error.response?.data?.message || 'Generation failed. No credits were charged.',
                tone: 'error',
            });
        } finally {
            setAiBusy(false);
        }
    };

    return (
        <>
            <div data-cosmic-edit-control="text" className="relative group/text cursor-pointer max-w-full block w-full" onClick={() => setIsEditing(true)}>
                <span className={className} style={style}>{value || 'Click to add text'}</span>
                <span className="absolute -top-2 right-2 hidden group-hover/text:inline-block bg-indigo-600 text-white text-[10px] px-1.5 py-0.5 rounded shadow-md font-sans z-30">
                    ✏️ Edit
                </span>
            </div>

            {isEditing && createPortal(
                <div className="cosmic-inline-edit-overlay fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
                    <div className="cosmic-inline-edit-modal w-full max-w-xl space-y-4 rounded-2xl border border-slate-800 bg-slate-900 p-6 font-sans text-slate-100 shadow-2xl">
                        <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-violet-300">Cosmic Builder</p>
                                <h3 className="mt-1 text-sm font-bold text-slate-200">Update text content</h3>
                            </div>
                            <button type="button" onClick={closeEditor} className="text-lg text-slate-500 transition hover:text-white">✕</button>
                        </div>

                        <div>
                            {isTextArea ? (
                                <textarea
                                    className="w-full rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"
                                    rows={6}
                                    value={currentValue}
                                    onChange={(e) => { setCurrentValue(e.target.value); setAiDraft(''); }}
                                    autoFocus
                                />
                            ) : (
                                <input
                                    type="text"
                                    className="w-full rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"
                                    value={currentValue}
                                    onChange={(e) => { setCurrentValue(e.target.value); setAiDraft(''); }}
                                    autoFocus
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && !aiBusy) {
                                            onSave(currentValue);
                                            closeEditor();
                                        }
                                    }}
                                />
                            )}
                        </div>

                        <div className="cosmic-inline-ai-text rounded-2xl border border-violet-400/20 bg-violet-500/10 p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-[0.18em] text-violet-300">Cosmic · AI Content</p>
                                    <p className="mt-1 text-xs leading-5 text-slate-400">{mode === 'rewrite' ? 'Polish the current copy or tell Cosmic what to change.' : 'Describe what you want written, or let Cosmic infer it from this field.'}</p>
                                </div>
                                <span className="rounded-full border border-amber-300/20 bg-amber-300/10 px-2.5 py-1 text-[10px] font-bold text-amber-200">⚡ {AI_TEXT_COST}</span>
                            </div>

                            <textarea
                                rows={2}
                                value={aiInstruction}
                                onChange={(e) => setAiInstruction(e.target.value)}
                                placeholder={mode === 'rewrite' ? 'Optional: Make it shorter, more premium, friendlier…' : 'Optional: Describe the copy you want…'}
                                className="mt-3 w-full resize-y rounded-xl border border-violet-300/20 bg-slate-950/80 px-3 py-2.5 text-xs text-white outline-none placeholder:text-slate-600 focus:border-violet-400"
                            />
                            <button
                                type="button"
                                onClick={generateAiText}
                                disabled={aiBusy}
                                className="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-xl bg-violet-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-violet-400 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {aiBusy ? '✨ Cosmic is writing…' : `✨ ${aiLabel} · ${AI_TEXT_COST} Credits`}
                            </button>

                            {aiDraft && (
                                <div className="mt-4 rounded-xl border border-emerald-400/20 bg-emerald-400/10 p-3">
                                    <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-300">AI suggestion</p>
                                    <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-100">{aiDraft}</p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <button type="button" onClick={() => { setCurrentValue(aiDraft); setAiDraft(''); }} className="rounded-lg bg-emerald-500 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-400">Use this text</button>
                                        <button type="button" onClick={generateAiText} disabled={aiBusy} className="rounded-lg border border-slate-600 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/5 disabled:opacity-50">Try again · {AI_TEXT_COST}</button>
                                        <button type="button" onClick={() => setAiDraft('')} className="rounded-lg px-3 py-2 text-xs font-semibold text-slate-500 hover:text-slate-300">Dismiss</button>
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end gap-3 pt-1 text-xs">
                            <button type="button" onClick={closeEditor} className="rounded-lg bg-slate-800 px-4 py-2 font-medium text-slate-300 transition hover:bg-slate-700">Cancel</button>
                            <button type="button" onClick={() => { onSave(currentValue); closeEditor(); }} className="rounded-lg bg-emerald-600 px-4 py-2 font-bold text-white shadow-lg shadow-emerald-900/20 transition hover:bg-emerald-500">Save Changes</button>
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </>
    );
}
