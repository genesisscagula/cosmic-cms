import axios from "axios";
import { useEffect, useState } from "react";
import { showCosmicNotification } from "../../../Components/CosmicNotification";
import { useCreditBalance } from '@/Hooks/useCreditBalance';

const generationSteps = [
    { label: "Understand brief", threshold: 18 },
    { label: "Design structure", threshold: 42 },
    { label: "Create content", threshold: 72 },
    { label: "Build page", threshold: 96 },
];

export default function GeneratePageModal({
    open,
    onClose,
    onReplace,
    websiteContext = "",
    websiteId = null,
    headerOverlayEnabled = false,
    creditCost = 50,
}) {
    const { setBalance } = useCreditBalance();
    const [pagePrompt, setPagePrompt] = useState("");
    const [generating, setGenerating] = useState(false);
    const [progress, setProgress] = useState(0);
    const [stage, setStage] = useState("Understanding your request...");
    const [confirmGenerate, setConfirmGenerate] = useState(false);
    const [brandMode, setBrandMode] = useState("keep");

    useEffect(() => {
        if (!open) {
            setPagePrompt("");
            setGenerating(false);
            setProgress(0);
            setStage("Understanding your request...");
            setConfirmGenerate(false);
            setBrandMode("keep");
        }
    }, [open]);

    useEffect(() => {
        if (!generating) return undefined;

        const stages = [
            { at: 8, text: "Understanding your request..." },
            { at: 24, text: "Planning page categories..." },
            { at: 48, text: "Designing custom section layouts..." },
            { at: 68, text: "Creating your page content..." },
            { at: 84, text: "Building your page..." },
        ];

        let current = 4;
        setProgress(current);
        const timer = window.setInterval(() => {
            const increment = current < 35 ? 3 : current < 70 ? 2 : 1;
            current = Math.min(89, current + increment);
            setProgress(current);
            const active = [...stages].reverse().find((item) => current >= item.at);
            if (active) setStage(active.text);
        }, 180);

        return () => window.clearInterval(timer);
    }, [generating]);

    if (!open) return null;

    const generatePage = async () => {
        const prompt = pagePrompt.trim();
        if (!prompt || !onReplace) return;

        setGenerating(true);
        try {
            const generationSeed = `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
            const historyKey = `cosmic:luna:page-design-history:${websiteId || "trial"}`;
            let previousSections = [];
            try {
                previousSections = JSON.parse(window.localStorage.getItem(historyKey) || "[]");
                if (!Array.isArray(previousSections)) previousSections = [];
            } catch (_) {
                previousSections = [];
            }

            const brandDirective = brandMode === "new"
                ? "BRAND DIRECTION: Create a fresh visual brand direction for this generation. Explore a new color-family mood, typography character, visual rhythm, and art direction while keeping the business identity and factual content intact."
                : "BRAND DIRECTION: Keep the current website brand. Preserve its color-family identity and overall brand character, but create a fresh page composition and new visual pacing.";

            const contextualPrompt = [
                websiteContext || "Generate professional website content for this business.",
                `User instruction: ${prompt}`,
                brandDirective,
                `LUNA UNIQUE DESIGN SEED: ${generationSeed}. Treat this as a new art-direction pass. Do not intentionally reproduce a previous generated page composition.`,
            ].join("\n\n");

            const { data: plan } = await axios.post("/ai/select-sections", {
                prompt: contextualPrompt,
                header_overlay_enabled: Boolean(headerOverlayEnabled),
                website_id: websiteId,
                design_seed: generationSeed,
                avoid_sections: previousSections.slice(-16),
                brand_mode: brandMode,
            });
            const sections = plan.sections || [];
            if (!sections.length) throw new Error("Cosmic AI could not plan this page.");

            const { data } = await axios.post("/ai/generate-content", {
                prompt: contextualPrompt,
                sections,
                image_folder: plan.image_folder || null,
                generation_type: "page",
                website_id: websiteId,
                header_overlay_enabled: Boolean(headerOverlayEnabled),
                design_seed: generationSeed,
                brand_mode: brandMode,
            });

            try {
                window.localStorage.setItem(historyKey, JSON.stringify(sections));
            } catch (_) {}

            if (!data.blocks?.length) throw new Error("Cosmic AI did not return any sections.");
            setStage("Your page is ready.");
            setProgress(100);
            await new Promise((resolve) => window.setTimeout(resolve, 450));
            setBalance(data.credit_balance);
            onReplace(data.blocks);
            showCosmicNotification({
                title: "Page generated",
                message: `${data.blocks.length} Sparks were created for this page.`,
                tone: "success",
            });
            onClose();
        } catch (error) {
            showCosmicNotification({
                title: "Could not generate page",
                message: error.response?.data?.message || error.message || "Please try again.",
                tone: "error",
            });
        } finally {
            setGenerating(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[930] flex items-center justify-center p-4 sm:p-6">
            <button
                type="button"
                onClick={generating ? undefined : onClose}
                className="absolute inset-0 bg-black/75 backdrop-blur-sm"
                aria-label="Close Generate Page"
            />
            <section role="dialog" aria-modal="true" className="cosmic-generate-page-modal relative z-10 w-full max-w-2xl overflow-hidden rounded-3xl border border-white/10 bg-[#111116] text-white shadow-2xl shadow-black/70">
                <header className="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-[0.22em] text-violet-300">Cosmic AI</p>
                        <h2 className="mt-1 text-2xl font-semibold">✨ Generate a full page</h2>
                        <p className="mt-1 text-sm text-slate-400">Describe the page. Luna will design custom section layouts, content, and imagery from your brand and page brief.</p>
                    </div>
                    <button type="button" disabled={generating} onClick={onClose} className="rounded-xl border border-white/10 px-3 py-2 text-slate-400 hover:bg-white/5 hover:text-white disabled:opacity-40">✕</button>
                </header>

                <div className="p-6">
                    {!generating ? (
                        <>
                            <label htmlFor="cosmic-page-prompt" className="text-xs font-bold uppercase tracking-[0.16em] text-violet-300">Page brief</label>
                            <textarea
                                id="cosmic-page-prompt"
                                value={pagePrompt}
                                onChange={(event) => setPagePrompt(event.target.value.slice(0, 800))}
                                rows={7}
                                autoFocus
                                placeholder="Example: modern fitness studio about us page with trainers, programs, testimonials, and a strong contact call to action"
                                className="mt-3 w-full resize-none rounded-2xl border border-white/10 bg-black/25 px-4 py-4 text-sm leading-6 text-white placeholder:text-slate-600 focus:border-violet-400 focus:outline-none"
                            />
                            <div className="mt-4 rounded-2xl border border-white/10 bg-white/[0.025] p-3">
                                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Brand direction</p>
                                <div className="mt-2 grid grid-cols-2 gap-2">
                                    <button type="button" onClick={() => setBrandMode("keep")} className={`rounded-xl border px-3 py-2.5 text-left text-xs transition ${brandMode === "keep" ? "border-violet-400/60 bg-violet-400/10 text-white" : "border-white/10 text-slate-400 hover:bg-white/5"}`}>
                                        <span className="block font-semibold">Keep brand</span>
                                        <span className="mt-0.5 block text-[10px] opacity-70">New layout, same identity</span>
                                    </button>
                                    <button type="button" onClick={() => setBrandMode("new")} className={`rounded-xl border px-3 py-2.5 text-left text-xs transition ${brandMode === "new" ? "border-cyan-400/60 bg-cyan-400/10 text-white" : "border-white/10 text-slate-400 hover:bg-white/5"}`}>
                                        <span className="block font-semibold">New brand direction</span>
                                        <span className="mt-0.5 block text-[10px] opacity-70">Fresh art direction</span>
                                    </button>
                                </div>
                            </div>
                            <div className="mt-5 flex items-center justify-between gap-4">
                                <span className="text-xs text-slate-500">{pagePrompt.length}/800</span>
                                <div className="flex gap-2">
                                    <button type="button" onClick={onClose} className="h-11 rounded-xl border border-white/10 px-5 text-sm font-semibold text-slate-300 hover:bg-white/5">Cancel</button>
                                    <button type="button" disabled={!pagePrompt.trim()} onClick={() => setConfirmGenerate(true)} className="cosmic-primary-action h-11 rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-40">Generate Page ✨ · {creditCost} Credits</button>
                                </div>
                            </div>
                        </>
                    ) : (
                        <div className="cosmic-generate-page-progress px-2 py-4 text-center" role="status" aria-live="polite">
                            <div className="relative mx-auto h-16 w-16" aria-hidden="true">
                                <div className="cosmic-loading-spinner absolute inset-0 rounded-full" />
                                <div className="absolute inset-[3px] grid place-items-center rounded-full bg-[#17171d] text-xl text-cyan-300 shadow-lg shadow-violet-950/50">✦</div>
                            </div>
                            <p className="mt-5 text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Cosmic AI</p>
                            <h3 className="mt-2 text-2xl font-semibold tracking-tight text-white">Building your page</h3>
                            <p className="mt-3 text-sm text-slate-300">{stage}</p>
                            <div className="mt-7 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                {generationSteps.map((step, index) => {
                                    const complete = progress >= step.threshold;
                                    const previous = index === 0 ? 0 : generationSteps[index - 1].threshold;
                                    const active = !complete && progress >= previous;
                                    return (
                                        <div key={step.label} className={`flex items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-[10px] font-medium sm:text-xs ${complete ? "border-emerald-400/30 bg-emerald-400/10 text-emerald-200" : active ? "border-violet-400/45 bg-violet-400/10 text-violet-100" : "border-white/10 bg-white/[0.02] text-slate-500"}`}>
                                            <span className={`grid h-4 w-4 shrink-0 place-items-center rounded-full text-[9px] ${complete ? "bg-emerald-400 text-emerald-950" : active ? "bg-violet-400 text-white" : "bg-white/10 text-slate-400"}`}>{complete ? "✓" : index + 1}</span>
                                            <span className="leading-4">{step.label}</span>
                                        </div>
                                    );
                                })}
                            </div>
                            <div className="mt-6 h-2 overflow-hidden rounded-full bg-white/10">
                                <div className="h-full rounded-full bg-gradient-to-r from-violet-500 via-cyan-400 to-emerald-400 transition-[width] duration-200" style={{ width: `${progress}%` }} />
                            </div>
                            <div className="mt-3 flex items-center justify-between text-xs text-slate-500"><span>Generating...</span><span>{progress}%</span></div>
                        </div>
                    )}
                </div>
            </section>
            {confirmGenerate && !generating && (
                <div className="absolute inset-0 z-30 flex items-center justify-center bg-black/55 p-4 backdrop-blur-sm">
                    <section className="w-full max-w-md rounded-2xl border border-white/10 bg-[#17171b] p-6 text-white shadow-2xl">
                        <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-300">Replace page content?</p>
                        <h3 className="mt-2 text-xl font-semibold">Generate a new page</h3>
                        <p className="mt-2 text-sm leading-6 text-slate-400">
                            Generating a new page will replace the current page layout and content. Confirm before Cosmic AI starts. This generation costs {creditCost} Cosmic Credits.
                        </p>
                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" onClick={() => setConfirmGenerate(false)} className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">Cancel</button>
                            <button type="button" onClick={() => { setConfirmGenerate(false); generatePage(); }} className="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-500">Confirm & Generate · {creditCost} Credits</button>
                        </div>
                    </section>
                </div>
            )}
        </div>
    );
}
