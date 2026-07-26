import { useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";

const FIELD_TYPES = ["text", "email", "tel", "textarea", "select", "radio", "checkbox", "date"];

const defaultFields = [
    { id: "name", name: "name", type: "text", label: "Name", placeholder: "Your name", required: true },
    { id: "email", name: "email", type: "email", label: "Email", placeholder: "you@example.com", required: true },
    { id: "phone", name: "phone", type: "tel", label: "Phone", placeholder: "Your phone number", required: false },
    { id: "message", name: "message", type: "textarea", label: "How can we help?", placeholder: "Tell us a little about your project", required: true },
];

const slugify = (value, fallback = "field") => {
    const slug = String(value || "").toLowerCase().trim().replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "");
    return slug || fallback;
};

export const normalizeContactFields = (fields) => {
    const source = Array.isArray(fields) && fields.length ? fields : defaultFields;
    return source.slice(0, 8).map((field, index) => {
        const type = FIELD_TYPES.includes(field?.type) ? field.type : "text";
        const label = String(field?.label || `Field ${index + 1}`);
        const name = slugify(field?.name || label, `field_${index + 1}`);
        const options = Array.isArray(field?.options)
            ? field.options.map((option) => String(option).trim()).filter(Boolean).slice(0, 8)
            : [];

        return {
            id: String(field?.id || name || `field_${index + 1}`),
            name,
            type,
            label,
            placeholder: String(field?.placeholder || ""),
            required: Boolean(field?.required),
            options,
        };
    });
};

export const ContactFormModernSchema = {
    type: "contact_form_modern",
    title: "Contact Form",
    category: "Contact",
    purpose: "Give visitors a clear, professional way to start a conversation.",
    description: "A premium split contact section with an editable, structured inquiry form.",
    tags: ["contact", "form", "inquiry", "lead", "cta"],
    defaults: {
        theme: "auto",
        eyebrow: "START A CONVERSATION",
        heading: "Let's talk about what's next.",
        text: "Tell us a little about your goals and our team will help you find the right next step.",
        email: "hello@example.com",
        phone: "+1 (555) 010-0200",
        address: "Available by appointment",
        submit_label: "Send inquiry",
        fields: defaultFields,
    },
};

function FormField({ field, inputClass, theme }) {
    const label = <span>{field.label}{field.required ? <span className="ml-1 text-rose-400">*</span> : null}</span>;
    const options = field.options.length ? field.options : ["Option one", "Option two"];

    if (field.type === "textarea") {
        return <label className={`block text-sm font-semibold ${theme.text}`}>{label}<textarea className={`mt-2 min-h-32 w-full resize-y rounded-xl border px-4 py-3 text-sm outline-none transition focus:ring-2 focus:ring-violet-400/60 ${inputClass}`} placeholder={field.placeholder} required={field.required} /></label>;
    }
    if (field.type === "select") {
        return <label className={`block text-sm font-semibold ${theme.text}`}>{label}<select style={{ colorScheme: "dark" }} className={`mt-2 h-12 w-full rounded-xl border px-4 text-sm outline-none transition focus:ring-2 focus:ring-violet-400/60 ${inputClass}`} defaultValue="" required={field.required}><option value="" disabled>{field.placeholder || "Select an option"}</option>{options.map((option) => <option className="bg-slate-900 text-white" key={option}>{option}</option>)}</select></label>;
    }
    if (field.type === "radio") {
        return <fieldset className={`text-sm font-semibold ${theme.text}`}><legend>{label}</legend><div className="mt-3 flex flex-wrap gap-3">{options.map((option) => <label className={`inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium ${theme.border}`} key={option}><input type="radio" name={field.name} required={field.required} />{option}</label>)}</div></fieldset>;
    }
    if (field.type === "checkbox") {
        if (field.options.length) {
            return <fieldset className={`text-sm font-semibold ${theme.text}`}><legend>{label}{field.required ? <span className="ml-1 text-rose-400">*</span> : null}</legend><div className="mt-3 space-y-2">{field.options.map((option) => <label className={`flex items-center gap-2 text-sm font-medium ${theme.sub}`} key={option}><input className="h-4 w-4 rounded border-slate-400 text-violet-500 focus:ring-violet-400" type="checkbox" />{option}</label>)}</div></fieldset>;
        }
        return <label className={`flex items-start gap-3 text-sm font-medium ${theme.text}`}><input className="mt-1 h-4 w-4 rounded border-slate-400 text-violet-500 focus:ring-violet-400" type="checkbox" required={field.required} /><span>{field.label}{field.required ? <span className="ml-1 text-rose-400">*</span> : null}</span></label>;
    }
    return <label className={`block text-sm font-semibold ${theme.text}`}>{label}<input type={field.type} style={field.type === "date" ? { colorScheme: "dark" } : undefined} className={`mt-2 h-12 w-full rounded-xl border px-4 text-sm outline-none transition focus:ring-2 focus:ring-violet-400/60 ${inputClass}`} placeholder={field.placeholder} required={field.required} /></label>;
}

function FormFieldsEditor({ fields, onSave, onClose }) {
    const [draft, setDraft] = useState(() => normalizeContactFields(fields));
    const updateField = (index, patch) => setDraft((current) => current.map((field, fieldIndex) => fieldIndex === index ? { ...field, ...patch } : field));
    const move = (index, direction) => setDraft((current) => { const next = [...current]; const target = index + direction; if (target < 0 || target >= next.length) return current; [next[index], next[target]] = [next[target], next[index]]; return next; });
    const addField = () => setDraft((current) => [...current, { id: `field_${Date.now()}`, name: "new_field", type: "text", label: "New field", placeholder: "", required: false, options: [] }]);
    useEffect(() => { const onKeyDown = (event) => event.key === "Escape" && onClose(); window.addEventListener("keydown", onKeyDown); return () => window.removeEventListener("keydown", onKeyDown); }, [onClose]);

    return createPortal(<div className="fixed inset-0 z-[200] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Edit form fields" onMouseDown={onClose}>
        <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-700 bg-[#17181c] shadow-2xl" onMouseDown={(event) => event.stopPropagation()}>
            <div className="sticky top-0 z-10 flex items-start justify-between border-b border-slate-700 bg-[#17181c] px-6 py-5"><div><h3 className="text-lg font-bold text-white">Edit form fields</h3><p className="mt-1 text-sm text-slate-400">Choose the questions visitors answer. These fields are included in published inquiries.</p></div><button type="button" onClick={onClose} className="rounded-lg p-2 text-slate-400 hover:bg-white/5 hover:text-white" aria-label="Close form field editor">×</button></div>
            <div className="space-y-4 p-6">{draft.map((field, index) => <div key={field.id} className="rounded-xl border border-slate-700 bg-slate-900/40 p-4"><div className="mb-3 flex items-center justify-between"><span className="text-sm font-semibold text-white">Field {index + 1}</span><div className="flex gap-1"><button type="button" disabled={index === 0} onClick={() => move(index, -1)} className="rounded px-2 py-1 text-xs text-slate-300 disabled:opacity-30 hover:bg-white/10">Up</button><button type="button" disabled={index === draft.length - 1} onClick={() => move(index, 1)} className="rounded px-2 py-1 text-xs text-slate-300 disabled:opacity-30 hover:bg-white/10">Down</button><button type="button" onClick={() => setDraft((current) => current.filter((_, fieldIndex) => fieldIndex !== index))} className="rounded px-2 py-1 text-xs text-rose-300 hover:bg-rose-500/10">Remove</button></div></div><div className="grid gap-3 sm:grid-cols-2"><label className="text-xs font-medium text-slate-400">Label<input value={field.label} onChange={(event) => updateField(index, { label: event.target.value, name: slugify(event.target.value, field.name) })} className="mt-1 h-10 w-full rounded-lg border border-slate-700 bg-[#111216] px-3 text-sm text-white outline-none focus:border-violet-400" /></label><label className="text-xs font-medium text-slate-400">Field type<select value={field.type} onChange={(event) => updateField(index, { type: event.target.value })} className="mt-1 h-10 w-full rounded-lg border border-slate-700 bg-[#111216] px-3 text-sm text-white outline-none focus:border-violet-400">{FIELD_TYPES.map((type) => <option key={type} value={type}>{type}</option>)}</select></label><label className="text-xs font-medium text-slate-400">Placeholder<input value={field.placeholder} onChange={(event) => updateField(index, { placeholder: event.target.value })} className="mt-1 h-10 w-full rounded-lg border border-slate-700 bg-[#111216] px-3 text-sm text-white outline-none focus:border-violet-400" /></label><label className="flex items-end gap-2 pb-2 text-sm text-slate-300"><input type="checkbox" checked={field.required} onChange={(event) => updateField(index, { required: event.target.checked })} /> Required</label></div>{["select", "radio", "checkbox"].includes(field.type) ? <label className="mt-3 block text-xs font-medium text-slate-400">Options — one per line<textarea value={field.options.join("\n")} onKeyDown={(event) => event.stopPropagation()} onChange={(event) => updateField(index, { options: event.target.value.split(/\r?\n/).map((value) => value.trim()) })} rows={4} placeholder={field.type === "checkbox" ? "Option one\nOption two" : "First option\nSecond option"} className="mt-1 w-full resize-y rounded-lg border border-slate-700 bg-[#111216] px-3 py-2 text-sm text-white outline-none focus:border-violet-400" /></label> : null}</div>)}<button type="button" onClick={addField} className="rounded-lg border border-dashed border-violet-400/60 px-4 py-2 text-sm font-semibold text-violet-200 hover:bg-violet-500/10">+ Add field</button></div>
            <div className="sticky bottom-0 flex justify-end gap-3 border-t border-slate-700 bg-[#17181c] px-6 py-4"><button type="button" onClick={onClose} className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-white/5">Cancel</button><button type="button" onClick={() => { onSave(normalizeContactFields(draft)); onClose(); }} className="rounded-lg bg-white px-4 py-2 text-sm font-bold text-slate-950 hover:bg-slate-200">Save fields</button></div>
        </div>
    </div>, document.body);
}

export function ContactFormModernBlock({ block, onUpdate, globalTheme }) {
    const data = { ...ContactFormModernSchema.defaults, ...block };
    const fields = useMemo(() => normalizeContactFields(data.fields), [data.fields]);
    const [editingFields, setEditingFields] = useState(false);
    const theme = getEffectiveTheme(block.resolvedTheme, globalTheme);
    const primaryTheme = colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const isPrimarySection = block.resolvedTheme === "primary";
    const buttonStyle = isPrimarySection ? "bg-white text-slate-950" : `${primaryTheme.bg} ${primaryTheme.text}`;
    const inputSurface = isPrimarySection ? "border-white/30 bg-slate-950/20 placeholder:text-white/50 focus:border-white/70" : `${theme.border} bg-transparent placeholder:opacity-70`;
    const inputClass = `h-12 w-full rounded-xl border px-4 text-sm outline-none transition focus:ring-2 focus:ring-violet-400/60 ${inputSurface} ${theme.text}`;

    return <section className={`relative overflow-hidden px-7 py-16 sm:px-10 sm:py-20 lg:px-12 lg:py-24 ${theme.bg} transition-colors duration-500`}>
        <div className={`pointer-events-none absolute -left-32 top-1/2 h-80 w-80 -translate-y-1/2 rounded-full opacity-[0.1] blur-[120px] ${primaryTheme.bg}`} />
        <div className="relative mx-auto grid max-w-7xl gap-12 lg:grid-cols-[0.88fr_1.12fr] lg:items-start lg:gap-20"><div className="max-w-xl pt-2"><EditableText value={data.eyebrow} className={`block text-xs font-semibold uppercase tracking-[0.3em] ${theme.sub}`} onSave={(eyebrow) => onUpdate({ eyebrow })} /><EditableText value={data.heading} className={`mt-5 block text-4xl font-black leading-[1.06] tracking-tight sm:text-5xl lg:text-6xl ${theme.text}`} onSave={(heading) => onUpdate({ heading })} /><EditableText value={data.text} isTextArea className={`mt-5 block text-base leading-7 sm:text-lg sm:leading-8 ${theme.sub}`} onSave={(text) => onUpdate({ text })} /><div className={`mt-9 space-y-4 border-t pt-7 ${theme.border}`}><div><p className={`text-xs font-semibold uppercase tracking-[0.18em] ${theme.sub}`}>Email</p><EditableText value={data.email} className={`mt-1 block text-base font-semibold ${theme.text}`} onSave={(email) => onUpdate({ email })} /></div><div><p className={`text-xs font-semibold uppercase tracking-[0.18em] ${theme.sub}`}>Phone</p><EditableText value={data.phone} className={`mt-1 block text-base font-semibold ${theme.text}`} onSave={(phone) => onUpdate({ phone })} /></div><div><p className={`text-xs font-semibold uppercase tracking-[0.18em] ${theme.sub}`}>Visit</p><EditableText value={data.address} className={`mt-1 block text-base font-semibold ${theme.text}`} onSave={(address) => onUpdate({ address })} /></div></div></div>
            <form onSubmit={(event) => event.preventDefault()} className={`rounded-[2rem] border p-5 shadow-2xl sm:p-8 ${theme.card} ${theme.border}`}><div className="mb-5 flex items-center justify-between gap-4"><p className={`text-sm font-semibold ${theme.text}`}>Inquiry form</p><button type="button" onClick={() => setEditingFields(true)} className="rounded-lg border border-violet-400/60 px-3 py-1.5 text-xs font-bold text-violet-100 transition hover:bg-violet-500/15">Edit fields</button></div><div className="space-y-5">{fields.map((field) => <FormField key={field.id} field={field} inputClass={inputClass} theme={theme} />)}</div><button type="submit" className={`mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-xl px-6 text-sm font-bold transition hover:opacity-90 ${buttonStyle}`}><EditableText value={data.submit_label} className="block" onSave={(submit_label) => onUpdate({ submit_label })} /></button><p className={`mt-3 text-center text-xs ${theme.sub}`}>We'll use your details only to respond to your inquiry.</p></form>
        </div>{editingFields ? <FormFieldsEditor fields={fields} onSave={(nextFields) => onUpdate({ fields: nextFields })} onClose={() => setEditingFields(false)} /> : null}
    </section>;
}
