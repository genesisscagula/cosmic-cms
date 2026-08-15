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
            "Create a polished website with reusable sections and complete editorial control.",

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

    const requestedTheme = block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme;
    const theme = getEffectiveTheme(requestedTheme, globalTheme);

    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;

    // Merge defaults gikan sa schema
    const data = {
        ...HeroHeadlineSchema.defaults,
        ...block
    };

    // Primary CTA always follows the website accent color
    const primaryTheme = colorFamilies[globalTheme.primary];

    const isPrimarySection = block.resolvedTheme === "primary";

    const buttonStyle = isPrimarySection
    ? {
        bg: "bg-white",
        text: "text-slate-950"
    }
    : {
        bg: primaryTheme.bg,
        text: primaryTheme.text
    };

    return (
        <section
            className={`relative w-full px-6 py-20 sm:px-[8%] sm:py-24 ${theme.bg} overflow-hidden transition-colors duration-500`}
        >

            {/* Background */}
            <div className="absolute top-[-120px] right-[-120px] w-[650px] h-[650px] rounded-full bg-gradient-to-br from-white/25 via-white/10 to-transparent blur-[180px]" />

            <div className="relative z-10 max-w-4xl">

                <EditableText
                    value={data.subtitle}
                    className={`font-bold tracking-widest uppercase text-sm block ${theme.sub}`}
                    onSave={(val) => onUpdate({ subtitle: val })}
                />

                <h1 className="mt-6 text-4xl font-extrabold leading-[1.1] sm:text-5xl md:text-8xl">
                    <EditableText
                        value={data.heading}
                        className={`block ${theme.text}`}
                        onSave={(val) => onUpdate({ heading: val })}
                    />
                </h1>

                <div className="mt-6 max-w-2xl text-base sm:mt-8 sm:text-xl">
                    <EditableText
                        value={data.text}
                        className={`block ${theme.sub}`}
                        onSave={(val) => onUpdate({ text: val })}
                    />
                </div>

                <div className="mt-8 flex flex-col items-stretch gap-3 sm:mt-12 sm:flex-row sm:items-center sm:gap-4">

                    {/* Primary CTA */}
                    <EditableButton
                        label={data.btn1_label}
                        url={data.btn1_url}
                        className={`
                            inline-flex w-full items-center justify-center sm:w-auto
                            min-h-[52px] px-8
                            rounded-full
                            font-bold
                            transition-all duration-200
                            ${buttonStyle.bg}
                            ${buttonStyle.text}
                        `}
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
                        className={`
                            inline-flex w-full items-center justify-center sm:w-auto
                            min-h-[52px] px-8
                            rounded-full
                            border
                            font-bold
                            transition-all duration-200
                            ${theme.border || "border-slate-700"}
                            ${theme.text}
                        `}
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
