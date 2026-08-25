import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { getHeroThemeState } from "../../../../theme/heroTheme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { sparkTw } from "../Shared/sparkTailwindRuntime";


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

    const heroState = getHeroThemeState(block, globalTheme);
    const { theme, isPrimary } = heroState;

    const data = {
        ...HeroCenteredCTASchema.defaults,
        ...block
    };

    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const primaryButtonStyle = isPrimary
        ? "bg-white text-slate-950"
        : `${primaryTheme.bg} ${primaryTheme.text}`;

    return (

        <section
            data-cosmic-hero-theme={heroState.requestedTheme}
            className={sparkTw(block, "section", `relative flex min-h-[500px] w-full items-center overflow-hidden border-b px-7 py-20 text-center sm:min-h-[560px] sm:px-10 sm:py-24 lg:min-h-[620px] lg:px-12 lg:py-28 ${theme.bg} ${theme.border} transition-colors duration-500`)}
        >

            <div className={sparkTw(block, "b10_1", `absolute
                inset-0
                overflow-hidden`)}>

                <div className={sparkTw(block, "wrapper", `absolute -left-32 -top-32 h-[30rem] w-[30rem] rounded-full opacity-[0.13] blur-[140px] ${primaryTheme.bg}`)} />

                <div className={sparkTw(block, "wrapper_2", `absolute -bottom-40 -right-32 h-[32rem] w-[32rem] rounded-full opacity-[0.1] blur-[150px] ${primaryTheme.bg}`)} />

                <div className={sparkTw(block, "wrapper_3", `absolute inset-x-[12%] top-0 border-t ${theme.border} opacity-70`)} />

            </div>

            <div className={sparkTw(block, "wrapper_4", "relative z-10 mx-auto flex max-w-5xl flex-col items-center space-y-7")}>

                <EditableText
                    value={data.tagline}
                    className={sparkTw(block, "text", `block text-xs font-semibold uppercase tracking-[0.32em] ${theme.sub}`)}
                    onSave={(val) =>
                        onUpdate({
                            tagline: val
                        })
                    }
                />

                <EditableText
                    value={data.heading} cosmicType="h1"
                    className={sparkTw(block, "text_2", `block max-w-5xl text-4xl font-bold leading-[1.02] tracking-tight sm:text-5xl lg:text-6xl xl:text-7xl ${theme.text}`)}
                    onSave={(val) =>
                        onUpdate({
                            heading: val
                        })
                    }
                />

                <EditableText
                    value={data.subheading}
                    isTextArea={true}
                    className={sparkTw(block, "text_3", `mx-auto block max-w-3xl text-base leading-7 sm:text-lg sm:leading-8 ${theme.sub}`)}
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
                    className={sparkTw(block, "button", `inline-flex min-h-[52px] items-center justify-center rounded-full px-8 font-bold shadow-lg transition hover:opacity-90 ${primaryButtonStyle}`)}
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
