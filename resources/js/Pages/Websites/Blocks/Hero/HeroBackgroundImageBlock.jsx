import { usePage } from "@inertiajs/react";
import { useRef } from "react";
import { resolveMediaOverlay, effectiveMediaOverlayOpacity } from "../../../../theme/mediaOverlay";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { sparkTw } from "../Shared/sparkTailwindRuntime";




export const HeroBackgroundImageSchema = {

    type: "hero_background_image",

    title: "Hero Background Image",

    category: "Hero",

    purpose: "Displays a full-width hero section with a background image, headline, description, and call-to-action.",

    description: "Ideal for businesses that want to create a strong first impression using a high-quality background image with overlay content.",

    tags: [
        "hero",
        "background",
        "image",
        "landing",
        "banner",
        "cta"
    ],

    defaults: {

        tagline: "WELCOME TO OUR COMPANY",

        heading: "Build Beautiful Websites With Confidence",

        text: "Create modern, responsive websites using reusable blocks, AI-generated content, and powerful customization tools.",

        button_label: "Get Started",

        button_url: "#",

        image_url: "/storage/cms-images/background/background-1.avif",

        overlayOpacity: 50,

        textAlign: "center",

        height: "screen"

    },

    fields: [

        {
            type: "text",
            name: "tagline",
            label: "Tagline"
        },

        {
            type: "textarea",
            name: "heading",
            label: "Heading"
        },

        {
            type: "textarea",
            name: "text",
            label: "Description"
        },

        {
            type: "button",
            name: "button",
            label: "Button"
        },

        {
            type: "image",
            name: "image_url",
            label: "Background Image"
        },

        {
            type: "range",
            name: "overlayOpacity",
            label: "Overlay Opacity",
            min: 0,
            max: 90,
            step: 5
        },

        {
            type: "select",
            name: "textAlign",
            label: "Text Alignment",
            options: [
                "left",
                "center",
                "right"
            ]
        },

        {
            type: "select",
            name: "height",
            label: "Hero Height",
            options: [
                "medium",
                "large",
                "screen"
            ]
        }

    ]

};


export function HeroBackgroundImageBlock({
    block,
    blockIndex,
    onUpdate,
    globalTheme
}) {

    const imageRef = useRef(null);

    const { requestedTheme, theme } = getHeroThemeState(block, globalTheme);

    const data = {
        ...HeroBackgroundImageSchema.defaults,
        ...block
    };

    if (!data.image_url) {
        data.image_url =
            HeroBackgroundImageSchema.defaults.image_url;
    }



    const normalizedGlobalTheme = typeof globalTheme === "string"
        ? { primary: globalTheme }
        : (globalTheme || {});
    const primaryTheme = colorFamilies[normalizedGlobalTheme.primary]
        || colorFamilies.midnight;

    const mediaOverlay = resolveMediaOverlay(globalTheme, resolveHeroThemeRequest(block, globalTheme));
    const isLightMediaTheme = mediaOverlay.isLight;
    // Light sections keep a strong white wash. Colored/dark sections blend the
    // active theme background with slate so the image stays branded without a
    // heavy monochrome filter.
    const overlayColor = mediaOverlay.overlayColor;
    const configuredOverlayOpacity = Math.max(0, Math.min(100, Number(data.overlayOpacity) || 50));
    // Hero Background Image needs a stronger contrast floor than the generic
    // media overlay. Bright photos otherwise make the theme blend look absent.
    // Keep light themes as a strong white wash; colored/dark themes retain the
    // dynamic primary+slate blend but in a clearly visible premium range.
    const effectiveOverlayOpacity = isLightMediaTheme
        ? Math.max(96, configuredOverlayOpacity)
        : Math.max(46, Math.min(68, Math.round(configuredOverlayOpacity * 0.90)));

    const buttonStyle = isLightMediaTheme
        ? { bg: primaryTheme.bg, text: "!text-white" }
        : { bg: "bg-white", text: "!text-slate-950" };

    const mediaStyle = isLightMediaTheme
        ? {
            tagline: "!text-slate-700",
            heading: "!text-slate-950",
            body: "!text-slate-700",
        }
        : {
            tagline: "!text-white/85",
            heading: "!text-white",
            body: "!text-white/85",
        };

    const heroHeight = {
        medium: "min-h-[500px]",
        large: "min-h-[650px]",
        // Compatibility for pages generated before the height enum was
        // normalized. The export compiler maps the same legacy value here.
        xl: "min-h-[650px]",
        screen: ""
    };

    const alignment = {
        left: "items-start text-left",
        center: "items-center text-center",
        right: "items-end text-right"
    };

    const { props } = usePage();

    const websiteId =
        props.page?.website_id ||
        props.website?.id;

    const handleSectionImageEdit = (event) => {
        if (event.target.closest("button, a, input, textarea, select, label, [contenteditable='true'], [role='button'], [data-cosmic-edit-control]")) {
            return;
        }

        imageRef.current?.openEditor();
    };


    return (

        <section data-cosmic-editable-hero-media="true"
            data-cosmic-media-banner="true"
            className={sparkTw(block, "section", `
                relative
                overflow-hidden
                flex
                cursor-pointer
                items-center

                ${heroHeight[data.height]}
            `)}
            onClick={handleSectionImageEdit}
            style={data.height === "screen" ? {minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"} : undefined}
        >

            {/* Background Image */}

           <EditableImage
            ref={imageRef}
            websiteId={websiteId}
            blockIndex={blockIndex}
            src={data.image_url}
            showOverlay={false}
            isBackground
            className={sparkTw(block, "b10_1", `
                absolute
                inset-0
                w-full
                h-full
                overflow-hidden
                z-20
            `)}
            onSave={(value) =>
                onUpdate({
                    image_url: value
                })
            }
        />

            {/* Overlay */}

            <div
                className={sparkTw(block, "wrapper", `
                    absolute
                    inset-0
                    z-[25]
                    pointer-events-none
                `)}
                style={{
                    backgroundColor: overlayColor,
                    opacity: effectiveOverlayOpacity / 100
                }}
            />

            {/* Content */}

            <div
            className={sparkTw(block, "wrapper_2", `
                relative
                z-[25]
                w-full
                h-full
                min-h-inherit
                max-w-7xl
                mx-auto
                px-6
                sm:px-[8%]
                py-0
                flex
                flex-col
                justify-center
                ${alignment[data.textAlign]}
            `)}
        >

                <EditableText
                    value={data.tagline}
                    className={sparkTw(block, "text", `
                        text-sm
                        uppercase
                        tracking-[0.35em]
                        font-semibold
                        ${mediaStyle.tagline}
                    `)}
                    onSave={(val) =>
                        onUpdate({
                            tagline: val
                        })
                    }
                />

                <EditableText
                    value={data.heading} cosmicType="h1"
                    className={sparkTw(block, "text_2", `
                        mt-6
                        text-4xl
                        sm:text-5xl
                        lg:text-6xl
                        xl:text-7xl
                        font-bold
                        leading-tight
                        break-words
                        ${mediaStyle.heading}
                    `)}
                    onSave={(val) =>
                        onUpdate({
                            heading: val
                        })
                    }
                />

                <EditableText
                    value={data.text}
                    isTextArea={true}
                    className={sparkTw(block, "text_3", `
                        mt-6
                        sm:mt-8
                        max-w-2xl
                        text-base
                        sm:text-xl
                        leading-7
                        sm:leading-8
                        ${mediaStyle.body}
                    `)}
                    onSave={(val) =>
                        onUpdate({
                            text: val
                        })
                    }
                />

                <div className={sparkTw(block, "wrapper_3", "mt-8 sm:mt-12")}>

                    <EditableButton
                        label={data.button_label}
                        url={data.button_url}
                        className={sparkTw(block, "button", `
                            inline-flex
                            w-full
                            sm:w-auto
                            items-center
                            justify-center
                            min-h-[52px]
                            px-8
                            rounded-full
                            font-bold
                            transition-all
                            duration-200
                            ${buttonStyle.bg}
                            ${buttonStyle.text}
                        `)}
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
