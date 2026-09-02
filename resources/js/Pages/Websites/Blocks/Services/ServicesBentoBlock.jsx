import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

import { getSectionSurfaceThemes } from "../../../../theme/Theme";
import { RepeatableControls, RepeatableRemoveButton, cloneLast } from "../Shared/RepeatableControls";
import { sparkTw, sparkTwItem } from "../Shared/sparkTailwindRuntime";


export const ServicesBentoSchema = {

    type: "services_bento",

    title: "Services Bento",

    category: "Services",

    purpose: "Display services using modern horizontal feature rows.",

    description:
        "Modern bento-inspired service layout with icon, title and description.",

    tags: [
        "services",
        "bento",
        "modern",
        "business",
        "agency"
    ],

    defaults: {

        tagline: "OUR SERVICES",

        heading: "Solutions Built Around Your Business",

        description:
            "Helping businesses grow through strategy, design and technology.",

        services: [

            {

                icon: "⚡",

                title: "Website Development",

                desc: "Fast, scalable and SEO-friendly websites.",
                cta_label: "Learn More",
                cta_url: "#"

            },

            {

                icon: "🎨",

                title: "UI / UX Design",

                desc: "Interfaces designed for people.",
                cta_label: "Learn More",
                cta_url: "#"

            },

            {

                icon: "🚀",

                title: "Digital Strategy",

                desc: "Roadmaps that move your business forward.",
                cta_label: "Learn More",
                cta_url: "#"

            }

        ]

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
            key: "description",
            type: "textarea",
            label: "Description"
        },

        {

            key: "services",

            type: "repeater",

            label: "Services",

            fields: [

                {

                    key: "icon",

                    type: "text",

                    label: "Icon"

                },

                {

                    key: "title",

                    type: "text",

                    label: "Title"

                },

                {

                    key: "desc",

                    type: "textarea",

                    label: "Description"

                },

                {

                    key: "cta_label",

                    type: "text",

                    label: "CTA Label"

                },

                {

                    key: "cta_url",

                    type: "text",

                    label: "CTA URL"

                }

            ]

        }

    ]

};


export function ServicesBentoBlock({ block, onUpdate, globalTheme }) {

    const requestedTheme = block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme;
    const { section: theme, surface: cardTheme } = getSectionSurfaceThemes(requestedTheme, globalTheme);

    const data = {
        ...ServicesBentoSchema.defaults,
        ...block
    };

    const services = data.services;

    const updateService = (index, field, value) => {

        const updated = [...services];

        updated[index] = {
            ...updated[index],
            [field]: value
        };

        onUpdate({
            services: updated
        });

    };

    return (

        <section
            className={sparkTw(block, "section", `group/repeatable-section w-full py-32 px-7 md:px-8 transition-colors duration-500 ${theme.bg}`)}
        >

            <div className={sparkTw(block, "wrapper", "max-w-7xl mx-auto")}>

                {/* Header */}

                <div className={sparkTw(block, "wrapper_2", "max-w-3xl mb-20")}>

                    <EditableText
                        value={data.tagline}
                        className={sparkTw(block, "text", `text-xs font-semibold tracking-[0.35em] uppercase ${theme.text} opacity-70 block`)}
                        onSave={(val) => onUpdate({ tagline: val })}
                    />

                    <EditableText
                        value={data.heading} cosmicType="h2"
                        className={sparkTw(block, "text_2", `mt-5 block text-4xl font-bold tracking-tight leading-[1.05] sm:text-5xl lg:text-[3.75rem] ${theme.text}`)}
                        onSave={(val) => onUpdate({ heading: val })}
                    />

                    <EditableText
                        value={data.description}
                        isTextArea
                        className={sparkTw(block, "text_3", `mt-6 text-lg leading-8 ${theme.sub} block`)}
                        onSave={(val) =>
                            onUpdate({
                                description: val
                            })
                        }
                    />

                </div>

                {/* Bento Rows */}

                <div data-cosmic-bento-list="true" className={sparkTw(block, "wrapper_3", "flex flex-col gap-6")}>

                    {services.map((service, index) => (

                        <div
                            key={index}
                            data-cosmic-card="1"
                            data-cosmic-card-surface="light"
                            className={sparkTwItem(block, "services", index, "card", `
                                group
                                cosmic-surface-contrast
                                ${cardTheme.card}
                                border
                                ${cardTheme.border}
                                rounded-3xl
                                p-8
                                relative
                                flex
                                flex-col
                                md:flex-row
                                md:items-center
                                gap-8
                                transition-all
                                duration-300
                                hover:shadow-2xl
                                hover:-translate-y-1
                            `)}
                        >

                            <RepeatableRemoveButton hoverScope="card" onRemove={() => onUpdate({ services: services.filter((_, idx) => idx !== index) })} disabled={services.length <= 1} label="Remove service" overlay />

                            {/* Icon */}

                            <div
                                className={sparkTwItem(block, "services", index, "icon", `w-20 h-20 rounded-3xl cosmic-adaptive-icon-tile border ${cardTheme.border} flex items-center justify-center text-4xl shrink-0`)}
                            >

                                <EditableText
                                    value={service.icon}
                                    className={sparkTwItem(block, "services", index, "icon_glyph", "text-4xl")}
                                    onSave={(val) =>
                                        updateService(index, "icon", val)
                                    }
                                />

                            </div>

                            {/* Content */}

                            <div className={sparkTwItem(block, "services", index, "content", "flex-grow")}>

                                <EditableText
                                    value={service.title}
                                    className={sparkTwItem(block, "services", index, "title", `text-3xl font-bold ${cardTheme.text} block`)}
                                    onSave={(val) =>
                                        updateService(index, "title", val)
                                    }
                                />

                                <EditableText
                                    value={service.desc}
                                    isTextArea
                                    className={sparkTwItem(block, "services", index, "desc", `mt-3 text-lg leading-8 ${cardTheme.sub} block`)}
                                    onSave={(val) =>
                                        updateService(index, "desc", val)
                                    }
                                />

                            </div>

                            {/* CTA */}

                            <div className={sparkTwItem(block, "services", index, "cta_wrap", "shrink-0")}>

                                <EditableButton
                                    label={service.cta_label || "Learn More"}
                                    url={service.cta_url || "#"}
                                    onSave={(label, url) => {
                                        const updated = [...services];
                                        updated[index] = { ...updated[index], cta_label: label, cta_url: url };
                                        onUpdate({ services: updated });
                                    }}
                                    className={sparkTwItem(block, "services", index, "cta", `inline-flex items-center gap-2 text-sm font-semibold ${cardTheme.text}`)}
                                />

                            </div>

                        </div>

                    ))}

                </div>

                <RepeatableControls
                    onAdd={() => onUpdate({services: cloneLast(services, services[0] || {})})}
                    addLabel="Add service"
                    showRemove={false}
                />

            </div>

        </section>

    );

}
