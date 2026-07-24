import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";

export const TestimonialsCarouselSchema = {

    type: "testimonials_carousel",

    title: "Testimonials Carousel",

    category: "Testimonials",

    purpose:
        "Display customer testimonials in a premium carousel layout.",

    description:
        "Showcase customer reviews, ratings and social proof to build trust.",

    tags: [
        "testimonials",
        "reviews",
        "social proof",
        "customers",
        "carousel",
        "ratings"
    ],

    defaults: {

        tagline: "CLIENT TESTIMONIALS",

        heading: "Trusted By Businesses Around The World",

        text:
            "See what our satisfied clients say about working with our team.",

        testimonials: [

		    {
		        avatar: "/storage/cms-images/avatars/avatar-1.jpg",
		        name: "John Smith",
		        company: "ABC Construction",
		        quote: "Professional from start to finish. The entire process exceeded our expectations.",
		        rating: 5
		    },

		    {
		        avatar: "/storage/cms-images/avatars/avatar-2.jpg",
		        name: "Sarah Johnson",
		        company: "Modern Interiors",
		        quote: "Outstanding quality and communication. Highly recommended.",
		        rating: 5
		    },

		    {
		        avatar: "/storage/cms-images/avatars/avatar-3.jpg",
		        name: "Michael Brown",
		        company: "Prime Builders",
		        quote: "Exceptional workmanship and attention to every detail.",
		        rating: 5
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
            key: "text",
            type: "textarea",
            label: "Description"
        },

        {
            key: "testimonials",
            type: "repeater",
            label: "Testimonials",

            fields: [

                {
                    key: "avatar",
                    type: "image",
                    label: "Avatar"
                },

                {
                    key: "name",
                    type: "text",
                    label: "Name"
                },

                {
                    key: "company",
                    type: "text",
                    label: "Company"
                },

                {
                    key: "quote",
                    type: "textarea",
                    label: "Quote"
                },

                {
                    key: "rating",
                    type: "number",
                    label: "Rating"
                }

            ]

        }

    ]

};



export function TestimonialsCarouselBlock({
    block,
    blockIndex,
    onUpdate,
    globalTheme
}) {

    const theme = getEffectiveTheme(
        block.resolvedTheme,
        globalTheme
    );

    const data = {
        ...TestimonialsCarouselSchema.defaults,
        ...block
    };

    const { website } = usePage().props;

	const websiteId = website?.id;

    const updateTestimonial = (index, field, value) => {

        const testimonials = [...data.testimonials];

        testimonials[index] = {
            ...testimonials[index],
            [field]: value
        };

        onUpdate({
            testimonials
        });

    };

    return (

        <section
            className={`relative py-32 px-7 overflow-hidden ${theme.bg} transition-colors duration-500`}
        >

            <div className="max-w-7xl mx-auto">

                {/* Header */}

                <div className="text-center max-w-3xl mx-auto mb-20">

                    <EditableText
                        value={data.tagline}
                        className={`block text-xs font-semibold uppercase tracking-[0.35em] ${theme.sub}`}
                        onSave={(val) =>
                            onUpdate({
                                tagline: val
                            })
                        }
                    />

                    <EditableText
                        value={data.heading}
                        className={`block mt-5 text-5xl md:text-6xl font-bold leading-tight tracking-tight ${theme.text}`}
                        onSave={(val) =>
                            onUpdate({
                                heading: val
                            })
                        }
                    />

                    <EditableText
                        value={data.text}
                        isTextArea={true}
                        className={`block mt-6 text-lg leading-8 ${theme.sub}`}
                        onSave={(val) =>
                            onUpdate({
                                text: val
                            })
                        }
                    />

                </div>

                {/* Testimonials */}

                <div className="grid md:grid-cols-3 gap-8">

                    {data.testimonials.map((item, index) => (

                        <div
                            key={index}
                            className={`
                                ${theme.card}
                                border
                                ${theme.border}
                                rounded-3xl
                                p-7

                                transition-all
                                duration-300
                                hover:-translate-y-2
                                hover:shadow-2xl
                            `}
                        >

                            {/* Stars */}

                            <div className="mb-5 text-xl text-yellow-400">
                                {"★".repeat(item.rating)}
                            </div>

                            {/* Quote */}

                            <div>

                                <EditableText
                                    value={item.quote}
                                    isTextArea={true}
                                    className={`block italic leading-8 ${theme.sub}`}
                                    onSave={(val) =>
                                        updateTestimonial(
                                            index,
                                            "quote",
                                            val
                                        )
                                    }
                                />

                            </div>

                            {/* Author */}

                            <div className="mt-6 flex items-center gap-4">

                                <EditableImage
                                    websiteId={websiteId}
                                    blockIndex={blockIndex}
                                    src={item.avatar}
                                    showOverlay={false}
                                    className="w-14 h-14 rounded-full overflow-hidden flex-shrink-0"
                                    onSave={(url) =>
                                        updateTestimonial(index, "avatar", url)
                                    }
                                />

                                <div>

                                    <EditableText
                                        value={item.name}
                                        className={`block font-bold ${theme.text}`}
                                        onSave={(val) =>
                                            updateTestimonial(index, "name", val)
                                        }
                                    />

                                    <EditableText
                                        value={item.company}
                                        className={`block text-sm ${theme.sub}`}
                                        onSave={(val) =>
                                            updateTestimonial(index, "company", val)
                                        }
                                    />

                                </div>

                            </div>

                        </div>

                    ))}

                </div>

            </div>

        </section>

    );

}
