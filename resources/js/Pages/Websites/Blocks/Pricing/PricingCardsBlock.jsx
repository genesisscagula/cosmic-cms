import { usePage } from "@inertiajs/react";

import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { EditableImage } from "../Shared/EditableImage";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";



export const PricingCardsSchema = {

    type: "pricing_cards",

    title: "Pricing Cards",

    category: "Pricing",

    purpose:
        "Display pricing plans in a modern comparison layout.",

    description:
        "Help customers compare plans and choose the best package for their needs.",

    tags: [
        "pricing",
        "plans",
        "packages",
        "subscription",
        "comparison",
        "cta"
    ],

    defaults: {

        tagline: "SIMPLE PRICING",

        heading: "Choose The Perfect Plan",

        text:
            "Flexible pricing options designed for individuals, growing businesses, and enterprise teams.",

        plans: [

            {
                badge: "",
                featured: false,

                title: "Starter",

                price: "$19",

                period: "/month",

                description:
                    "Perfect for individuals and small businesses getting started.",

                button_label: "Get Started",

                button_url: "#",

                features: [
                    "1 Website",
                    "Basic Support",
                    "5GB Storage",
                    "Free SSL"
                ]
            },

            {
                badge: "MOST POPULAR",

                featured: true,

                title: "Professional",

                price: "$49",

                period: "/month",

                description:
                    "Best for growing businesses that need more power and flexibility.",

                button_label: "Start Free Trial",

                button_url: "#",

                features: [
                    "10 Websites",
                    "Priority Support",
                    "Unlimited Storage",
                    "Free SSL",
                    "Daily Backups"
                ]
            },

            {
                badge: "",

                featured: false,

                title: "Enterprise",

                price: "Custom",

                period: "",

                description:
                    "Tailored solutions for large organizations with custom requirements.",

                button_label: "Contact Sales",

                button_url: "#",

                features: [
                    "Unlimited Websites",
                    "Dedicated Support",
                    "Custom Integrations",
                    "Enterprise Security",
                    "SLA Guarantee"
                ]
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
            key: "plans",
            type: "repeater",
            label: "Pricing Plans",

            fields: [

                {
                    key: "badge",
                    type: "text",
                    label: "Badge"
                },

                {
                    key: "featured",
                    type: "boolean",
                    label: "Featured Plan"
                },

                {
                    key: "title",
                    type: "text",
                    label: "Plan Name"
                },

                {
                    key: "price",
                    type: "text",
                    label: "Price"
                },

                {
                    key: "period",
                    type: "text",
                    label: "Billing Period"
                },

                {
                    key: "description",
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
                    type: "text",
                    label: "Button URL"
                },

                {
                    key: "features",
                    type: "repeater",
                    label: "Features",

                    fields: [

                        {
                            key: "text",
                            type: "text",
                            label: "Feature"
                        }

                    ]

                }

            ]

        }

    ]

};



export function PricingCardsBlock({
    block,
    blockIndex,
    onUpdate,
    globalTheme
}) {

    const theme = getEffectiveTheme(
        block.resolvedTheme,
        globalTheme
    );

    const plans = Array.isArray(block.plans)
        ? block.plans
        : PricingCardsSchema.defaults.plans;

    const data = {
        ...PricingCardsSchema.defaults,
        ...block,
        plans: plans.map((plan) => {
            if (!plan || typeof plan !== "object") {
                return plan;
            }

            return {
                ...plan,
                features: Array.isArray(plan.features)
                    ? plan.features.map((feature) => (
                        typeof feature === "string" ? { text: feature } : feature
                    ))
                    : plan.features
            };
        })
    };

    const globalPrimary = typeof globalTheme === "string"
        ? globalTheme
        : (globalTheme?.primary || "midnight");
    const primaryTheme = colorFamilies[globalPrimary] || colorFamilies.midnight;

	const isPrimarySection = block.resolvedTheme === "primary";

	const buttonStyle = isPrimarySection
	    ? {
	        bg: "bg-white",
	        text: "text-slate-950"
	    }
	    : {
	        bg: primaryTheme.bg,
	        text: primaryTheme.text
	    };

    const updatePlan = (index, field, value) => {

        const plans = [...data.plans];

        plans[index] = {
            ...plans[index],
            [field]: value
        };

        onUpdate({
            plans
        });

    };

    const updateFeature = (planIndex, featureIndex, value) => {

        const plans = [...data.plans];

        plans[planIndex].features[featureIndex] = {
            ...plans[planIndex].features[featureIndex],
            text: value
        };

        onUpdate({
            plans
        });

    };

    return (

        <section
            className={`relative px-6 py-20 sm:px-8 lg:py-24 ${theme.bg} transition-colors duration-500`}
        >

            <div className="max-w-7xl mx-auto">

                {/* Header */}

                <div className="text-center max-w-3xl mx-auto mb-12 sm:mb-14">

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
                        className={`block mt-5 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${theme.text}`}
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

                {/* Plans */}

                <div className="grid gap-6 md:grid-cols-3 lg:gap-7">

                    {data.plans.map((plan, index) => (

                        <div
                            key={index}
                            className={`
                                relative
                                rounded-3xl
                                border
                                ${theme.border}
                                ${theme.card}
                                p-7
                                lg:p-8
                                transition-all
                                duration-300
                                hover:-translate-y-2
                                hover:shadow-2xl
                                ${plan.featured ? "scale-105 ring-2 ring-white/40" : ""}
                            `}
                        >

                            {plan.badge && (

                            <div className="absolute -top-3 left-1/2 z-10 -translate-x-1/2">

                                    <span className={`inline-flex whitespace-nowrap rounded-full px-3 py-1.5 ${primaryTheme.bg} ${primaryTheme.text} text-[10px] font-semibold uppercase tracking-[0.16em] shadow-sm`}>

                                        <EditableText
                                            value={plan.badge}
                                            onSave={(val) =>
                                                updatePlan(index, "badge", val)
                                            }
                                        />

                                    </span>

                                </div>

                            )}

                            {/* Plan */}

                            <EditableText
                                value={plan.title}
                                className={`block text-2xl font-bold ${theme.text}`}
                                onSave={(val) =>
                                    updatePlan(index, "title", val)
                                }
                            />

                            <div className="mt-5 flex items-end gap-2">

                                <EditableText
                                    value={plan.price}
                                className={`block text-4xl font-bold sm:text-5xl ${theme.text}`}
                                    onSave={(val) =>
                                        updatePlan(index, "price", val)
                                    }
                                />

                                <EditableText
                                    value={plan.period}
                                    className={`block mb-2 ${theme.sub}`}
                                    onSave={(val) =>
                                        updatePlan(index, "period", val)
                                    }
                                />

                            </div>

                            <EditableText
                                value={plan.description}
                                isTextArea={true}
                            className={`block mt-5 leading-7 ${theme.sub}`}
                                onSave={(val) =>
                                    updatePlan(index, "description", val)
                                }
                            />

                            {/* Features */}

                            <div className="mt-7 space-y-3">

                                {plan.features.map((feature, featureIndex) => (

                                    <div
                                        key={featureIndex}
                                        className="flex items-center gap-3"
                                    >

                                        <svg
										    className={`w-5 h-5 ${theme.text}`}
										    fill="none"
										    stroke="currentColor"
										    strokeWidth="2.5"
										    viewBox="0 0 24 24"
										>
										    <path
										        strokeLinecap="round"
										        strokeLinejoin="round"
										        d="M5 13l4 4L19 7"
										    />
										</svg>

                                        <EditableText
                                            value={feature.text}
                                            className={`${theme.text}`}
                                            onSave={(val) =>
                                                updateFeature(
                                                    index,
                                                    featureIndex,
                                                    val
                                                )
                                            }
                                        />

                                    </div>

                                ))}

                            </div>

                            {/* Button */}

                            <div className="mt-8">

                                <EditableButton
								    label={plan.button_label}
								    url={plan.button_url}
								    className={`
								        w-full
								        inline-flex
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
								    `}
								    onSave={(label, url) => {
								        updatePlan(index, "button_label", label);
								        updatePlan(index, "button_url", url);
								    }}
								/>

                            </div>

                        </div>

                    ))}

                </div>

            </div>

        </section>

    );

}
