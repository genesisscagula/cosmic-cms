import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";

export const ContactDetailsSchema = {
    type: "contact_details", title: "Contact Details", category: "Contact",
    purpose: "Give visitors a clear, trustworthy way to reach the business.",
    description: "Editable contact details and availability without a form.",
    tags: ["contact", "details", "hours", "location"],
    defaults: {
        eyebrow: "Contact us", heading: "A clear way to reach us", text: "Share what you need and we will help you find the right next step.",
        email: "hello@example.com", phone: "+1 (555) 010-0200", address: "Serving clients by appointment", hours: "Monday–Friday, 9:00 AM–5:00 PM",
    },
    fields: [{ key: "eyebrow", type: "text", label: "Eyebrow" }, { key: "heading", type: "text", label: "Heading" }, { key: "text", type: "textarea", label: "Supporting text" }, { key: "email", type: "text", label: "Email" }, { key: "phone", type: "text", label: "Phone" }, { key: "address", type: "textarea", label: "Address / service area" }, { key: "hours", type: "text", label: "Hours" }],
};

export function ContactDetailsBlock({ block, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme, globalTheme);
    const data = { ...ContactDetailsSchema.defaults, ...block };
    const items = [["Email", "email"], ["Phone", "phone"], ["Visit", "address"], ["Hours", "hours"]];

    return <section className={`px-6 py-16 sm:px-8 lg:py-20 ${theme.bg} transition-colors duration-500`}>
        <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-2 lg:gap-16">
            <div className="space-y-4">
                {data.eyebrow && <EditableText value={data.eyebrow} className={`block text-xs font-semibold uppercase tracking-[0.22em] ${theme.sub}`} onSave={(eyebrow) => onUpdate({ eyebrow })} />}
                <EditableText value={data.heading} className={`block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${theme.text}`} onSave={(heading) => onUpdate({ heading })} />
                <EditableText value={data.text} isTextArea className={`block max-w-xl text-base leading-7 ${theme.sub}`} onSave={(text) => onUpdate({ text })} />
            </div>
            <div className={`grid overflow-hidden rounded-2xl border sm:grid-cols-2 ${theme.border} ${theme.card}`}>
                {items.map(([label, key], index) => <div key={key} className={`min-h-36 p-6 ${index < 2 ? "border-b" : ""} ${index % 2 === 0 ? "sm:border-r" : ""} ${theme.border}`}>
                    <span className={`block text-xs font-semibold uppercase tracking-[0.18em] ${theme.sub}`}>{label}</span>
                    <EditableText value={data[key]} isTextArea={key === "address"} className={`mt-4 block text-base font-semibold leading-6 ${theme.text}`} onSave={(value) => onUpdate({ [key]: value })} />
                </div>)}
            </div>
        </div>
    </section>;
}
