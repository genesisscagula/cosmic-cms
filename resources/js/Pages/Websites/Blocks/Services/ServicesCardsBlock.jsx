import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { RepeatableControls, RepeatableRemoveButton, cloneLast } from "../Shared/RepeatableControls";
import { sparkTw } from "../Shared/sparkTailwindRuntime";

export const ServicesCardsSchema = {

    type: "services_cards",

    title: "Services Cards",

    category: "Services",

    purpose: "Display company services using a responsive card grid.",

    description:
        "Section containing a heading, description and multiple service cards with icon, title and description.",

    tags: [
        "services",
        "cards",
        "grid",
        "features",
        "business",
        "offerings"
    ],

    defaults: {

        tagline: "WHAT WE OFFER",

        heading: "Solutions Designed To Help Your Business Grow",

        description:
            "We combine strategy, design, and technology to create digital experiences that help businesses grow with confidence.",

        cards: [

            {
                icon: "💻",
                title: "Website Development",
                desc: "Modern, fast, and scalable websites tailored for your business.",
                cta_label: "Learn More",
                cta_url: "#"
            },

            {
                icon: "🎨",
                title: "UI / UX Design",
                desc: "Beautiful user experiences focused on clarity and conversion.",
                cta_label: "Learn More",
                cta_url: "#"
            },

            {
                icon: "🚀",
                title: "Digital Strategy",
                desc: "Helping businesses grow through thoughtful digital solutions.",
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
            key: "cards",
            type: "repeater",
            label: "Service Cards",
            fields: [

                {
                    key: "icon",
                    type: "text",
                    label: "Icon (Emoji)"
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


export function ServicesCardsBlock({ block, onUpdate, globalTheme }) {

    const requestedTheme = block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme;
    const theme = getEffectiveTheme(requestedTheme, globalTheme);

    const data = {
        ...ServicesCardsSchema.defaults,
        ...block
    };

    const cardData = data.cards;

    const updateCard = (cardIndex, field, newValue) => {

        const updatedCards = [...cardData];

        updatedCards[cardIndex] = {
            ...updatedCards[cardIndex],
            [field]: newValue
        };

        onUpdate({
            cards: updatedCards
        });

    };

    return (

        <section
            className={sparkTw(block, "section", `group/repeatable-section w-full py-32 px-7 md:px-8 transition-colors duration-500 ${theme.bg}`)}
        >

            <div className={sparkTw(block, "wrapper", "max-w-7xl mx-auto")}>

                {/* Header */}

                <div className={sparkTw(block, "wrapper_2", "max-w-3xl mx-auto text-center mb-20")}>

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
                        isTextArea={true}
                        className={sparkTw(block, "text_3", `mt-6 text-lg leading-8 ${theme.sub} block`)}
                        onSave={(val) =>
                            onUpdate({
                                description: val
                            })
                        }
                    />

                </div>

                {/* Cards */}

                <div className={sparkTw(block, "wrapper_3", "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8")}>

                    {cardData.map((card, i) => (

                        <div
                            key={i}
                            className={sparkTw(block, "wrapper_4", `group ${theme.card} border ${theme.border} rounded-3xl p-8 h-full flex flex-col relative transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl`)}
                        >

                            <RepeatableRemoveButton hoverScope="card" onRemove={() => onUpdate({ cards: cardData.filter((_, idx) => idx !== i) })} disabled={cardData.length <= 1} label="Remove service card" overlay />

                            {/* Icon */}

                            <div
                                data-cosmic-card-icon="true"
                                className={sparkTw(block, "wrapper_5", `
                                    w-[var(--cosmic-card-icon-tile)]
                                    h-[var(--cosmic-card-icon-tile)]
                                    rounded-2xl
                                    border
                                    ${theme.border}
                                    cosmic-adaptive-icon-tile
                                    flex
                                    items-center
                                    justify-center
                                    text-2xl
                                    mb-6
                                `)}
                            >

                                <EditableText
                                    value={card.icon || "✨"}
                                    className={sparkTw(block, "text_4", "text-[length:var(--cosmic-card-icon-size)] leading-none")}
                                    onSave={(val) =>
                                        updateCard(i, "icon", val)
                                    }
                                />

                            </div>

                            {/* Title */}

                            <EditableText
                                value={card.title}
                                data-cosmic-type="card-title"
                                className={sparkTw(block, "text_5", `font-bold tracking-tight ${theme.text} block`)}
                                onSave={(val) =>
                                    updateCard(i, "title", val)
                                }
                            />

                            <div
                                className={sparkTw(block, "wrapper_6", `w-14 h-px mt-5 mb-5 ${theme.border} border-t`)}
                            />

                            {/* Description */}

                            <EditableText
                                value={card.desc}
                                isTextArea={true}
                                data-cosmic-type="card-body"
                                className={sparkTw(block, "text_6", `${theme.sub} block flex-grow`)}
                                onSave={(val) =>
                                    updateCard(i, "desc", val)
                                }
                            />

                            <div className={sparkTw(block, "wrapper_7", "mt-8")}>

                                <EditableButton
                                    label={card.cta_label || "Learn More"}
                                    url={card.cta_url || "#"}
                                    onSave={(label, url) => {
                                        const updatedCards = [...cardData];
                                        updatedCards[i] = { ...updatedCards[i], cta_label: label, cta_url: url };
                                        onUpdate({ cards: updatedCards });
                                    }}
                                    className={sparkTw(block, "button_2", `inline-flex items-center gap-2 text-sm font-semibold ${theme.text} opacity-80 transition-all duration-300 hover:gap-3`)}
                                />

                            </div>

                        </div>

                    ))}

                </div>

                <RepeatableControls
                    onAdd={() => onUpdate({cards: cloneLast(cardData, cardData[0] || {})})}
                    addLabel="Add service card"
                    showRemove={false}
                />

            </div>

        </section>

    );

}
