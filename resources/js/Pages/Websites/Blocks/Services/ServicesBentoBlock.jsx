import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { RepeatableControls, RepeatableRemoveButton, cloneLast } from "../Shared/RepeatableControls";


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
    const theme = getEffectiveTheme(requestedTheme, globalTheme);

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
            className={`w-full py-32 px-7 md:px-8 transition-colors duration-500 ${theme.bg}`}
        >

            <div className="max-w-7xl mx-auto">

                {/* Header */}

                <div className="max-w-3xl mb-20">

                    <EditableText
                        value={data.tagline}
                        className={`text-xs font-semibold tracking-[0.35em] uppercase ${theme.text} opacity-70 block`}
                        onSave={(val) => onUpdate({ tagline: val })}
                    />

                    <EditableText
                        value={data.heading}
                        className={`mt-5 block text-4xl font-bold tracking-tight leading-[1.05] sm:text-5xl lg:text-[3.75rem] ${theme.text}`}
                        onSave={(val) => onUpdate({ heading: val })}
                    />

                    <EditableText
                        value={data.description}
                        isTextArea
                        className={`mt-6 text-lg leading-8 ${theme.sub} block`}
                        onSave={(val) =>
                            onUpdate({
                                description: val
                            })
                        }
                    />

                </div>

                {/* Bento Rows */}

                <div className="space-y-6">

                    {services.map((service, index) => (

                        <div
                            key={index}
                            className={`
                                group
                                ${theme.card}
                                border
                                ${theme.border}
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
                            `}
                        >

                            <RepeatableRemoveButton onRemove={() => onUpdate({ services: services.filter((_, idx) => idx !== index) })} disabled={services.length <= 1} label="Remove service" overlay />

                            {/* Icon */}

                            <div
                                className="
                                    w-20
                                    h-20
                                    rounded-3xl
                                    cosmic-adaptive-icon-tile
                                    border
                                    flex
                                    items-center
                                    justify-center
                                    text-4xl
                                    shrink-0
                                "
                            >

                                <EditableText
                                    value={service.icon}
                                    className="text-4xl"
                                    onSave={(val) =>
                                        updateService(index, "icon", val)
                                    }
                                />

                            </div>

                            {/* Content */}

                            <div className="flex-grow">

                                <EditableText
                                    value={service.title}
                                    className={`text-3xl font-bold ${theme.text} block`}
                                    onSave={(val) =>
                                        updateService(index, "title", val)
                                    }
                                />

                                <EditableText
                                    value={service.desc}
                                    isTextArea
                                    className={`mt-3 text-lg leading-8 ${theme.sub} block`}
                                    onSave={(val) =>
                                        updateService(index, "desc", val)
                                    }
                                />

                            </div>

                            {/* CTA */}

                            <div className="shrink-0">

                                <EditableButton
                                    label={service.cta_label || "Learn More"}
                                    url={service.cta_url || "#"}
                                    onSave={(label, url) => {
                                        const updated = [...services];
                                        updated[index] = { ...updated[index], cta_label: label, cta_url: url };
                                        onUpdate({ services: updated });
                                    }}
                                    className={`inline-flex items-center gap-2 text-sm font-semibold ${theme.text}`}
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
