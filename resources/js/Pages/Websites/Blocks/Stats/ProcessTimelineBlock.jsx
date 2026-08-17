import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { RepeatableControls, RepeatableRemoveButton, cloneLast, removeAt } from "../Shared/RepeatableControls";
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

    const requestedTheme = block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme;
    const theme = getEffectiveTheme(requestedTheme, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.midnight;
    const themeGlow = primaryTheme?.gradient?.glowSoft || "rgba(96, 165, 250, 0.10)";

    const data = {
        ...ProcessTimelineSchema.defaults,
        ...block,
        steps: Array.isArray(block.steps) && block.steps.length ? block.steps : ProcessTimelineSchema.defaults.steps
    };

    const steps = data.steps;
    const updateStep = (index, field, value) => onUpdate({
        steps: steps.map((step, stepIndex) => stepIndex === index ? { ...step, [field]: value } : step)
    });

    return (

        <section
            className={`group/repeatable-section relative py-32 px-7 overflow-hidden ${theme.bg} transition-colors duration-500`}
        >

            <div
                className="absolute top-0 right-[-180px] h-[420px] w-[420px] rounded-full blur-[170px] pointer-events-none"
                style={{ backgroundColor: themeGlow }}
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
                        className={`block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${theme.text}`}
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
                            className={`group relative rounded-3xl ${theme.card} p-8 border ${theme.border}`}
                        >

                            <EditableText
                                value={step.number}
                                className={`block text-5xl font-bold opacity-20 mb-6 ${theme.text}`}
                                onSave={(val) => updateStep(index, "number", val)}
                            />

                            <EditableText
                                value={step.title}
                                className={`block text-2xl font-bold mb-4 ${theme.text}`}
                                onSave={(val) => updateStep(index, "title", val)}
                            />

                            <EditableText
                                value={step.text}
                                className={`block leading-7 ${theme.sub}`}
                                onSave={(val) => updateStep(index, "text", val)}
                            />

                            <RepeatableRemoveButton hoverScope="card"
                                onRemove={() => onUpdate({ steps: removeAt(steps, index, 1) })}
                                disabled={steps.length <= 1}
                                label="Remove step"
                                overlay
                            />

                        </div>

                    ))}

                </div>

                <RepeatableControls
                    onAdd={() => steps.length < 8 && onUpdate({ steps: cloneLast(steps, ProcessTimelineSchema.defaults.steps[0]) })}
                    onRemove={() => onUpdate({ steps: removeAt(steps, steps.length - 1, 1) })}
                    canAdd={steps.length < 8}
                    addLabel="Add step"
                    showRemove={false}
                />

            </div>

        </section>

    );

}
