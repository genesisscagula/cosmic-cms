import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";

export const StatsModernSchema = {
    type: "stats_modern",
    title: "Modern Stats",
    category: "Features",
    purpose: "Build trust with a compact, editable set of service or business metrics.",
    description: "Display a heading and up to four outcome-focused statistics for any industry.",
    tags: ["stats", "metrics", "trust", "results", "numbers"],
    defaults: {
        eyebrow: "Why choose us",
        heading: "Experience you can count on",
        text: "Clear results, dependable service, and a team committed to every project.",
        metrics: [
            { value: "15+", label: "Years of experience", description: "Serving customers with proven expertise." },
            { value: "250+", label: "Projects completed", description: "Delivered across a wide range of needs." },
            { value: "98%", label: "Client satisfaction", description: "Built through reliable service and support." },
            { value: "24/7", label: "Responsive support", description: "Help is available whenever it matters." }
        ]
    },
    fields: [
        { key: "eyebrow", type: "text", label: "Eyebrow" },
        { key: "heading", type: "text", label: "Heading" },
        { key: "text", type: "textarea", label: "Supporting text" },
        {
            key: "metrics",
            type: "repeater",
            label: "Metrics",
            fields: [
                { key: "value", type: "text", label: "Value" },
                { key: "label", type: "text", label: "Label" },
                { key: "description", type: "textarea", label: "Description" }
            ]
        }
    ]
};

export function StatsModernBlock({ block, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.resolvedTheme, globalTheme);
    const data = {
        ...StatsModernSchema.defaults,
        ...block,
        metrics: Array.isArray(block.metrics) && block.metrics.length
            ? block.metrics
            : StatsModernSchema.defaults.metrics
    };

    const updateMetric = (index, field, value) => {
        onUpdate({
            metrics: data.metrics.map((metric, metricIndex) => (
                metricIndex === index ? { ...metric, [field]: value } : metric
            ))
        });
    };

    return (
        <section className={`px-6 py-16 sm:px-8 lg:py-20 ${theme.bg} transition-colors duration-500`}>
            <div className="mx-auto max-w-7xl">
                <div className="mb-10 max-w-2xl space-y-4 sm:mb-12">
                    {data.eyebrow && (
                        <EditableText
                            value={data.eyebrow}
                            className={`block text-xs font-semibold uppercase tracking-[0.22em] ${theme.sub}`}
                            onSave={(eyebrow) => onUpdate({ eyebrow })}
                        />
                    )}
                    <EditableText
                        value={data.heading}
                        className={`block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${theme.text}`}
                        onSave={(heading) => onUpdate({ heading })}
                    />
                    {data.text && (
                        <EditableText
                            value={data.text}
                            isTextArea
                            className={`block max-w-xl text-base leading-7 ${theme.sub}`}
                            onSave={(text) => onUpdate({ text })}
                        />
                    )}
                </div>

                <div className={`grid grid-cols-1 border-y ${theme.border} sm:grid-cols-2 lg:grid-cols-4`}>
                    {data.metrics.slice(0, 4).map((metric, index) => (
                        <article
                            key={index}
                            className={`min-w-0 border-b p-6 last:border-b-0 sm:border-b-0 sm:border-r sm:last:border-r-0 lg:p-7 ${theme.border}`}
                        >
                            <EditableText
                                value={metric.value}
                                className={`block text-3xl font-bold tracking-tight sm:text-4xl ${theme.text}`}
                                onSave={(value) => updateMetric(index, "value", value)}
                            />
                            <EditableText
                                value={metric.label}
                                className={`mt-3 block text-sm font-semibold ${theme.text}`}
                                onSave={(label) => updateMetric(index, "label", label)}
                            />
                            {metric.description && (
                                <EditableText
                                    value={metric.description}
                                    isTextArea
                                    className={`mt-2 block text-sm leading-6 ${theme.sub}`}
                                    onSave={(description) => updateMetric(index, "description", description)}
                                />
                            )}
                        </article>
                    ))}
                </div>
            </div>
        </section>
    );
}
