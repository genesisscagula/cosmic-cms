import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";

export const NewsletterCtaSchema = {
    type: "newsletter_cta",
    title: "Newsletter CTA",
    category: "Content",
    sparkGroup: "Blog-News-Letter",
    freeSparkCount: 3,
    purpose: "Invite readers to subscribe after a blog collection.",
    description: "A compact, editorial email signup call to action.",
    tags: ["newsletter", "email", "blog", "cta"],
    defaults: {
        layout_variant: "newsletter-01",
        eyebrow: "Stay in the loop",
        heading: "Get weekly insights",
        text: "Practical ideas, useful updates, and new resources delivered occasionally.",
        placeholder: "Your email address",
        button_label: "Subscribe",
        disclaimer: "No spam. Unsubscribe anytime.",
    },
};

export function NewsletterCtaBlock({ block, onUpdate, globalTheme }) {
    const data = { ...NewsletterCtaSchema.defaults, ...block };
    const selectedTheme = block.theme && block.theme !== "auto" ? block.theme : (block.resolvedTheme || "primary");
    const theme = getEffectiveTheme(selectedTheme, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const isPrimary = selectedTheme === "primary";
    const buttonClass = isPrimary ? `${theme.card} ${theme.text}` : `${primaryTheme.bg} ${primaryTheme.text}`;
    const variant = data.layout_variant || "newsletter-01";
    const centered = variant === "newsletter-02";
    const boxed = variant === "newsletter-03";

    return (
        <section className={`${theme.bg} px-6 py-14 sm:px-8 lg:px-12 lg:py-20`}>
            <div className="mx-auto max-w-7xl">
                <div className={`${theme.bg} ${theme.text} ${theme.border} rounded-3xl border px-6 py-10 shadow-[0_24px_70px_rgba(15,23,42,0.16)] sm:px-10 lg:px-14 lg:py-12 ${centered ? "text-center" : boxed ? "grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-center" : "lg:flex lg:items-center lg:justify-between lg:gap-12"}`}>
                    <div className={centered ? "mx-auto max-w-2xl" : "max-w-2xl"}>
                        <EditableText value={data.eyebrow} className={`block text-xs font-semibold uppercase tracking-[0.28em] ${theme.sub}`} onSave={(eyebrow) => onUpdate({ eyebrow })} />
                        <EditableText value={data.heading} className="mt-4 block text-3xl font-bold leading-[1.05] tracking-tight sm:text-4xl" onSave={(heading) => onUpdate({ heading })} />
                        <EditableText value={data.text} isTextArea className={`mt-4 block max-w-xl text-base leading-7 ${theme.sub} ${centered ? "mx-auto" : ""}`} onSave={(text) => onUpdate({ text })} />
                    </div>
                    <div className={`${centered ? "mx-auto mt-8" : boxed ? "" : "mt-8 lg:mt-0"} w-full max-w-md`}>
                        <div className={`flex gap-3 ${centered || !boxed ? "flex-col sm:flex-row" : "flex-col"}`}>
                            <EditableText value={data.placeholder} className={`flex min-h-[50px] flex-1 items-center rounded-xl border px-4 text-sm ${theme.border} ${theme.card} ${theme.sub}`} onSave={(placeholder) => onUpdate({ placeholder })} />
                            <EditableButton label={data.button_label} url="#" className={`inline-flex min-h-[50px] items-center justify-center rounded-xl px-6 text-sm font-bold ${buttonClass}`} onSave={(button_label) => onUpdate({ button_label })} />
                        </div>
                        <EditableText value={data.disclaimer} className={`mt-3 block text-xs ${theme.sub}`} onSave={(disclaimer) => onUpdate({ disclaimer })} />
                    </div>
                </div>
            </div>
        </section>
    );
}
