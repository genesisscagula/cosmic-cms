import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
import { colorFamilies } from "../../../../theme/colorFamilies";

export const HeroSplitEditorialSchema = {
    type: "hero_split_editorial",
    title: "Hero Split Editorial",
    category: "Hero",
    purpose: "Open a premium website with an asymmetric editorial story, proof point, and image.",
    description: "An Apple-inspired Pro hero with oversized typography, refined spacing, and layered proof.",
    tags: ["hero", "premium", "editorial", "pro", "image", "luxury"],
    defaults: {
        eyebrow: "A NEW STANDARD",
        editorial_index: "01",
        heading: "Designed to make the right first impression.",
        text: "A considered digital experience that brings your story, expertise, and next step into one confident opening statement.",
        primary_label: "Start a conversation",
        primary_url: "#",
        secondary_label: "Explore our work",
        secondary_url: "#",
        proof_value: "15+",
        proof_label: "Years of considered craft",
        image_caption: "Built with clarity, confidence, and care.",
        image_url: "/storage/cms-images/background/background-1.avif",
    },
};

export function HeroSplitEditorialBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const { requestedTheme, theme } = getHeroThemeState(block, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const data = { ...HeroSplitEditorialSchema.defaults, ...block };
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const isPrimary = requestedTheme === "primary";
    const primaryButton = isPrimary
        ? "bg-white text-slate-950"
        : `${primaryTheme.bg} ${primaryTheme.text}`;

    return (
        <section className={`relative overflow-hidden px-6 py-0 sm:px-10 lg:px-14 ${theme.bg} transition-colors duration-500`} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}>
            <div className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-current to-transparent opacity-20" />
            <div className="relative mx-auto max-w-7xl">
                <div className={`mb-10 flex items-center justify-between border-b pb-5 ${theme.border}`}>
                    <EditableText
                        value={data.eyebrow}
                        className={`text-[11px] font-bold uppercase tracking-[0.34em] ${theme.sub}`}
                        onSave={(eyebrow) => onUpdate({ eyebrow })}
                    />
                    <EditableText
                        value={data.editorial_index}
                        className={`text-xs ${theme.sub}`}
                        onSave={(editorial_index) => onUpdate({ editorial_index })}
                    />
                </div>

                <div className="grid items-end gap-10 lg:grid-cols-[minmax(0,1.05fr)_minmax(360px,0.95fr)] lg:gap-16">
                    <div className="relative z-10 lg:pb-8">
                        <EditableText
                            value={data.heading}
                            className={`block max-w-4xl text-4xl font-semibold leading-[.98] tracking-[-.045em] sm:text-5xl lg:text-6xl xl:text-7xl ${theme.text}`}
                            onSave={(heading) => onUpdate({ heading })}
                        />
                        <div className="mt-8 grid gap-7 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                            <div>
                                <EditableText
                                    value={data.text}
                                    isTextArea
                                    className={`block max-w-xl text-base leading-7 sm:text-lg sm:leading-8 ${theme.sub}`}
                                    onSave={(text) => onUpdate({ text })}
                                />
                                <div className="mt-7 flex flex-col gap-3 sm:flex-row">
                                    <EditableButton
                                        label={data.primary_label}
                                        url={data.primary_url}
                                        className={`inline-flex min-h-[50px] items-center justify-center rounded-full px-7 font-bold ${primaryButton}`}
                                        onSave={(primary_label, primary_url) => onUpdate({ primary_label, primary_url })}
                                    />
                                    <EditableButton
                                        label={data.secondary_label}
                                        url={data.secondary_url}
                                        className={`inline-flex min-h-[50px] items-center justify-center rounded-full border px-7 font-bold ${theme.border} ${theme.text}`}
                                        onSave={(secondary_label, secondary_url) => onUpdate({ secondary_label, secondary_url })}
                                    />
                                </div>
                            </div>

                            <div className={`min-w-40 border-l pl-5 ${theme.border}`}>
                                <EditableText
                                    value={data.proof_value}
                                    className={`block text-4xl font-semibold tracking-tight ${theme.text}`}
                                    onSave={(proof_value) => onUpdate({ proof_value })}
                                />
                                <EditableText
                                    value={data.proof_label}
                                    className={`mt-2 block max-w-36 text-xs font-medium leading-5 ${theme.sub}`}
                                    onSave={(proof_label) => onUpdate({ proof_label })}
                                />
                            </div>
                        </div>
                    </div>

                    <div className="relative">
                        <div className={`relative overflow-hidden rounded-[2rem] border shadow-2xl ${theme.border}`}>
                            <EditableImage
                                websiteId={websiteId}
                                blockIndex={blockIndex}
                                src={data.image_url}
                                className="aspect-[4/5] w-full object-cover sm:aspect-[5/4] lg:aspect-[4/5]"
                                onSave={(image_url) => onUpdate({ image_url })}
                            />
                            <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/55 via-transparent to-transparent" />
                            <EditableText
                                value={data.image_caption}
                                className="absolute bottom-5 left-5 right-5 block max-w-sm text-sm font-medium leading-6 text-white"
                                onSave={(image_caption) => onUpdate({ image_caption })}
                            />
                        </div>
                        <div className={`absolute -bottom-5 -left-5 hidden h-24 w-24 rounded-full border sm:block ${theme.border} ${theme.bg}`} />
                        <div className={`absolute -bottom-2 -left-2 hidden h-16 w-16 rounded-full ${primaryTheme.bg} opacity-90 sm:block`} />
                    </div>
                </div>
            </div>
        </section>
    );
}
