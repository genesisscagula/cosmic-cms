import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
import { colorFamilies } from "../../../../theme/colorFamilies";

export const HeroAiConversationSchema = {
    type: "hero_ai_conversation",
    title: "AI Conversation Hero",
    category: "Hero",
    purpose: "Introduce an AI product through a conversational interface and clear next action.",
    description: "A Pro-only AI hero with assistant messages, prompt composer, proof chips, and premium product framing.",
    tags: ["hero", "premium", "ai", "conversation", "assistant", "chat", "pro", "saas"],
    defaults: {
        eyebrow: "AI THAT WORKS WITH YOU",
        heading: "Turn a simple prompt into meaningful progress.",
        text: "Show visitors how your AI listens, responds, and helps them move from idea to action in one focused experience.",
        primary_label: "Start building",
        primary_url: "#",
        secondary_label: "See how it works",
        secondary_url: "#",
        assistant_label: "Cosmic AI",
        assistant_status: "Ready to help",
        user_message: "Create a polished campaign page for our next launch.",
        assistant_message: "I’ll shape the structure, write the first draft, and prepare a responsive page you can refine.",
        prompt_placeholder: "Ask AI to create, improve, or explain...",
        chip_one: "Strategy-aware",
        chip_two: "Editable output",
        chip_three: "Built to publish",
    },
};

export function HeroAiConversationBlock({ block, onUpdate, globalTheme }) {
    const { requestedTheme, theme } = getHeroThemeState(block, globalTheme);
    const primaryKey = typeof globalTheme === "string" ? globalTheme : (globalTheme?.primary || "midnight");
    const primaryTheme = colorFamilies[primaryKey] || colorFamilies.midnight;
    const data = { ...HeroAiConversationSchema.defaults, ...block };
    const isPrimary = requestedTheme === "primary";
    const primaryButton = isPrimary ? "bg-white text-slate-950" : `${primaryTheme.bg} ${primaryTheme.text}`;
    const userBubble = isPrimary
        ? "bg-white text-slate-950"
        : `${primaryTheme.bg} text-white`;
    const assistantBubble = isPrimary
        ? "bg-white/12 border-white/20 text-white"
        : `${theme.card} ${theme.border} ${theme.text}`;
    const composerSurface = isPrimary
        ? "bg-white/10 border-white/20 text-white"
        : `${theme.card} ${theme.border} ${theme.text}`;
    const familyGlow = primaryTheme.gradient?.glowSoft || "rgba(124,58,237,.16)";
    const isLight = requestedTheme === "white" || requestedTheme === "surface" || requestedTheme === "stone";

    return (
        <section className={`relative flex items-center overflow-hidden px-6 py-0 sm:px-10 lg:px-14 ${theme.bg}`} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}>
            <div className="pointer-events-none absolute inset-0" style={{ background: `radial-gradient(circle at 75% 25%, ${familyGlow}, transparent 34%)`, opacity: isLight ? 0.45 : 1 }} />
            <div className="relative mx-auto grid w-full max-w-7xl items-center gap-12 lg:grid-cols-[.88fr_1.12fr] lg:gap-16">
                <div>
                    <EditableText value={data.eyebrow} className={`text-xs font-bold uppercase tracking-[.28em] ${theme.sub}`} onSave={(eyebrow)=>onUpdate({eyebrow})}/>
                    <EditableText value={data.heading} className={`mt-5 block text-4xl font-semibold leading-[1] tracking-[-.045em] sm:text-5xl lg:text-6xl xl:text-7xl ${theme.text}`} onSave={(heading)=>onUpdate({heading})}/>
                    <EditableText value={data.text} isTextArea className={`mt-6 block max-w-xl text-base leading-7 sm:text-lg sm:leading-8 ${theme.sub}`} onSave={(text)=>onUpdate({text})}/>
                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                        <EditableButton label={data.primary_label} url={data.primary_url} className={`inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold ${primaryButton}`} onSave={(primary_label,primary_url)=>onUpdate({primary_label,primary_url})}/>
                        <EditableButton label={data.secondary_label} url={data.secondary_url} className={`inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold ${theme.border} ${theme.text}`} onSave={(secondary_label,secondary_url)=>onUpdate({secondary_label,secondary_url})}/>
                    </div>
                    <div className="mt-8 flex flex-wrap gap-2">
                        {["chip_one","chip_two","chip_three"].map((key)=><EditableText key={key} value={data[key]} className={`rounded-full border px-3 py-2 text-xs font-semibold ${theme.border} ${theme.surface} ${theme.sub}`} onSave={(v)=>onUpdate({[key]:v})}/>) }
                    </div>
                </div>

                <div className={`relative rounded-[2rem] border p-4 shadow-2xl sm:p-6 ${theme.border} ${theme.surface}`}>
                    <div className={`flex items-center justify-between border-b pb-4 ${theme.border}`}>
                        <div className="flex items-center gap-3">
                            <div className={`grid h-11 w-11 place-items-center rounded-2xl ${primaryTheme.bg} ${primaryTheme.text}`} aria-hidden="true">✦</div>
                            <div>
                                <EditableText value={data.assistant_label} className={`block text-sm font-bold ${theme.text}`} onSave={(assistant_label)=>onUpdate({assistant_label})}/>
                                <EditableText value={data.assistant_status} className={`mt-1 block text-xs ${theme.sub}`} onSave={(assistant_status)=>onUpdate({assistant_status})}/>
                            </div>
                        </div>
                        <span className={`rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-[.18em] ${theme.border} ${theme.bg} ${theme.sub}`}>Live preview</span>
                    </div>

                    <div className="space-y-4 py-6">
                        <div className={`ml-auto max-w-[82%] rounded-[1.4rem] rounded-br-md px-5 py-4 text-sm leading-6 ${userBubble}`}>
                            <EditableText value={data.user_message} isTextArea className="block" onSave={(user_message)=>onUpdate({user_message})}/>
                        </div>
                        <div className={`max-w-[88%] rounded-[1.4rem] rounded-bl-md border px-5 py-4 text-sm leading-6 ${assistantBubble}`}>
                            <EditableText value={data.assistant_message} isTextArea className="block" onSave={(assistant_message)=>onUpdate({assistant_message})}/>
                        </div>
                    </div>

                    <div className={`flex items-center gap-3 rounded-2xl border p-3 ${composerSurface}`}>
                        <EditableText value={data.prompt_placeholder} className={`min-w-0 flex-1 text-sm ${theme.sub}`} onSave={(prompt_placeholder)=>onUpdate({prompt_placeholder})}/>
                        <span className={`grid h-10 w-10 shrink-0 place-items-center rounded-xl ${primaryTheme.bg} ${primaryTheme.text}`}>↑</span>
                    </div>
                </div>
            </div>
        </section>
    );
}
