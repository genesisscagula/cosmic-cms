import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";

export const LocationMapSchema = {
    type: "location_map", title: "Location Map", category: "Contact",
    purpose: "Show where and how visitors can find the business.",
    description: "A polished location and directions section without requiring a third-party map API.",
    tags: ["location", "map", "directions", "contact"],
    defaults: {
        eyebrow: "Find us", heading: "Close when you need us", text: "Visit by appointment or get in touch to confirm the best time.",
        location_name: "Your business location", address: "Serving your local area", service_area: "Appointments and service visits available.", directions_label: "Get directions",
    },
    fields: [{ key: "eyebrow", type: "text", label: "Eyebrow" }, { key: "heading", type: "text", label: "Heading" }, { key: "text", type: "textarea", label: "Supporting text" }, { key: "location_name", type: "text", label: "Location name" }, { key: "address", type: "textarea", label: "Address" }, { key: "service_area", type: "textarea", label: "Service area" }, { key: "directions_label", type: "text", label: "Directions button label" }],
};

export function LocationMapBlock({ block, onUpdate, globalTheme }) {
    const theme = getEffectiveTheme(block.resolvedTheme, globalTheme);
    const data = { ...LocationMapSchema.defaults, ...block };
    return <section className={`px-6 py-16 sm:px-8 lg:py-20 ${theme.bg} transition-colors duration-500`}>
        <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-16">
            <div className="space-y-4">
                {data.eyebrow && <EditableText value={data.eyebrow} className={`block text-xs font-semibold uppercase tracking-[0.22em] ${theme.sub}`} onSave={(eyebrow) => onUpdate({ eyebrow })} />}
                <EditableText value={data.heading} className={`block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${theme.text}`} onSave={(heading) => onUpdate({ heading })} />
                <EditableText value={data.text} isTextArea className={`block max-w-xl text-base leading-7 ${theme.sub}`} onSave={(text) => onUpdate({ text })} />
            </div>
            <div className={`relative isolate min-h-[22rem] overflow-hidden rounded-3xl border p-7 sm:p-9 ${theme.border} ${theme.card}`}>
                <div className={`absolute inset-0 opacity-30 [background-image:linear-gradient(currentColor_1px,transparent_1px),linear-gradient(90deg,currentColor_1px,transparent_1px)] [background-size:2.5rem_2.5rem] ${theme.sub}`} />
                <div className="absolute inset-0 bg-gradient-to-br from-transparent via-transparent to-black/10" />
                <div className="relative flex h-full min-h-[16rem] flex-col justify-between">
                    <div className={`grid h-14 w-14 place-items-center rounded-full border-8 ${theme.border} ${theme.bg}`}><span className={`h-3 w-3 rounded-full bg-current ${theme.text}`} /></div>
                    <div className={`max-w-md rounded-2xl border p-5 backdrop-blur ${theme.border} ${theme.card}`}>
                        <EditableText value={data.location_name} className={`block text-lg font-semibold ${theme.text}`} onSave={(location_name) => onUpdate({ location_name })} />
                        <EditableText value={data.address} isTextArea className={`mt-2 block text-sm leading-6 ${theme.sub}`} onSave={(address) => onUpdate({ address })} />
                        <EditableText value={data.service_area} isTextArea className={`mt-3 block text-sm leading-6 ${theme.sub}`} onSave={(service_area) => onUpdate({ service_area })} />
                        <EditableText value={data.directions_label} className={`mt-5 block text-sm font-semibold ${theme.text}`} onSave={(directions_label) => onUpdate({ directions_label })} />
                    </div>
                </div>
            </div>
        </div>
    </section>;
}
