import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { sparkTw } from "../Shared/sparkTailwindRuntime";

export const FeatureImageLeftSchema = {

    type: "feature_image_left",

    title: "Feature Image Left",

    category: "Features",

    purpose: "Highlight a feature, service or company information with image on the left and content on the right.",

    description:
        "Two column section with image on the left and content on the right including category, heading, description and CTA button.",

    tags: [
        "feature",
        "about",
        "content",
        "image",
        "cta",
        "benefits",
        "company"
    ],

    defaults: {

        category: "CATEGORY",

        heading: "Heading Title",

        text: "Add your description here...",

        button_label: "Read More",

        button_url: "#",

        image_url: "https://picsum.photos/800/600"

    },

    fields: [

        {
            key: "category",
            type: "text",
            label: "Category"
        },

        {
            key: "heading",
            type: "text",
            label: "Heading"
        },

        {
            key: "text",
            type: "textarea",
            label: "Description"
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
        },

        {
            key: "image_url",
            type: "image",
            label: "Feature Image"
        }

    ]

};



export function FeatureImageLeftBlock({ block, blockIndex, onUpdate, globalTheme }) {

    const requestedTheme = block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme;
    const theme = getEffectiveTheme(requestedTheme, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.midnight;
    const themeGlow = primaryTheme?.gradient?.glowSoft || "rgba(96, 165, 250, 0.10)";

    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;

    // Merge defaults gikan sa schema
    const data = {
        ...FeatureImageLeftSchema.defaults,
        ...block
    };

    return (
        <section
            className={sparkTw(block, "section", `relative py-32 px-7 overflow-hidden ${theme.bg} transition-colors duration-500`)}
        >

            <div
                className={sparkTw(block, "wrapper", "absolute top-10 left-[-180px] h-[450px] w-[450px] rounded-full blur-[170px] pointer-events-none")}
                style={{ backgroundColor: themeGlow }}
            />

            <div className={sparkTw(block, "wrapper_2", "max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-20")}>

                {/* Image */}
                <div className={sparkTw(block, "wrapper_3", "w-full md:w-1/2")}>
                    <div className={sparkTw(block, "wrapper_4", "rounded-3xl overflow-hidden shadow-2xl ring-1 ring-white/10 transition-transform duration-500 hover:scale-[1.02]")}>

                        <EditableImage
                            websiteId={websiteId}
                            blockIndex={blockIndex}
                            src={data.image_url}
                            className={sparkTw(block, "image", "w-full h-auto object-cover")}
                            onSave={(url) =>
                                onUpdate({
                                    image_url: url
                                })
                            }
                        />

                    </div>
                </div>

                {/* Content */}
                <div className={sparkTw(block, "wrapper_5", "w-full md:w-1/2 space-y-8")}>

                    <EditableText
                        value={data.category}
                        className={sparkTw(block, "text", `block text-xs font-semibold uppercase tracking-[0.30em] ${theme.sub}`)}
                        onSave={(val) =>
                            onUpdate({
                                category: val
                            })
                        }
                    />

                    <EditableText
                        value={data.heading} cosmicType="h2"
                        className={sparkTw(block, "text_2", `block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${theme.text}`)}
                        onSave={(val) =>
                            onUpdate({
                                heading: val
                            })
                        }
                    />

                    <EditableText
                        value={data.text}
                        className={sparkTw(block, "text_3", `block text-lg leading-8 max-w-xl ${theme.sub}`)}
                        onSave={(val) =>
                            onUpdate({
                                text: val
                            })
                        }
                    />

                    <EditableButton
                        label={data.button_label}
                        url={data.button_url}
                        className={sparkTw(block, "button", `inline-flex items-center gap-2 font-semibold transition-all duration-300 hover:gap-3 ${theme.text}`)}
                        onSave={(label, url) =>
                            onUpdate({
                                button_label: label,
                                button_url: url
                            })
                        }
                    />

                </div>

            </div>

        </section>
    );
}
