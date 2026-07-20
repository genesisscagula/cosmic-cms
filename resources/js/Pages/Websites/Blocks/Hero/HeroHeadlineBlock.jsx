import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";


export const HeroHeadlineSchema = {

    type: "hero_headline",

    title: "Hero Headline",

    category: "Hero",

    purpose: "Landing page hero",

    description:
        "Large hero section with subtitle, heading, description and two CTA buttons.",

    tags: [
        "hero",
        "landing",
        "headline",
        "cta"
    ],

    defaults: {

        subtitle: "WELCOME TO THE FUTURE",

        heading: "Build Better Digital Reality.",

        text:
            "Focus sa logic...",

        btn1_label: "Get Started",

        btn1_url: "#",

        btn2_label: "View Docs",

        btn2_url: "#"
    },

    fields: [

        {
            key:"subtitle",
            type:"text"
        },

        {
            key:"heading",
            type:"text"
        },

        {
            key:"text",
            type:"textarea"
        },

        {
            key:"btn1_label",
            type:"text"
        },

        {
            key:"btn1_url",
            type:"url"
        },

        {
            key:"btn2_label",
            type:"text"
        },

        {
            key:"btn2_url",
            type:"url"
        }

    ]

};

export function HeroHeadlineBlock({ block, blockIndex, onUpdate, globalTheme }) {

    const theme = getEffectiveTheme(
        block.resolvedTheme,
        globalTheme
    );

    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;

    // Merge defaults gikan sa schema
    const data = {
        ...HeroHeadlineSchema.defaults,
        ...block
    };

    // Primary CTA always follows the website accent color
    const primaryTheme = colorFamilies[globalTheme.primary];

    const buttonStyle = {
        bg: primaryTheme?.bg || "bg-indigo-600",
        text: primaryTheme?.text || "text-white"
    };

    return (
        <section
            className={`relative w-full py-24 px-[8%] ${theme.bg} overflow-hidden transition-colors duration-500`}
        >

            {/* Background */}
            <div className="absolute top-[-120px] right-[-120px] w-[650px] h-[650px] rounded-full bg-gradient-to-br from-white/25 via-white/10 to-transparent blur-[180px]" />

            <div className="relative z-10 max-w-4xl">

                <EditableText
                    value={data.subtitle}
                    className={`font-bold tracking-widest uppercase text-sm block ${theme.sub}`}
                    onSave={(val) => onUpdate({ subtitle: val })}
                />

                <h1 className="text-6xl md:text-8xl font-extrabold mt-6 leading-[1.1]">
                    <EditableText
                        value={data.heading}
                        className={`block ${theme.text}`}
                        onSave={(val) => onUpdate({ heading: val })}
                    />
                </h1>

                <div className="mt-8 text-xl max-w-2xl">
                    <EditableText
                        value={data.text}
                        className={`block ${theme.sub}`}
                        onSave={(val) => onUpdate({ text: val })}
                    />
                </div>

                <div className="mt-12 flex gap-4">

                    {/* Primary CTA */}

                    <EditableButton
                        label={data.btn1_label}
                        url={data.btn1_url}
                        className={`px-8 py-4 rounded-full font-bold transition !opacity-100 ${buttonStyle.bg} ${buttonStyle.text}`}
                        onSave={(label, url) =>
                            onUpdate({
                                btn1_label: label,
                                btn1_url: url
                            })
                        }
                    />

                    {/* Secondary CTA */}

                    <EditableButton
                        label={data.btn2_label}
                        url={data.btn2_url}
                        className={`border px-8 py-4 rounded-full font-bold transition ${theme.border || "border-slate-700"} ${theme.text}`}
                        onSave={(label, url) =>
                            onUpdate({
                                btn2_label: label,
                                btn2_url: url
                            })
                        }
                    />

                </div>

            </div>

        </section>
    );
}