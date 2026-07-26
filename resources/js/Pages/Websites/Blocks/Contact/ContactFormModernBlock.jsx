import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";

export const ContactFormModernSchema = {
    type: "contact_form_modern",
    title: "Contact Form",
    category: "Contact",
    purpose: "Give visitors a clear, professional way to start a conversation.",
    description: "A premium split contact section with business details and a fixed starter inquiry form.",
    tags: ["contact", "form", "inquiry", "lead", "cta"],
    defaults: {
        theme: "auto",
        eyebrow: "START A CONVERSATION",
        heading: "Let’s talk about what’s next.",
        text: "Tell us a little about your goals and our team will help you find the right next step.",
        email: "hello@example.com",
        phone: "+1 (555) 010-0200",
        address: "Available by appointment",
        submit_label: "Send inquiry",
    },
};

export function ContactFormModernBlock({ block, onUpdate, globalTheme }) {
    const data = { ...ContactFormModernSchema.defaults, ...block };
    const theme = getEffectiveTheme(block.resolvedTheme, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const isPrimarySection = block.resolvedTheme === "primary";
    const buttonStyle = isPrimarySection
        ? "bg-white text-slate-950"
        : `${primaryTheme.bg} ${primaryTheme.text}`;
    const inputSurface = isPrimarySection
        ? "border-white/20 bg-slate-950/20 placeholder:text-white/40 focus:border-white/60"
        : `${theme.border} bg-transparent placeholder:opacity-70`;
    const inputClass = `h-12 w-full rounded-xl border px-4 text-sm outline-none transition focus:ring-2 focus:ring-violet-400/60 ${inputSurface} ${theme.text}`;

    return (
        <section className={`relative overflow-hidden px-7 py-16 sm:px-10 sm:py-20 lg:px-12 lg:py-24 ${theme.bg} transition-colors duration-500`}>
            <div className={`pointer-events-none absolute -left-32 top-1/2 h-80 w-80 -translate-y-1/2 rounded-full opacity-[0.1] blur-[120px] ${primaryTheme.bg}`} />
            <div className="relative mx-auto grid max-w-7xl gap-12 lg:grid-cols-[0.88fr_1.12fr] lg:items-start lg:gap-20">
                <div className="max-w-xl pt-2">
                    <EditableText value={data.eyebrow} className={`block text-xs font-semibold uppercase tracking-[0.3em] ${theme.sub}`} onSave={(eyebrow) => onUpdate({ eyebrow })} />
                    <EditableText value={data.heading} className={`mt-5 block text-4xl font-black leading-[1.06] tracking-tight sm:text-5xl lg:text-6xl ${theme.text}`} onSave={(heading) => onUpdate({ heading })} />
                    <EditableText value={data.text} isTextArea className={`mt-5 block text-base leading-7 sm:text-lg sm:leading-8 ${theme.sub}`} onSave={(text) => onUpdate({ text })} />

                    <div className={`mt-9 space-y-4 border-t pt-7 ${theme.border}`}>
                        <div><p className={`text-xs font-semibold uppercase tracking-[0.18em] ${theme.sub}`}>Email</p><EditableText value={data.email} className={`mt-1 block text-base font-semibold ${theme.text}`} onSave={(email) => onUpdate({ email })} /></div>
                        <div><p className={`text-xs font-semibold uppercase tracking-[0.18em] ${theme.sub}`}>Phone</p><EditableText value={data.phone} className={`mt-1 block text-base font-semibold ${theme.text}`} onSave={(phone) => onUpdate({ phone })} /></div>
                        <div><p className={`text-xs font-semibold uppercase tracking-[0.18em] ${theme.sub}`}>Visit</p><EditableText value={data.address} className={`mt-1 block text-base font-semibold ${theme.text}`} onSave={(address) => onUpdate({ address })} /></div>
                    </div>
                </div>

                <form onSubmit={(event) => event.preventDefault()} className={`rounded-[2rem] border p-5 shadow-2xl sm:p-8 ${theme.card} ${theme.border}`}>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <label className={`text-sm font-semibold ${theme.text}`}>Name<input className={`mt-2 ${inputClass}`} placeholder="Your name" /></label>
                        <label className={`text-sm font-semibold ${theme.text}`}>Email<input type="email" className={`mt-2 ${inputClass}`} placeholder="you@example.com" /></label>
                    </div>
                    <label className={`mt-5 block text-sm font-semibold ${theme.text}`}>Phone <span className={theme.sub}>(optional)</span><input type="tel" className={`mt-2 ${inputClass}`} placeholder="Your phone number" /></label>
                    <label className={`mt-5 block text-sm font-semibold ${theme.text}`}>How can we help?<textarea className={`mt-2 min-h-32 w-full resize-y rounded-xl border px-4 py-3 text-sm outline-none transition focus:ring-2 focus:ring-violet-400/60 ${inputSurface} ${theme.text}`} placeholder="Tell us a little about your project" /></label>
                    <button type="submit" className={`mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-xl px-6 text-sm font-bold transition hover:opacity-90 ${buttonStyle}`}><EditableText value={data.submit_label} className="block" onSave={(submit_label) => onUpdate({ submit_label })} /></button>
                    <p className={`mt-3 text-center text-xs ${theme.sub}`}>We’ll use your details only to respond to your inquiry.</p>
                </form>
            </div>
        </section>
    );
}
