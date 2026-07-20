import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";


export const HeroCenteredCTASchema = {

    type: "hero_centered_cta",

    title: "Hero Centered CTA",

    category: "Hero",

    purpose: "Display a centered call-to-action hero section with heading, description and primary button.",

    description:
        "Centered hero section containing a tagline, heading, supporting text and a call-to-action button.",

    tags: [
        "hero",
        "cta",
        "landing",
        "banner",
        "marketing",
        "call-to-action"
    ],

    defaults: {

        tagline: "LOREM IPSUM DOLOR",

        heading: "Build Something Amazing",

        subheading:
            "Create beautiful websites faster with a modern editing experience.",

        text:
            "Create beautiful websites faster with a modern editing experience.",

        button_label: "Get Started",

        button_url: "#"

    },

    fields: [

        {
            key: "tagline",
            type: "text",
            label: "Tagline"
        },

        {
            key: "heading",
            type: "text",
            label: "Heading"
        },

        {
            key: "subheading",
            type: "textarea",
            label: "Subheading"
        },

        {
            key: "button_label",
            type: "text",
            label: "Button Label"
        },

        {
            key: "button_url",
            type: "url",
            label: "Button URL"
        }

    ]

};


export function HeroCenteredCTA({ block, onUpdate, globalTheme }) {

    const theme = getEffectiveTheme(
        block.resolvedTheme,
        globalTheme
    );

    const data = {
        ...HeroCenteredCTASchema.defaults,
        ...block
    };

    return (

        <section
            className={`w-full py-24 px-7 md:px-8 text-center ${theme.bg} relative overflow-hidden border-b ${theme.border} transition-colors duration-500`}
        >

            <div className="absolute
                inset-0
                overflow-hidden">

                <div
                    className="
                    absolute
                    top-0
                    left-0
                    w-80
                    h-80
                    rounded-full
                    bg-primary-400/20
                    blur-[130px]"
                />

                <div
                    className="
                    absolute
                    bottom-0
                    right-0
                    w-80
                    h-80
                    rounded-full
                    bg-primary-300/20
                    blur-[130px]"
                />

            </div>

            <div className="max-w-4xl mx-auto space-y-6 relative z-10 flex flex-col items-center">

                <EditableText
                    value={data.tagline}
                    className={`text-xs font-bold ${theme.text} tracking-widest uppercase block opacity-80`}
                    onSave={(val) =>
                        onUpdate({
                            tagline: val
                        })
                    }
                />

                <EditableText
                    value={data.heading}
                    className={`text-4xl md:text-5xl font-extrabold ${theme.text} leading-tight block`}
                    onSave={(val) =>
                        onUpdate({
                            heading: val
                        })
                    }
                />

                <EditableText
                    value={data.subheading}
                    isTextArea={true}
                    className={`text-base md:text-lg ${theme.sub} max-w-2xl mx-auto leading-relaxed block`}
                    onSave={(val) =>
                        onUpdate({
                            subheading: val,
                            text: val
                        })
                    }
                />

                <EditableButton
                    label={data.button_label}
                    url={data.button_url}
                    className={`inline-block ${theme.text} ${theme.bg} border ${theme.border} px-8 py-3 rounded-full font-bold shadow-lg hover:opacity-90 transition`}
                    onSave={(label, url) =>
                        onUpdate({
                            button_label: label,
                            button_url: url
                        })
                    }
                />

            </div>

        </section>

    );

}