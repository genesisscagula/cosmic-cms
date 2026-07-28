import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";

export const BlogMiniHeroSchema = {
    type: "blog_mini_hero",
    title: "Blog Mini Hero",
    category: "Content",
    purpose: "Introduce a posts and updates page with a concise editorial heading.",
    defaults: {
        eyebrow: "Latest insights",
        heading: "Ideas for building a better business",
        text: "Practical notes, useful perspectives, and updates from our team.",
    },
};

export function BlogMiniHeroBlock({ block, onUpdate, globalTheme }) {
    const data = { ...BlogMiniHeroSchema.defaults, ...block };
    const selectedTheme = block.resolvedTheme || block.theme || "primary";
    const theme = getEffectiveTheme(selectedTheme, globalTheme);

    return (
        <section className={`relative overflow-hidden border-b px-6 py-16 sm:px-8 sm:py-20 lg:px-12 lg:py-24 ${theme.bg} ${theme.border} transition-colors duration-500`}>
            <div className={`pointer-events-none absolute -right-24 -top-28 h-72 w-72 rounded-full opacity-10 blur-3xl ${theme.card}`} />
            <div className="relative mx-auto max-w-7xl">
                <div className="max-w-3xl">
                    <EditableText
                        value={data.eyebrow}
                        className={`block text-xs font-semibold uppercase tracking-[0.28em] ${theme.sub}`}
                        onSave={(eyebrow) => onUpdate({ eyebrow })}
                    />
                    <EditableText
                        value={data.heading}
                        className={`mt-4 block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${theme.text}`}
                        onSave={(heading) => onUpdate({ heading })}
                    />
                    <EditableText
                        value={data.text}
                        isTextArea
                        className={`mt-5 block max-w-2xl text-base leading-7 sm:text-lg ${theme.sub}`}
                        onSave={(text) => onUpdate({ text })}
                    />
                </div>
            </div>
        </section>
    );
}
