import { useState } from "react";
import { EditableText } from "../Shared/EditableText";
import { getSectionSurfaceThemes } from "../../../../theme/Theme";
import { RepeatableControls, RepeatableRemoveButton, cloneLast, removeAt } from "../Shared/RepeatableControls";
import { sparkTw, sparkTwItem } from "../Shared/sparkTailwindRuntime";

export const FaqAccordionSchema = {
    type: "faq_accordion",
    title: "FAQ Accordion",
    category: "FAQ",
    purpose: "Answer common customer questions in a clear, scannable format.",
    description: "A compact, editable FAQ section with accessible accordion controls.",
    tags: ["faq", "questions", "support", "trust"],
    defaults: {
        eyebrow: "Helpful answers",
        heading: "Questions, answered clearly",
        text: "Everything visitors need to know before taking the next step.",
        faqs: [
            { question: "What can I expect?", answer: "You can expect clear communication, practical guidance, and a straightforward next step." },
            { question: "How do I get started?", answer: "Send an inquiry and we will help you choose the option that fits your needs." },
            { question: "Can I ask a specific question?", answer: "Absolutely. Share a little context and we will point you in the right direction." },
            { question: "When will I hear back?", answer: "We aim to respond as soon as we can with the details you need." },
        ],
    },
    fields: [
        { key: "eyebrow", type: "text", label: "Eyebrow" },
        { key: "heading", type: "text", label: "Heading" },
        { key: "text", type: "textarea", label: "Supporting text" },
        { key: "faqs", type: "repeater", label: "Questions", fields: [{ key: "question", type: "text", label: "Question" }, { key: "answer", type: "textarea", label: "Answer" }] },
    ],
};

export function FaqAccordionBlock({ block, onUpdate, globalTheme }) {
    const requestedTheme = block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme;
    const { section: theme, surface: cardTheme } = getSectionSurfaceThemes(requestedTheme, globalTheme);
    const [openIndex, setOpenIndex] = useState(0);
    const data = {
        ...FaqAccordionSchema.defaults,
        ...block,
        faqs: Array.isArray(block.faqs) && block.faqs.length ? block.faqs : FaqAccordionSchema.defaults.faqs,
    };

    const updateFaq = (index, key, value) => onUpdate({
        faqs: data.faqs.map((faq, faqIndex) => faqIndex === index ? { ...faq, [key]: value } : faq),
    });

    return (
        <section className={sparkTw(block, "auto_1", `group/repeatable-section px-6 py-16 sm:px-8 lg:py-20 ${theme.bg} transition-colors duration-500`)}>
            <div className={sparkTw(block, "auto_2", "mx-auto grid max-w-7xl gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16")}>
                <div className={sparkTw(block, "auto_3", "space-y-4")}>
                    {data.eyebrow && <EditableText value={data.eyebrow} className={sparkTw(block, "auto_4", `block text-xs font-semibold uppercase tracking-[0.22em] ${theme.sub}`)} onSave={(eyebrow) => onUpdate({ eyebrow })} />}
                    <EditableText value={data.heading} cosmicType="h2" className={sparkTw(block, "auto_5", `block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${theme.text}`)} onSave={(heading) => onUpdate({ heading })} />
                    {data.text && <EditableText value={data.text} isTextArea className={sparkTw(block, "auto_6", `block max-w-xl text-base leading-7 ${theme.sub}`)} onSave={(text) => onUpdate({ text })} />}
                </div>
                <div className={sparkTw(block, "auto_7", `overflow-hidden rounded-2xl border ${cardTheme.border} ${cardTheme.card}`)}>
                    {data.faqs.map((faq, index) => {
                        const isOpen = openIndex === index;
                        return <article key={index} className={sparkTwItem(block, "faqs", index, "item", `group relative border-b last:border-b-0 ${cardTheme.border}`)}>
                            <div className={sparkTwItem(block, "faqs", index, "trigger", "flex items-start gap-4 p-5 pr-16 sm:p-6 sm:pr-16")}>
                                <EditableText value={faq.question} className={sparkTwItem(block, "faqs", index, "question", `flex-1 text-base font-semibold ${cardTheme.text}`)} onSave={(question) => updateFaq(index, "question", question)} />
                                <button type="button" aria-label={isOpen ? "Collapse answer" : "Expand answer"} aria-expanded={isOpen} onClick={() => setOpenIndex(isOpen ? -1 : index)} className={sparkTwItem(block, "faqs", index, "toggle", `mr-1 mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full border text-lg transition ${cardTheme.border} ${cardTheme.text}`)}>{isOpen ? "−" : "+"}</button>
                            </div>
                            {isOpen && <div className={sparkTwItem(block, "faqs", index, "answer_wrap", "px-5 pb-5 sm:px-6 sm:pb-6")}><EditableText value={faq.answer} isTextArea className={sparkTwItem(block, "faqs", index, "answer", `block text-sm leading-6 ${cardTheme.sub}`)} onSave={(answer) => updateFaq(index, "answer", answer)} /></div>}
                            <RepeatableRemoveButton hoverScope="card"
                                overlay
                                onRemove={() => onUpdate({ faqs: removeAt(data.faqs, index, 1) })}
                                disabled={data.faqs.length <= 1}
                                label="Remove question"
                            />
                        </article>;
                    })}
                </div>
                <div className={sparkTw(block, "auto_14", "lg:col-start-2")}>
                    <RepeatableControls
                        onAdd={() => data.faqs.length < 12 && onUpdate({ faqs: cloneLast(data.faqs, FaqAccordionSchema.defaults.faqs[0]) })}
                        onRemove={() => onUpdate({ faqs: removeAt(data.faqs, data.faqs.length - 1, 1) })}
                        canAdd={data.faqs.length < 12}
                        canRemove={data.faqs.length > 1}
                        addLabel="Add question"
                        addButtonClassName="!text-white !border-white/60 hover:!border-white/90 hover:!bg-white/10"
                        removeLabel="Remove last question"
                        showRemove={false}
                    />
                </div>
            </div>
        </section>
    );
}
