import axios from "axios";
import { useEffect, useState } from "react";
import { showCosmicNotification } from "../../../Components/CosmicNotification";
import { useCreditBalance } from "../../../Components/CosmicCredits/CreditBalanceContext";

const generationSteps = [
    { label: "Understand brief", threshold: 18 },
    { label: "Plan sections", threshold: 42 },
    { label: "Create content", threshold: 72 },
    { label: "Build page", threshold: 96 },
];

export default function GeneratePageModal({
    open,
    onClose,
    onReplace,
    websiteContext = "",
    websiteId = null,
}) {
    const { setBalance } = useCreditBalance();
    const [pagePrompt, setPagePrompt] = useState("");
    const [generating, setGenerating] = useState(false);
    const [progress, setProgress] = useState(0);
    const [stage, setStage] = useState("Understanding your request...");

    useEffect(() => {
        if (!open) {
            setPagePrompt("");
            setGenerating(false);
            setProgress(0);
            setStage("Understanding your request...");
        }
    }, [open]);

    useEffect(() => {
        if (!generating) return undefined;

        const stages = [
            { at: 8, text: "Understanding your request..." },
            { at: 24, text: "Planning the right Sparks..." },
            { at: 48, text: "Choosing layouts and images..." },
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
            const contextualPrompt = [
                websiteContext || "Generate professional website content for this business.",
                `User instruction: ${prompt}`,
            ].join("\n\n");

            const { data: plan } = await axios.post("/ai/select-sections", { prompt: contextualPrompt });
            const sections = plan.sections || [];
            if (!sections.length) throw new Error("Cosmic AI could not plan this page.");

            const { data } = await axios.post("/ai/generate-content", {
                prompt: contextualPrompt,
                sections,
                image_folder: plan.image_folder || null,
                generation_type: "page",
                website_id: websiteId,
            });

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
            <section role="dialog" aria-modal="true" className="relative z-10 w-full max-w-2xl overflow-hidden rounded-3xl border border-white/10 bg-[#111116] text-white shadow-2xl shadow-black/70">
                <header className="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-[0.22em] text-violet-300">Cosmic AI</p>
                        <h2 className="mt-1 text-2xl font-semibold">✨ Generate a full page</h2>
                        <p className="mt-1 text-sm text-slate-400">Describe the page. Cosmic will choose the Sparks, layout, content, and images.</p>
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
                            <div className="mt-5 flex items-center justify-between gap-4">
                                <span className="text-xs text-slate-500">{pagePrompt.length}/800</span>
                                <div className="flex gap-2">
                                    <button type="button" onClick={onClose} className="h-11 rounded-xl border border-white/10 px-5 text-sm font-semibold text-slate-300 hover:bg-white/5">Cancel</button>
                                    <button type="button" disabled={!pagePrompt.trim()} onClick={generatePage} className="h-11 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 text-sm font-bold text-white shadow-lg transition hover:scale-[1.01] disabled:cursor-not-allowed disabled:opacity-40">Generate Page ✨</button>
                                </div>
                            </div>
                        </>
                    ) : (
                        <div className="px-2 py-4 text-center" role="status" aria-live="polite">
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
        </div>
    );
}
