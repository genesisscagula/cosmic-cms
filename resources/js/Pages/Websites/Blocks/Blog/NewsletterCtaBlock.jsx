import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";

export const NewsletterCtaSchema = {
    type: "newsletter_cta",
    title: "Newsletter CTA",
    category: "Content",
    purpose: "Invite readers to subscribe after a blog collection.",
    description: "A compact, editorial email signup call to action.",
    tags: ["newsletter", "email", "blog", "cta"],
    defaults: {
        eyebrow: "Stay in the loop",
        heading: "Get weekly insights",
        text: "Practical ideas, useful updates, and new resources delivered occasionally.",
        placeholder: "Your email address",
        button_label: "Subscribe",
        disclaimer: "No spam. Unsubscribe anytime.",
    },
};

export function NewsletterCtaBlock({ block, onUpdate }) {
    const data = { ...NewsletterCtaSchema.defaults, ...block };

    return (
        <section className="bg-[#fcfcfb] px-6 py-14 sm:px-8 lg:px-12 lg:py-20">
            <div className="mx-auto max-w-7xl">
                <div className="rounded-3xl border border-slate-200 bg-slate-950 px-6 py-10 text-white shadow-[0_24px_70px_rgba(15,23,42,0.16)] sm:px-10 lg:flex lg:items-center lg:justify-between lg:gap-12 lg:px-14 lg:py-12">
                    <div className="max-w-2xl">
                        <EditableText value={data.eyebrow} className="block text-xs font-semibold uppercase tracking-[0.28em] text-violet-200" onSave={(eyebrow) => onUpdate({ eyebrow })} />
                        <EditableText value={data.heading} className="mt-4 block text-3xl font-bold leading-[1.05] tracking-tight sm:text-4xl" onSave={(heading) => onUpdate({ heading })} />
                        <EditableText value={data.text} isTextArea className="mt-4 block max-w-xl text-base leading-7 text-slate-300" onSave={(text) => onUpdate({ text })} />
                    </div>
                    <div className="mt-8 w-full max-w-md lg:mt-0">
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <EditableText value={data.placeholder} className="flex min-h-[50px] flex-1 items-center rounded-xl border border-white/15 bg-white/10 px-4 text-sm text-slate-300" onSave={(placeholder) => onUpdate({ placeholder })} />
                            <EditableButton label={data.button_label} url="#" className="inline-flex min-h-[50px] items-center justify-center rounded-xl bg-white px-6 text-sm font-bold text-slate-950" onSave={(button_label) => onUpdate({ button_label })} />
                        </div>
                        <EditableText value={data.disclaimer} className="mt-3 block text-xs text-slate-400" onSave={(disclaimer) => onUpdate({ disclaimer })} />
                    </div>
                </div>
            </div>
        </section>
    );
}
