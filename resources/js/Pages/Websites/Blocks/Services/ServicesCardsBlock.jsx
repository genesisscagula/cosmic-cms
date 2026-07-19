import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";

export const ServicesCardsSchema = {

    type: "services_cards",

    title: "Services Cards",

    category: "Services",

    purpose: "Display company services using a responsive card grid.",

    description:
        "Section containing a heading, description and multiple service cards with title and description.",

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
                title: "Website Development",
                desc: "Modern, fast, and scalable websites tailored for your business."
            },

            {
                title: "UI / UX Design",
                desc: "Beautiful user experiences focused on clarity and conversion."
            },

            {
                title: "Digital Strategy",
                desc: "Helping businesses grow through thoughtful digital solutions."
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
                    key: "title",
                    type: "text",
                    label: "Title"
                },

                {
                    key: "desc",
                    type: "textarea",
                    label: "Description"
                }

            ]
        }

    ]

};


export function ServicesCardsBlock({ block, onUpdate, globalTheme }) {

    const theme = getEffectiveTheme(block.theme, globalTheme);

    const data = {
        ...ServicesCardsSchema.defaults,
        ...block
    };

    const cardData = data.cards;

    const icons = ['⚡', '💻', '🚀', '📈', '🛡️', '💡', '🎯', '✨'];

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
            className={`w-full py-32 px-7 md:px-8 transition-colors duration-500 ${theme.bg}`}
        >

            <div className="max-w-7xl mx-auto">

                {/* Header */}

                <div className="max-w-3xl mx-auto text-center mb-20">

                    <EditableText
                        value={data.tagline}
                        className={`text-xs font-semibold tracking-[0.35em] uppercase ${theme.text} opacity-70 block`}
                        onSave={(val) => onUpdate({ tagline: val })}
                    />

                    <EditableText
                        value={data.heading}
                        className={`mt-5 text-5xl md:text-6xl font-bold tracking-tight leading-tight ${theme.text} block`}
                        onSave={(val) => onUpdate({ heading: val })}
                    />

                    <EditableText
                        value={data.description}
                        isTextArea={true}
                        className={`mt-6 text-lg leading-8 ${theme.sub} block`}
                        onSave={(val) =>
                            onUpdate({
                                description: val
                            })
                        }
                    />

                </div>

                {/* Cards */}

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

                    {cardData.map((card, i) => (

                        <div
                            key={i}
                            className={`${theme.card} border ${theme.border} rounded-3xl p-8 h-full flex flex-col transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl`}
                        >

                            <div
                                className={`
                                    w-16
                                    h-16
                                    rounded-2xl
                                    border
                                    ${theme.border}
                                    bg-white/5
                                    flex
                                    items-center
                                    justify-center
                                    text-2xl
                                    mb-6
                                `}
                            >
                                {icons[i % icons.length]}
                            </div>

                            <EditableText
                                value={card.title}
                                className={`text-2xl font-bold tracking-tight ${theme.text} block`}
                                onSave={(val) =>
                                    updateCard(i, "title", val)
                                }
                            />

                            <div
                                className={`w-14 h-px mt-5 mb-5 ${theme.border} border-t`}
                            />

                            <EditableText
                                value={card.desc}
                                isTextArea={true}
                                className={`text-base leading-8 ${theme.sub} block flex-grow`}
                                onSave={(val) =>
                                    updateCard(i, "desc", val)
                                }
                            />

                            <div className="mt-8">

                                <span
                                    className={`inline-flex items-center gap-2 text-sm font-semibold ${theme.text} opacity-80`}
                                >
                                    Learn More

                                    <span className="transition-transform duration-300 group-hover:translate-x-1">
                                        →
                                    </span>

                                </span>

                            </div>

                        </div>

                    ))}

                </div>

            </div>

        </section>

    );

}