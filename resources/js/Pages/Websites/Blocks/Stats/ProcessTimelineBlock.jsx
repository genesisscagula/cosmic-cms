import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";


export const ProcessTimelineSchema = {

    type: "process_timeline",

    title: "Process Timeline",

    category: "Features",

    purpose: "Show the step-by-step process, workflow or customer journey in a clean timeline layout.",

    description:
        "Display a sequence of process steps with titles and descriptions to explain how a service or workflow operates.",

    tags: [
        "process",
        "timeline",
        "workflow",
        "steps",
        "journey",
        "service",
        "how it works"
    ],

    defaults: {

        category: "HOW IT WORKS",

        heading: "Our Simple Process",

        text: "We follow a proven workflow to deliver consistent quality and excellent customer experience.",

        steps: [

            {
                number: "01",
                title: "Consultation",
                text: "Tell us about your project and requirements."
            },

            {
                number: "02",
                title: "Planning",
                text: "We prepare the best solution tailored for your needs."
            },

            {
                number: "03",
                title: "Execution",
                text: "Our experienced team completes the work with precision."
            },

            {
                number: "04",
                title: "Completion",
                text: "Final inspection and project handover with ongoing support."
            }

        ]

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
            key: "steps",
            type: "repeater",
            label: "Timeline Steps",
            fields: [

                {
                    key: "number",
                    type: "text",
                    label: "Step Number"
                },

                {
                    key: "title",
                    type: "text",
                    label: "Step Title"
                },

                {
                    key: "text",
                    type: "textarea",
                    label: "Step Description"
                }

            ]
        }

    ]

};


export function ProcessTimelineBlock({ block, blockIndex, onUpdate, globalTheme }) {

    const theme = getEffectiveTheme(
        block.resolvedTheme,
        globalTheme
    );

    const data = {
        ...ProcessTimelineSchema.defaults,
        ...block
    };

    return (

        <section
            className={`relative py-32 px-7 overflow-hidden ${theme.bg} transition-colors duration-500`}
        >

            <div
                className="
                    absolute
                    top-0
                    right-[-180px]
                    w-[420px]
                    h-[420px]
                    rounded-full
                    bg-blue-500/10
                    blur-[170px]
                    pointer-events-none
                "
            />

            <div className="max-w-7xl mx-auto">

                <div className="text-center max-w-3xl mx-auto mb-20 space-y-6">

                    <EditableText
                        value={data.category}
                        className={`block text-xs font-semibold uppercase tracking-[0.30em] ${theme.sub}`}
                        onSave={(val) =>
                            onUpdate({
                                category: val
                            })
                        }
                    />

                    <EditableText
                        value={data.heading}
                        className={`block text-5xl md:text-6xl font-bold leading-tight tracking-tight ${theme.text}`}
                        onSave={(val) =>
                            onUpdate({
                                heading: val
                            })
                        }
                    />

                    <EditableText
                        value={data.text}
                        className={`block text-lg leading-8 ${theme.sub}`}
                        onSave={(val) =>
                            onUpdate({
                                text: val
                            })
                        }
                    />

                </div>

                <div className="grid md:grid-cols-4 gap-10">

                    {data.steps.map((step, index) => (

                        <div
                            key={index}
                            className={`relative rounded-3xl ${theme.card} p-8 border ${theme.border}`}
                        >

                            <EditableText
                                value={step.number}
                                className={`block text-5xl font-bold opacity-20 mb-6 ${theme.text}`}
                                onSave={(val) => {

                                    const steps = [...data.steps];
                                    steps[index].number = val;

                                    onUpdate({
                                        steps
                                    });

                                }}
                            />

                            <EditableText
                                value={step.title}
                                className={`block text-2xl font-bold mb-4 ${theme.text}`}
                                onSave={(val) => {

                                    const steps = [...data.steps];
                                    steps[index].title = val;

                                    onUpdate({
                                        steps
                                    });

                                }}
                            />

                            <EditableText
                                value={step.text}
                                className={`block leading-7 ${theme.sub}`}
                                onSave={(val) => {

                                    const steps = [...data.steps];
                                    steps[index].text = val;

                                    onUpdate({
                                        steps
                                    });

                                }}
                            />

                        </div>

                    ))}

                </div>

            </div>

        </section>

    );

}