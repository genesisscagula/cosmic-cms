import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";

const F = (key, type = "text", label = null) => ({ key, type, label: label || key.replaceAll("_", " ") });
const Text = ({ value, className = "", fieldPath, cosmicType, area = false, style }) => <EditableText value={value} className={className} fieldPath={fieldPath} cosmicType={cosmicType} isTextArea={area} style={style} />;
const Button = ({ label, url = "#", className = "", fieldPath, style }) => <EditableButton label={label} url={url} className={className} fieldPath={fieldPath} style={style} />;
const Image = ({ src, className = "", fieldPath, background = false, style }) => <EditableImage src={src} className={className} fieldPath={fieldPath} isBackground={background} style={style} />;

const LEDGER = { ink: "#102b33", deep: "#09242d", teal: "#0c6670", teal2: "#16838d", gold: "#d3b166", cream: "#f7f6f1", line: "#dfe7e5", muted: "#60747a" };
const EMBER = { ink: "#201712", deep: "#17110e", cream: "#f4ede2", paper: "#fffaf3", copper: "#c7794e", copper2: "#df9b69", line: "#d9ccbc", muted: "#786d64" };

const ledgerServices = [
    { icon: "▤", title: "Expert Accounting", text: "Accurate bookkeeping and financial reporting built around the way your business operates." },
    { icon: "✓", title: "Tax & Compliance", text: "Practical tax support that keeps deadlines, records, and obligations under control." },
    { icon: "↗", title: "Business Advisory", text: "Clear commercial insight to help you plan, improve cash flow, and make confident decisions." },
    { icon: "◇", title: "Secure & Reliable", text: "Professional systems, responsive support, and careful handling of your financial information." },
];

export const MarketplaceLedgerHeroSchema = {
    type: "marketplace_ledger_hero", title: "LedgerPoint Executive Hero", category: "Marketplace",
    defaults: {
        eyebrow: "ACCOUNTING · TAX · BUSINESS ADVISORY",
        heading: "Clear numbers.", accent_heading: "Confident business decisions.",
        text: "We help businesses stay compliant, understand their numbers, and make better decisions with reliable accounting and practical advice.",
        primary_label: "Book a Consultation", primary_url: "/contact", secondary_label: "Explore Our Services", secondary_url: "/services",
        image_url: "https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1800&q=86",
        stats: [{ value: "150+", label: "Businesses supported" }, { value: "99%", label: "On-time compliance" }, { value: "12+", label: "Years experience" }],
    },
    fields: [F("eyebrow"), F("heading"), F("accent_heading"), F("text", "textarea"), F("primary_label"), F("primary_url"), F("secondary_label"), F("secondary_url"), F("image_url", "image"), F("stats", "repeater")],
};
export function MarketplaceLedgerHeroBlock({ block }) {
    const d = { ...MarketplaceLedgerHeroSchema.defaults, ...block };
    const stats = Array.isArray(d.stats) ? d.stats : MarketplaceLedgerHeroSchema.defaults.stats;
    return <section className="px-4 py-5 sm:px-6 lg:px-8" style={{ background: LEDGER.cream }}>
        <div className="relative mx-auto min-h-[650px] max-w-[1500px] overflow-hidden rounded-[34px] shadow-[0_34px_90px_rgba(9,36,45,.18)] lg:min-h-[730px]">
            <Image src={d.image_url} fieldPath="image_url" background className="absolute inset-0 h-full w-full" />
            <div className="absolute inset-0" style={{ background: "linear-gradient(90deg,rgba(5,28,36,.98),rgba(8,38,47,.91) 43%,rgba(8,38,47,.28) 75%,rgba(8,38,47,.08))" }} />
            <div className="relative z-10 flex min-h-[650px] items-center px-7 py-20 sm:px-12 lg:min-h-[730px] lg:px-20 xl:px-24">
                <div className="max-w-[770px] text-white">
                    <div className="mb-7 flex items-center gap-3"><span className="h-px w-10" style={{ background: LEDGER.gold }} /><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[11px] font-black uppercase tracking-[.26em] text-white/70" /></div>
                    <Text value={d.heading} fieldPath="heading" cosmicType="h1" className="block text-[clamp(3.1rem,6vw,6.5rem)] font-black leading-[.91] tracking-[-.055em]" />
                    <Text value={d.accent_heading} fieldPath="accent_heading" className="mt-1 block font-serif text-[clamp(2.7rem,5vw,5.5rem)] italic leading-[.95] tracking-[-.035em]" style={{ color: LEDGER.gold }} />
                    <Text value={d.text} fieldPath="text" area className="mt-8 block max-w-[610px] text-base leading-8 text-white/70 sm:text-lg" />
                    <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                        <Button label={d.primary_label} url={d.primary_url} fieldPath="primary_label" className="inline-flex min-h-12 items-center justify-center rounded-full px-7 text-sm font-black text-white shadow-lg" style={{ background: LEDGER.teal2 }} />
                        <Button label={d.secondary_label} url={d.secondary_url} fieldPath="secondary_label" className="inline-flex min-h-12 items-center justify-center rounded-full border border-white/25 bg-white/10 px-7 text-sm font-black text-white backdrop-blur" />
                    </div>
                </div>
            </div>
            <div className="absolute bottom-7 right-7 z-20 hidden w-[520px] grid-cols-3 overflow-hidden rounded-2xl border border-white/20 bg-white/10 backdrop-blur-xl lg:grid">
                {stats.slice(0, 3).map((s, i) => <div key={i} className="border-r border-white/15 px-7 py-6 text-white last:border-r-0"><Text value={s.value} fieldPath={`stats.${i}.value`} className="block text-3xl font-black" /><Text value={s.label} fieldPath={`stats.${i}.label`} className="mt-1 block text-[10px] font-bold uppercase tracking-[.12em] text-white/60" /></div>)}
            </div>
        </div>
    </section>;
}

export const MarketplaceLedgerServicesSchema = {
    type: "marketplace_ledger_services", title: "LedgerPoint Service Grid", category: "Marketplace",
    defaults: { eyebrow: "WHY CHOOSE US", heading: "More than numbers. We deliver clarity.", text: "Straightforward financial support that helps you stay organized, compliant, and ready for what comes next.", items: ledgerServices },
    fields: [F("eyebrow"), F("heading"), F("text", "textarea"), F("items", "repeater")],
};
export function MarketplaceLedgerServicesBlock({ block }) {
    const d = { ...MarketplaceLedgerServicesSchema.defaults, ...block }; const items = Array.isArray(d.items) && d.items.length ? d.items : ledgerServices;
    return <section className="px-6 py-24 lg:px-8 lg:py-32" style={{ background: LEDGER.cream, color: LEDGER.ink }}>
        <div className="mx-auto grid max-w-[1380px] gap-14 lg:grid-cols-[.8fr_1.35fr] lg:gap-20">
            <div><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[11px] font-black uppercase tracking-[.22em]" style={{ color: LEDGER.teal2 }} /><Text value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-5 block max-w-xl text-4xl font-black leading-[1.02] tracking-[-.045em] sm:text-5xl lg:text-6xl" /><Text value={d.text} fieldPath="text" area className="mt-6 block max-w-lg text-base leading-8" style={{ color: LEDGER.muted }} /><span className="mt-9 block h-1 w-20 rounded-full" style={{ background: LEDGER.gold }} /></div>
            <div className="grid gap-5 sm:grid-cols-2">{items.slice(0, 6).map((it, i) => <article key={i} className="rounded-[26px] border bg-white p-7 shadow-[0_18px_50px_rgba(13,50,58,.06)]" style={{ borderColor: LEDGER.line }}><div className="grid h-11 w-11 place-items-center rounded-xl text-xl" style={{ background: "#e6f1f0", color: LEDGER.teal }}><Text value={it.icon || "◇"} fieldPath={`items.${i}.icon`} /></div><Text value={it.title || it.heading} fieldPath={`items.${i}.title`} cosmicType="h3" className="mt-6 block text-xl font-black" /><Text value={it.text || it.description} fieldPath={`items.${i}.text`} area className="mt-3 block text-sm leading-7" style={{ color: LEDGER.muted }} /></article>)}</div>
        </div>
    </section>;
}

export const MarketplaceLedgerStorySchema = {
    type: "marketplace_ledger_story", title: "LedgerPoint Advisory Story", category: "Marketplace",
    defaults: { eyebrow: "A BETTER ACCOUNTING RELATIONSHIP", heading: "Advice that starts with understanding your business.", text: "We combine disciplined accounting with practical commercial thinking. That means fewer surprises, clearer conversations, and advice you can actually use.", image_url: "https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1500&q=86", quote: "Your reports should explain the business — not make it harder to understand." },
    fields: [F("eyebrow"), F("heading"), F("text", "textarea"), F("image_url", "image"), F("quote")],
};
export function MarketplaceLedgerStoryBlock({ block }) { const d = { ...MarketplaceLedgerStorySchema.defaults, ...block }; return <section className="px-6 py-24 lg:px-8 lg:py-32" style={{ background: "#fff", color: LEDGER.ink }}><div className="mx-auto grid max-w-[1380px] items-center gap-14 lg:grid-cols-2 lg:gap-20"><div className="relative"><div className="overflow-hidden rounded-[30px]"><Image src={d.image_url} fieldPath="image_url" className="aspect-[6/5] w-full" /></div><div className="absolute -bottom-7 right-4 max-w-sm rounded-2xl p-6 shadow-2xl" style={{ background: LEDGER.deep, color: "white" }}><Text value={d.quote} fieldPath="quote" className="block font-serif text-xl italic leading-7" /></div></div><div className="pt-6 lg:pt-0"><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[11px] font-black uppercase tracking-[.22em]" style={{ color: LEDGER.teal2 }} /><Text value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-5 block text-4xl font-black leading-[1.03] tracking-[-.045em] sm:text-5xl" /><Text value={d.text} fieldPath="text" area className="mt-6 block text-base leading-8" style={{ color: LEDGER.muted }} /></div></div></section>; }

export const MarketplaceLedgerProofSchema = {
    type: "marketplace_ledger_proof", title: "LedgerPoint Proof & Process", category: "Marketplace",
    defaults: { eyebrow: "PROVEN, PRACTICAL, PERSONAL", heading: "Financial confidence you can see.", stats: [{ value: "12+", label: "Years experience" }, { value: "150+", label: "Active clients" }, { value: "99%", label: "On-time compliance" }, { value: "4.9/5", label: "Average rating" }], steps: [{ title: "Understand", text: "We learn how your business works and what you need from your numbers." }, { title: "Organize", text: "We establish reliable records, reporting, and compliance rhythms." }, { title: "Advise", text: "We turn the numbers into priorities and clear next steps." }] },
    fields: [F("eyebrow"), F("heading"), F("stats", "repeater"), F("steps", "repeater")],
};
export function MarketplaceLedgerProofBlock({ block }) { const d = { ...MarketplaceLedgerProofSchema.defaults, ...block }; const stats = Array.isArray(d.stats) ? d.stats : MarketplaceLedgerProofSchema.defaults.stats; const steps = Array.isArray(d.steps) ? d.steps : MarketplaceLedgerProofSchema.defaults.steps; return <section className="px-6 py-24 lg:px-8" style={{ background: LEDGER.deep, color: "white" }}><div className="mx-auto max-w-[1380px]"><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: LEDGER.gold }} /><Text value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-4 block max-w-3xl text-4xl font-black tracking-[-.04em] sm:text-5xl" /><div className="mt-10 grid grid-cols-2 overflow-hidden rounded-2xl border border-white/10 md:grid-cols-4">{stats.slice(0, 4).map((s, i) => <div key={i} className="border-r border-white/10 p-6 last:border-r-0"><Text value={s.value} fieldPath={`stats.${i}.value`} className="block text-3xl font-black" /><Text value={s.label} fieldPath={`stats.${i}.label`} className="mt-2 block text-xs text-white/50" /></div>)}</div><div className="mt-10 grid gap-4 lg:grid-cols-3">{steps.slice(0, 3).map((s, i) => <article key={i} className="rounded-2xl border border-white/10 bg-white/5 p-7"><span className="text-[10px] font-black tracking-[.16em]" style={{ color: LEDGER.gold }}>{String(i + 1).padStart(2, "0")}</span><Text value={s.title} fieldPath={`steps.${i}.title`} cosmicType="h3" className="mt-8 block text-2xl font-black" /><Text value={s.text} fieldPath={`steps.${i}.text`} area className="mt-3 block text-sm leading-7 text-white/60" /></article>)}</div></div></section>; }

export const MarketplaceLedgerFaqSchema = {
    type: "marketplace_ledger_faq", title: "LedgerPoint FAQ & Contact", category: "Marketplace",
    defaults: { eyebrow: "COMMON QUESTIONS", heading: "Useful answers before we get started.", items: [{ question: "Can you take over from my current accountant?", answer: "Yes. We can coordinate a straightforward handover and organize the records needed to get started." }, { question: "Do you work with growing businesses?", answer: "Yes. Support can scale from bookkeeping and compliance through to forecasting and advisory." }, { question: "Can you help with payroll and reporting?", answer: "Yes. Payroll, management reporting, and regular financial reviews can all be included." }, { question: "How do fees work?", answer: "We scope the work around your needs and explain recurring and one-off costs clearly before engagement." }], button_label: "Request a Consultation", button_url: "/contact" },
    fields: [F("eyebrow"), F("heading"), F("items", "repeater"), F("button_label"), F("button_url")],
};
export function MarketplaceLedgerFaqBlock({ block }) { const d = { ...MarketplaceLedgerFaqSchema.defaults, ...block }; const items = Array.isArray(d.items) ? d.items : MarketplaceLedgerFaqSchema.defaults.items; return <section className="px-6 py-24 lg:px-8" style={{ background: LEDGER.cream, color: LEDGER.ink }}><div className="mx-auto grid max-w-[1280px] gap-12 lg:grid-cols-[.75fr_1.25fr]"><div><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: LEDGER.teal2 }} /><Text value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-5 block text-4xl font-black leading-[1.04] tracking-[-.04em] sm:text-5xl" /><Button label={d.button_label} url={d.button_url} fieldPath="button_label" className="mt-8 inline-flex min-h-11 items-center rounded-full px-6 text-sm font-black text-white" style={{ background: LEDGER.teal }} /></div><div>{items.slice(0, 8).map((it, i) => <article key={i} className="border-b py-6 first:pt-0" style={{ borderColor: LEDGER.line }}><Text value={it.question} fieldPath={`items.${i}.question`} cosmicType="h3" className="block text-lg font-black" /><Text value={it.answer} fieldPath={`items.${i}.answer`} area className="mt-2 block text-sm leading-7" style={{ color: LEDGER.muted }} /></article>)}</div></div></section>; }

export const MarketplaceLedgerContactSchema = {
    type: "marketplace_ledger_contact", title: "LedgerPoint Consultation Contact", category: "Marketplace",
    defaults: { eyebrow: "START A CONVERSATION", heading: "Ready to make the next step clear?", text: "Tell us what you need help with and we’ll point you toward the right accounting support.", phone: "(02) 5550 0148", email: "hello@ledgerpoint.example", address: "Level 6 · 42 Market Street · Sydney NSW", hours: "Mon–Fri · 8:30am–5:30pm", button_label: "Request a Consultation", button_url: "mailto:hello@ledgerpoint.example", image_url: "https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1500&q=86" },
    fields: [F("eyebrow"), F("heading"), F("text", "textarea"), F("phone"), F("email"), F("address"), F("hours"), F("button_label"), F("button_url"), F("image_url", "image")],
};
export function MarketplaceLedgerContactBlock({ block }) { const d = { ...MarketplaceLedgerContactSchema.defaults, ...block }; return <section className="px-6 py-24 lg:px-8 lg:py-32" style={{ background: "#fff", color: LEDGER.ink }}><div className="mx-auto grid max-w-[1380px] overflow-hidden rounded-[30px] border lg:grid-cols-[.9fr_1.1fr]" style={{ borderColor: LEDGER.line }}><div className="p-8 sm:p-12 lg:p-14" style={{ background: LEDGER.deep, color: "white" }}><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: LEDGER.gold }} /><Text value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-5 block text-4xl font-black leading-[1.02] tracking-[-.045em] sm:text-5xl" /><Text value={d.text} fieldPath="text" area className="mt-6 block max-w-lg text-base leading-8 text-white/60" /><div className="mt-9 grid gap-3 border-t border-white/10 pt-7 text-sm"><Text value={d.phone} fieldPath="phone" className="font-black" /><Text value={d.email} fieldPath="email" className="font-black" /><Text value={d.address} fieldPath="address" className="text-white/60" /><Text value={d.hours} fieldPath="hours" className="text-white/60" /></div><Button label={d.button_label} url={d.button_url} fieldPath="button_label" className="mt-9 inline-flex min-h-12 items-center rounded-full px-7 text-sm font-black text-white" style={{ background: LEDGER.teal2 }} /></div><div className="min-h-[520px] overflow-hidden"><Image src={d.image_url} fieldPath="image_url" className="h-full min-h-[520px] w-full" /></div></div></section>; }

export const MarketplaceLedgerCtaSchema = {
    type: "marketplace_ledger_cta", title: "LedgerPoint CTA", category: "Marketplace",
    defaults: { eyebrow: "READY WHEN YOU ARE", heading: "Make the next financial decision with more clarity.", text: "A better accounting relationship starts with a useful conversation.", button_label: "Book a Consultation", button_url: "/contact" },
    fields: [F("eyebrow"), F("heading"), F("text", "textarea"), F("button_label"), F("button_url")],
};
export function MarketplaceLedgerCtaBlock({ block }) { const d = { ...MarketplaceLedgerCtaSchema.defaults, ...block }; return <section className="px-6 py-16 lg:px-8" style={{ background: LEDGER.cream }}><div className="mx-auto flex max-w-[1380px] flex-col gap-8 rounded-[30px] px-8 py-12 text-white sm:px-12 lg:flex-row lg:items-end lg:justify-between lg:px-16" style={{ background: LEDGER.teal }}><div className="max-w-3xl"><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.24em] text-white/60" /><Text value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-4 block text-4xl font-black leading-[1.02] tracking-[-.04em] sm:text-5xl" /><Text value={d.text} fieldPath="text" area className="mt-4 block text-base text-white/60" /></div><Button label={d.button_label} url={d.button_url} fieldPath="button_label" className="inline-flex min-h-12 shrink-0 items-center rounded-full bg-white px-7 text-sm font-black" style={{ color: LEDGER.teal }} /></div></section>; }

export const MarketplaceLedgerPageHeroSchema = { type: "marketplace_ledger_page_hero", title: "LedgerPoint Page Hero", category: "Marketplace", defaults: { eyebrow: "LEDGERPOINT ACCOUNTING", heading: "Accounting with clarity and confidence.", text: "Practical support, clear communication, and reliable financial information for better business decisions." }, fields: [F("eyebrow"), F("heading"), F("text", "textarea")] };
export function MarketplaceLedgerPageHeroBlock({ block }) { const d = { ...MarketplaceLedgerPageHeroSchema.defaults, ...block }; return <section className="px-6 py-20 lg:px-8 lg:py-28" style={{ background: LEDGER.deep, color: "white" }}><div className="mx-auto max-w-[1380px]"><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: LEDGER.gold }} /><Text value={d.heading} fieldPath="heading" cosmicType="h1" className="mt-5 block max-w-5xl text-[clamp(3.2rem,6vw,6.5rem)] font-black leading-[.94] tracking-[-.055em]" /><Text value={d.text} fieldPath="text" area className="mt-7 block max-w-2xl text-base leading-8 text-white/60 sm:text-lg" /></div></section>; }

const menuDefaults = [
    { title: "Wood-Fired Octopus", text: "Smoked paprika · charred lemon · olive relish", price: "$28", image_url: "https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=900&q=88" },
    { title: "Herb-Roasted Chicken", text: "Baby carrots · romesco · rosemary jus", price: "$32", image_url: "https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=900&q=88" },
    { title: "Seared Scallops", text: "Cauliflower puree · brown butter · crispy capers", price: "$36", image_url: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=900&q=88" },
    { title: "Handmade Pappardelle", text: "Wild mushrooms · truffle cream · parmigiano", price: "$30", image_url: "https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=900&q=88" },
    { title: "Olive Oil Cake", text: "Citrus · mascarpone · rosemary honey", price: "$14", image_url: "https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=900&q=88" },
];

const emberFeatureDefaults = [
    { icon: "◌", title: "Seasonal Ingredients", text: "Thoughtfully sourced from local farms and trusted producers." },
    { icon: "♨", title: "Chef-Driven Menu", text: "Creative dishes inspired by fire, flavor, and the changing seasons." },
    { icon: "◇", title: "Private Events", text: "Intimate gatherings and celebrations, beautifully tailored." },
    { icon: "⌁", title: "Handcrafted Cocktails", text: "Curated pours and original creations, mixed with care." },
];

const emberGalleryDefaults = [
    { image_url: "https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=900&q=88", alt: "Handcrafted cocktail" },
    { image_url: "https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=900&q=88", alt: "Dining room" },
    { image_url: "https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=900&q=88", alt: "Seasonal plate" },
    { image_url: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=900&q=88", alt: "Guests at dinner" },
    { image_url: "https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=900&q=88", alt: "Open fire kitchen" },
    { image_url: "https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=900&q=88", alt: "Dessert" },
];

export const MarketplaceEmberHeroSchema = {
    type: "marketplace_ember_hero", title: "Ember & Olive Fire-Crafted Hero", category: "Marketplace",
    defaults: {
        eyebrow: "EMBER & OLIVE · PORTLAND",
        heading: "Fire-crafted food.", accent_heading: "Gathered moments.",
        text: "Seasonal ingredients, open flames, and warm hospitality. A dining experience rooted in craft and connection.",
        primary_label: "Reserve a Table", primary_url: "/contact", secondary_label: "View Menu", secondary_url: "/menu",
        image_url: "https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=2000&q=90",
    },
    fields: [F("eyebrow"), F("heading"), F("accent_heading"), F("text", "textarea"), F("primary_label"), F("primary_url"), F("secondary_label"), F("secondary_url"), F("image_url", "image")],
};
export function MarketplaceEmberHeroBlock({ block }) {
    const d = { ...MarketplaceEmberHeroSchema.defaults, ...block };
    return <section data-ember-surface="hero" data-ember-tone="dark" className="marketplace-ember-home cosmic-tw-own-section-y relative min-h-[630px] overflow-hidden lg:min-h-[690px]" style={{ background: EMBER.deep, color: EMBER.cream }}>
        <Image src={d.image_url} fieldPath="image_url" background className="absolute inset-0 h-full w-full" />
        <div className="absolute inset-0" style={{ background: "linear-gradient(90deg,rgba(12,12,9,.99) 0%,rgba(16,16,11,.94) 30%,rgba(18,18,12,.72) 49%,rgba(16,15,10,.18) 76%,rgba(9,9,7,.08) 100%)" }} />
        <div className="relative z-10 mx-auto flex min-h-[630px] max-w-[1360px] items-center px-6 py-20 sm:px-8 lg:min-h-[690px] lg:px-12">
            <div className="max-w-[680px]">
                <div className="flex items-center gap-4"><span className="h-px w-12" style={{ background: "#d7a14f" }} /><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-bold uppercase tracking-[.24em] text-white/60" /></div>
                <Text value={d.heading} fieldPath="heading" cosmicType="h1" className="mt-8 block font-serif text-[clamp(3.8rem,6.1vw,6.75rem)] leading-[.92] tracking-[-.045em] text-white" />
                <Text value={d.accent_heading} fieldPath="accent_heading" className="block font-serif text-[clamp(3.8rem,6.1vw,6.75rem)] leading-[.92] tracking-[-.045em]" style={{ color: "#e0ad58" }} />
                <div className="mt-8 flex items-center gap-3"><span className="h-px w-12" style={{ background: "#d7a14f" }} /><span className="text-sm" style={{ color: "#d7a14f" }}>✦</span></div>
                <Text value={d.text} fieldPath="text" area className="mt-6 block max-w-[500px] text-[15px] leading-7 text-white/70 sm:text-base" />
                <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Button label={d.primary_label} url={d.primary_url} fieldPath="primary_label" className="inline-flex min-h-12 items-center justify-center rounded-[4px] px-7 text-sm font-bold text-[#15140f] shadow-[0_12px_30px_rgba(0,0,0,.24)]" style={{ background: "linear-gradient(180deg,#e7bd71,#c98d36)" }} />
                    <Button label={d.secondary_label} url={d.secondary_url} fieldPath="secondary_label" className="inline-flex min-h-12 items-center justify-center rounded-[4px] border border-white/30 bg-black/15 px-7 text-sm font-semibold text-white backdrop-blur-sm" />
                </div>
            </div>
        </div>
    </section>;
}

export const MarketplaceEmberFeatureSchema = {
    type: "marketplace_ember_feature", title: "Ember & Olive Experience Strip", category: "Marketplace",
    defaults: { items: emberFeatureDefaults },
    fields: [F("items", "repeater")],
};
export function MarketplaceEmberFeatureBlock({ block }) {
    const d = { ...MarketplaceEmberFeatureSchema.defaults, ...block };
    const items = Array.isArray(d.items) && d.items.length ? d.items : emberFeatureDefaults;
    return <section data-ember-surface="feature" data-ember-tone="dark" className="marketplace-ember-home cosmic-tw-own-section-y border-y px-6 sm:px-8 lg:px-12" style={{ background: "linear-gradient(90deg,#292819,#34321b 45%,#292819)", borderColor: "rgba(215,161,79,.22)", color: EMBER.cream }}>
        <div className="mx-auto grid max-w-[1360px] md:grid-cols-2 xl:grid-cols-4">
            {items.slice(0, 4).map((it, i) => <article key={i} className="relative px-7 py-8 text-center xl:px-9 xl:py-9">
                {i > 0 && <span className="absolute left-0 top-[22%] hidden h-[56%] w-px xl:block" style={{ background: "rgba(238,211,158,.18)" }} />}
                <Text value={it.icon || "✦"} fieldPath={`items.${i}.icon`} className="block text-3xl font-light" style={{ color: "#d5a153" }} />
                <Text value={it.title} fieldPath={`items.${i}.title`} cosmicType="h3" className="mt-4 block font-serif text-lg text-white" />
                <Text value={it.text} fieldPath={`items.${i}.text`} area className="mx-auto mt-2 block max-w-[280px] text-xs leading-5 text-white/60" />
            </article>)}
        </div>
    </section>;
}

export const MarketplaceEmberStorySchema = {
    type: "marketplace_ember_story", title: "Ember & Olive Story Split", category: "Marketplace",
    defaults: {
        eyebrow: "OUR STORY", heading: "Rooted in fire. Inspired by tradition.",
        text: "Ember & Olive is where timeless techniques meet modern flair. Our open kitchen, warm ambience, and genuine hospitality create the perfect setting for memorable meals and meaningful moments.",
        image_url: "https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=1500&q=90",
        quote: "Good food brings people together. Great food leaves a lasting impression.", quote_by: "Chef & Founder, Marcus Hale",
    },
    fields: [F("eyebrow"), F("heading"), F("text", "textarea"), F("image_url", "image"), F("quote", "textarea"), F("quote_by")],
};
export function MarketplaceEmberStoryBlock({ block }) {
    const d = { ...MarketplaceEmberStorySchema.defaults, ...block };
    return <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home cosmic-tw-own-section-y px-6 py-20 sm:px-8 lg:px-12 lg:py-24" style={{ background: "#f5f0e7", color: "#221b16" }}>
        <div className="mx-auto grid max-w-[1360px] items-center gap-12 lg:grid-cols-[.78fr_1.22fr] lg:gap-16">
            <div className="lg:pl-4">
                <Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: "#9e6539" }} />
                <Text value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-4 block max-w-[560px] font-serif text-[clamp(2.7rem,4vw,4.5rem)] leading-[.98] tracking-[-.035em]" />
                <div className="mt-7 flex items-center gap-3"><span className="h-px w-12" style={{ background: "#b47b43" }} /><span style={{ color: "#b47b43" }}>✦</span></div>
                <Text value={d.text} fieldPath="text" area className="mt-6 block max-w-[560px] text-sm leading-7" style={{ color: "#62574f" }} />
                <div className="mt-7 max-w-[540px] border-l-2 pl-5" style={{ borderColor: "#b47b43" }}>
                    <Text value={`“${d.quote}”`} fieldPath="quote" area className="block font-serif text-lg italic leading-7" style={{ color: "#7b4b2e" }} />
                    <Text value={`— ${d.quote_by}`} fieldPath="quote_by" className="mt-2 block text-[10px] font-bold uppercase tracking-[.12em]" style={{ color: "#8b8077" }} />
                </div>
            </div>
            <div className="overflow-hidden rounded-[3px] shadow-[0_26px_70px_rgba(45,32,22,.14)]"><Image src={d.image_url} fieldPath="image_url" className="aspect-[16/9] w-full lg:aspect-[5/3]" /></div>
        </div>
    </section>;
}

export const MarketplaceEmberMenuSchema = {
    type: "marketplace_ember_menu", title: "Ember & Olive Signature Menu Cards", category: "Marketplace",
    defaults: { eyebrow: "SIGNATURE EXPERIENCE", heading: "From our kitchen to your table.", items: menuDefaults, button_label: "View Full Menu", button_url: "/menu" },
    fields: [F("eyebrow"), F("heading"), F("items", "repeater"), F("button_label"), F("button_url")],
};
export function MarketplaceEmberMenuBlock({ block }) {
    const d = { ...MarketplaceEmberMenuSchema.defaults, ...block };
    const items = Array.isArray(d.items) && d.items.length ? d.items : menuDefaults;
    return <section data-ember-surface="menu" data-ember-tone="dark" className="marketplace-ember-home cosmic-tw-own-section-y relative overflow-hidden px-6 py-20 sm:px-8 lg:px-12 lg:py-24" style={{ background: "linear-gradient(115deg,#151612,#1f2016 68%,#2b2b19)", color: "#f3ecdf" }}>
        <div className="pointer-events-none absolute -right-16 bottom-0 text-[220px] leading-none opacity-[.045]">❧</div>
        <div className="relative mx-auto max-w-[1360px]">
            <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: "#d5a153" }} /><Text value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-3 block font-serif text-[clamp(2.6rem,4vw,4.5rem)] leading-[.96] tracking-[-.035em] text-white" /></div>
                <Button label={d.button_label} url={d.button_url} fieldPath="button_label" className="inline-flex min-h-10 items-center justify-center rounded-[3px] border border-white/30 px-5 text-xs font-semibold text-white" />
            </div>
            <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                {items.slice(0, 5).map((it, i) => <article key={i} className="overflow-hidden rounded-[3px] border" style={{ borderColor: "rgba(222,190,130,.18)", background: "#1a1b16" }}>
                    <Image src={it.image_url} fieldPath={`items.${i}.image_url`} className="aspect-[5/3] w-full" />
                    <div className="p-4"><Text value={it.title} fieldPath={`items.${i}.title`} cosmicType="h3" className="block font-serif text-[17px] text-white" /><Text value={it.text} fieldPath={`items.${i}.text`} area className="mt-2 block min-h-[42px] text-[11px] leading-5 text-white/60" /><Text value={it.price} fieldPath={`items.${i}.price`} className="mt-3 block font-serif text-sm" style={{ color: "#d4a257" }} /></div>
                </article>)}
            </div>
        </div>
    </section>;
}

export const MarketplaceEmberPrivateDiningSchema = {
    type: "marketplace_ember_private_dining", title: "Ember & Olive Private Dining", category: "Marketplace",
    defaults: { eyebrow: "PRIVATE DINING", heading: "Celebrate in our space.", text: "From intimate dinners to milestone celebrations, our private dining experiences are tailored to you. Exceptional food, attentive service, and an atmosphere your guests won’t forget.", button_label: "Inquire About Events", button_url: "/private-dining", image_url: "https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=1500&q=90" },
    fields: [F("eyebrow"), F("heading"), F("text", "textarea"), F("button_label"), F("button_url"), F("image_url", "image")],
};
export function MarketplaceEmberPrivateDiningBlock({ block }) {
    const d = { ...MarketplaceEmberPrivateDiningSchema.defaults, ...block };
    return <section data-ember-surface="private" data-ember-tone="dark" className="marketplace-ember-home cosmic-tw-own-section-y px-6 py-16 sm:px-8 lg:px-12 lg:py-20" style={{ background: "#3a3a20", color: "#f5ecdd" }}>
        <div className="mx-auto grid max-w-[1360px] overflow-hidden lg:grid-cols-[.96fr_1.04fr]">
            <div className="min-h-[390px] overflow-hidden"><Image src={d.image_url} fieldPath="image_url" className="h-full min-h-[390px] w-full" /></div>
            <div className="relative flex items-center px-8 py-16 sm:px-12 lg:px-16"><div className="pointer-events-none absolute bottom-0 right-4 text-[190px] leading-none opacity-[.055]">❧</div><div className="relative max-w-[650px]"><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: "#d8a352" }} /><Text value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-3 block font-serif text-[clamp(2.7rem,4vw,4.7rem)] leading-[.96] text-white" /><Text value={d.text} fieldPath="text" area className="mt-5 block max-w-[610px] text-sm leading-7 text-white/60" /><Button label={d.button_label} url={d.button_url} fieldPath="button_label" className="mt-7 inline-flex min-h-11 items-center rounded-[3px] px-6 text-xs font-bold text-[#17150e]" style={{ background: "linear-gradient(180deg,#e8bd70,#c88c35)" }} /></div></div>
        </div>
    </section>;
}

export const MarketplaceEmberReviewsSchema = {
    type: "marketplace_ember_reviews", title: "Ember & Olive Editorial Testimonial", category: "Marketplace",
    defaults: { quote: "Every detail was perfect. The food, the service, the ambience—Ember & Olive is our new favorite place.", name: "Jessica L.", role: "Guest" },
    fields: [F("quote", "textarea"), F("name"), F("role")],
};
export function MarketplaceEmberReviewsBlock({ block }) {
    const d = { ...MarketplaceEmberReviewsSchema.defaults, ...block };
    return <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home cosmic-tw-own-section-y px-6 py-12 text-center sm:px-8 lg:py-14" style={{ background: "#f5f0e7", color: "#2c211a" }}><div className="mx-auto max-w-[980px]"><div className="font-serif text-5xl leading-none" style={{ color: "#9f633f" }}>“</div><Text value={`“${d.quote}”`} fieldPath="quote" area className="mx-auto -mt-2 block max-w-[900px] font-serif text-[clamp(1.5rem,2.3vw,2.45rem)] leading-[1.18]" /><Text value={`— ${d.name}, ${d.role}`} fieldPath="name" className="mt-5 block text-[9px] font-black uppercase tracking-[.16em]" style={{ color: "#9f633f" }} /></div></section>;
}

export const MarketplaceEmberGallerySchema = {
    type: "marketplace_ember_gallery", title: "Ember & Olive Image Rail", category: "Marketplace",
    defaults: { images: emberGalleryDefaults }, fields: [F("images", "repeater")],
};
export function MarketplaceEmberGalleryBlock({ block }) {
    const d = { ...MarketplaceEmberGallerySchema.defaults, ...block };
    const images = Array.isArray(d.images) && d.images.length ? d.images : emberGalleryDefaults;
    return <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home cosmic-tw-own-section-y px-6 pb-6 sm:px-8 sm:pb-8 lg:px-12 lg:pb-12" style={{ background: "#f5f0e7" }}><div className="mx-auto grid max-w-[1360px] grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">{images.slice(0, 6).map((it, i) => <div key={i} className="overflow-hidden rounded-[2px]"><Image src={typeof it === "string" ? it : it.image_url} fieldPath={`images.${i}.image_url`} className="aspect-[4/3] w-full transition duration-500 hover:scale-[1.03]" /></div>)}</div></section>;
}

export const MarketplaceEmberReservationSchema = {
    type: "marketplace_ember_reservation", title: "Ember & Olive Reservation Form", category: "Marketplace",
    defaults: { eyebrow: "RESERVATIONS", heading: "We’ll save you a seat.", text: "Join us for an unforgettable dining experience. Reserve your table and let the evening begin.", phone: "(555) 123-4567", email: "hello@emberandolive.com", button_label: "Find a Table", image_url: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=1200&q=88" },
    fields: [F("eyebrow"), F("heading"), F("text", "textarea"), F("phone"), F("email"), F("button_label"), F("image_url", "image")],
};
export function MarketplaceEmberReservationBlock({ block }) {
    const d = { ...MarketplaceEmberReservationSchema.defaults, ...block };
    const fieldClass = "min-h-11 w-full rounded-[2px] border px-4 text-xs outline-none";
    return <section id="reserve" data-ember-surface="reservation" data-ember-tone="light" className="marketplace-ember-home cosmic-tw-own-section-y px-6 py-16 sm:px-8 lg:px-12 lg:py-20" style={{ background: "#f8f3ea", color: "#2a241b" }}>
        <div className="mx-auto grid max-w-[1360px] overflow-hidden lg:grid-cols-[.78fr_1.22fr]">
            <div className="min-h-[370px] overflow-hidden"><Image src={d.image_url} fieldPath="image_url" className="h-full min-h-[370px] w-full" /></div>
            <div className="grid gap-10 px-8 py-14 sm:px-12 xl:grid-cols-[.8fr_1.2fr] xl:px-16"><div><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: "#b77d2f" }} /><Text value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-3 block font-serif text-[clamp(2.6rem,3.7vw,4.1rem)] leading-[.98]" style={{ color: "#2a241b" }} /><Text value={d.text} fieldPath="text" area className="mt-5 block max-w-md text-sm leading-6" style={{ color: "#6b6255" }} /><div className="mt-6 flex flex-wrap gap-x-7 gap-y-2 text-xs"><Text value={d.phone} fieldPath="phone" className="font-semibold" style={{ color: "#b77d2f" }} /><Text value={d.email} fieldPath="email" className="font-semibold" style={{ color: "#b77d2f" }} /></div></div>
            <div className="grid content-center grid-cols-1 gap-2 sm:grid-cols-2"><input className={fieldClass} style={{ borderColor: "#cfc3b4", background: "#fffaf3", color: "#5f5549" }} placeholder="Date" readOnly /><input className={fieldClass} style={{ borderColor: "#cfc3b4", background: "#fffaf3", color: "#5f5549" }} placeholder="Time" readOnly /><input className={fieldClass} style={{ borderColor: "#cfc3b4", background: "#fffaf3", color: "#5f5549" }} placeholder="Party Size" readOnly /><input className={fieldClass} style={{ borderColor: "#cfc3b4", background: "#fffaf3", color: "#5f5549" }} placeholder="Your Name" readOnly /><input className={fieldClass} style={{ borderColor: "#cfc3b4", background: "#fffaf3", color: "#5f5549" }} placeholder="Email" readOnly /><input className={fieldClass} style={{ borderColor: "#cfc3b4", background: "#fffaf3", color: "#5f5549" }} placeholder="Phone" readOnly /><Button label={d.button_label} url="#reserve" fieldPath="button_label" className="sm:col-span-2 inline-flex min-h-11 items-center justify-center rounded-[2px] text-xs font-bold text-[#17150e]" style={{ background: "linear-gradient(180deg,#e7bb6d,#c98e37)" }} /></div></div>
        </div>
    </section>;
}

export const MarketplaceEmberLocationSchema = {
    type: "marketplace_ember_location", title: "Ember & Olive Location & Hours", category: "Marketplace",
    defaults: { address_heading: "Find Us", address: "123 Hearthwood Lane\nPortland, OR 97201", directions_label: "Get Directions", hours_heading: "Hours", hours: "Mon – Thu     5:00pm – 10:00pm\nFri – Sat       5:00pm – 11:00pm\nSunday          Closed", image_url: "https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=1000&q=88" },
    fields: [F("address_heading"), F("address", "textarea"), F("directions_label"), F("hours_heading"), F("hours", "textarea"), F("image_url", "image")],
};
export function MarketplaceEmberLocationBlock({ block }) {
    const d = { ...MarketplaceEmberLocationSchema.defaults, ...block };
    return <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home cosmic-tw-own-section-y px-6 py-10 sm:px-8" style={{ background: "#f5f0e7", color: "#332820" }}><div className="mx-auto grid max-w-[1360px] gap-8 lg:grid-cols-[.8fr_1.05fr_.85fr] lg:items-center"><div className="flex gap-4"><div className="mt-1 text-xl" style={{ color: "#a8663d" }}>⌖</div><div><Text value={d.address_heading} fieldPath="address_heading" cosmicType="h3" className="block font-serif text-xl" /><Text value={d.address} fieldPath="address" area className="mt-2 block whitespace-pre-line text-xs leading-5" style={{ color: "#65594f" }} /><Text value={d.directions_label} fieldPath="directions_label" className="mt-2 block text-xs font-bold" style={{ color: "#9d5e38" }} /></div></div><div className="flex gap-4 border-y py-7 lg:border-x lg:border-y-0 lg:px-10 lg:py-0" style={{ borderColor: "#cfc3b4" }}><div className="mt-1 text-xl" style={{ color: "#a8663d" }}>◷</div><div><Text value={d.hours_heading} fieldPath="hours_heading" cosmicType="h3" className="block font-serif text-xl" /><Text value={d.hours} fieldPath="hours" area className="mt-2 block whitespace-pre-line text-xs leading-5" style={{ color: "#65594f" }} /></div></div><div className="overflow-hidden rounded-[2px]"><Image src={d.image_url} fieldPath="image_url" className="aspect-[16/7] w-full lg:aspect-[16/8]" /></div></div></section>;
}

export const MarketplaceEmberContactSchema = {
    type: "marketplace_ember_contact", title: "Ember & Olive Contact", category: "Marketplace",
    defaults: { eyebrow: "RESERVATIONS & PRIVATE DINING", heading: "Your table is waiting.", text: "Reserve online or contact us for group dining, celebrations, and private events.", phone: "(02) 5550 0184", email: "hello@emberandolive.example", address: "18 Willow Lane · Surry Hills NSW", hours: "Tue–Thu 5:30–10 · Fri–Sun 12–10:30", button_label: "Reserve a Table", button_url: "#reserve", image_url: "https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=1400&q=88" },
    fields: [F("eyebrow"), F("heading"), F("text", "textarea"), F("phone"), F("email"), F("address"), F("hours"), F("button_label"), F("button_url"), F("image_url", "image")],
};
export function MarketplaceEmberContactBlock({ block }) { const d = { ...MarketplaceEmberContactSchema.defaults, ...block }; return <section id="reserve" className="px-6 py-24 lg:px-8" style={{ background: EMBER.cream, color: EMBER.ink }}><div className="mx-auto grid max-w-[1380px] gap-4 lg:grid-cols-[.9fr_1.1fr]"><div className="rounded-[28px] p-8 sm:p-12 lg:p-14" style={{ background: EMBER.deep, color: EMBER.cream }}><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: EMBER.copper2 }} /><Text value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-5 block font-serif text-5xl leading-[.95] sm:text-6xl" /><Text value={d.text} fieldPath="text" area className="mt-6 block text-base leading-8 text-white/60" /><div className="mt-9 grid gap-3 border-t border-white/10 pt-7 text-sm"><Text value={d.phone} fieldPath="phone" className="font-bold" /><Text value={d.email} fieldPath="email" className="font-bold" /><Text value={d.address} fieldPath="address" className="text-white/50" /><Text value={d.hours} fieldPath="hours" className="text-white/50" /></div><Button label={d.button_label} url={d.button_url} fieldPath="button_label" className="mt-9 inline-flex min-h-12 items-center rounded-full px-7 text-sm font-black text-white" style={{ background: EMBER.copper }} /></div><div className="overflow-hidden rounded-[28px]"><Image src={d.image_url} fieldPath="image_url" className="min-h-[540px] w-full" /></div></div></section>; }

export const MarketplaceEmberCtaSchema = {
    type: "marketplace_ember_cta", title: "Ember & Olive Reservation CTA", category: "Marketplace",
    defaults: { eyebrow: "COME JOIN US", heading: "Dinner tastes better when the table is full.", text: "Book your next lunch, dinner, or celebration at Ember & Olive.", button_label: "Reserve a Table", button_url: "/contact" },
    fields: [F("eyebrow"), F("heading"), F("text", "textarea"), F("button_label"), F("button_url")],
};
export function MarketplaceEmberCtaBlock({ block }) { const d = { ...MarketplaceEmberCtaSchema.defaults, ...block }; return <section className="px-6 py-16 lg:px-8" style={{ background: EMBER.deep }}><div className="mx-auto grid max-w-[1380px] gap-8 rounded-[30px] px-8 py-12 sm:px-12 lg:grid-cols-[1fr_auto] lg:items-end lg:px-16" style={{ background: EMBER.copper, color: "white" }}><div><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.24em] text-white/60" /><Text value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-4 block max-w-4xl font-serif text-5xl leading-[.93] sm:text-6xl" /><Text value={d.text} fieldPath="text" area className="mt-4 block text-base text-white/70" /></div><Button label={d.button_label} url={d.button_url} fieldPath="button_label" className="inline-flex min-h-12 items-center rounded-full bg-white px-7 text-sm font-black" style={{ color: EMBER.deep }} /></div></section>; }

export const MarketplaceEmberPageHeroSchema = { type: "marketplace_ember_page_hero", title: "Ember & Olive Page Hero", category: "Marketplace", defaults: { eyebrow: "EMBER & OLIVE", heading: "Seasonal dining, thoughtfully done.", text: "Food with character, generous hospitality, and a room made for gathering." }, fields: [F("eyebrow"), F("heading"), F("text", "textarea")] };
export function MarketplaceEmberPageHeroBlock({ block }) { const d = { ...MarketplaceEmberPageHeroSchema.defaults, ...block }; return <section className="px-6 py-20 lg:px-8 lg:py-28" style={{ background: EMBER.deep, color: EMBER.cream }}><div className="mx-auto max-w-[1380px]"><Text value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: EMBER.copper2 }} /><Text value={d.heading} fieldPath="heading" cosmicType="h1" className="mt-5 block max-w-5xl font-serif text-[clamp(3.8rem,7vw,7.2rem)] leading-[.89] tracking-[-.055em]" /><Text value={d.text} fieldPath="text" area className="mt-7 block max-w-2xl text-base leading-8 text-white/60 sm:text-lg" /></div></section>; }

const emberAboutValues = [
    { icon: "♨", title: "Fire", text: "We cook over live fire and embers to create depth, nuance, and unforgettable flavor." },
    { icon: "◇", title: "Seasonality", text: "Our menus change with the seasons so we can highlight ingredients at their peak." },
    { icon: "⌁", title: "Craft", text: "Thoughtful technique, time-honored skills, and careful attention in every detail." },
    { icon: "❧", title: "Hospitality", text: "Warmth, generosity, and a genuine connection are at the heart of every experience." },
];
const emberAboutMilestones = [
    { year: "2016", title: "The beginning", text: "A wood-fired oven and a vision." },
    { year: "2017", title: "First home", text: "We opened our doors in Portland." },
    { year: "2019", title: "Growing roots", text: "Built lasting partnerships with local farms." },
    { year: "2021", title: "Private dining", text: "Welcomed guests to our private table." },
    { year: "2023", title: "National recognition", text: "Honored for our cuisine and hospitality." },
    { year: "Today", title: "Looking ahead", text: "Continuing to evolve, stay curious, and serve our community." },
];
const emberAboutTeam = [
    { name: "Daniel Brooks", role: "Chef & Owner", image_url: "https://images.unsplash.com/photo-1583394293214-28ded15ee548?auto=format&fit=crop&w=700&q=88" },
    { name: "Maya Lin", role: "Sous Chef", image_url: "https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=700&q=88" },
    { name: "Ethan Caldwell", role: "General Manager", image_url: "https://images.unsplash.com/photo-1577219491135-ce391730fb2c?auto=format&fit=crop&w=700&q=88" },
    { name: "Sophie Mercier", role: "Beverage Director", image_url: "https://images.unsplash.com/photo-1581299894007-aaa50297cf16?auto=format&fit=crop&w=700&q=88" },
];

export const MarketplaceEmberAboutSchema = {
    type: "marketplace_ember_about", title: "Ember & Olive Editorial About Page", category: "Marketplace",
    defaults: {
        hero_heading: "A story shaped by fire.", hero_text: "Ember & Olive is a celebration of fire-cooked cuisine, seasonal ingredients, and genuine hospitality.", hero_image_url: "https://images.unsplash.com/photo-1577219491135-ce391730fb2c?auto=format&fit=crop&w=2000&q=90",
        story_heading: "Rooted in place. Inspired by tradition.", story_text: "Ember & Olive began with a simple idea: let fire and honest ingredients do the talking. What started as a wood-fired oven and a small team has grown into a restaurant shaped by community, craft, and the changing seasons.\n\nWe partner with local farmers, purveyors, and artisans who share our respect for quality and place. Every dish is a reflection of that relationship—and of the moment we’re in.", story_image_url: "https://images.unsplash.com/photo-1556910103-1c02745aae4d?auto=format&fit=crop&w=1400&q=90",
        values: emberAboutValues, chef_heading: "Cooking with intention. Serving from the heart.", chef_quote: "Fire teaches patience and respect. Ingredients tell a story if you listen. Our job is to bring those stories to the table.", chef_image_url: "https://images.unsplash.com/photo-1516211697506-8360dbcfe9a4?auto=format&fit=crop&w=1500&q=90",
        milestones: emberAboutMilestones, team: emberAboutTeam, cta_heading: "Join us for an unforgettable evening.", cta_text: "Reserve your table and experience fire-crafted cuisine, seasonal ingredients, and warm hospitality.", cta_label: "Reserve a Table", cta_url: "/contact",
    },
    fields: [F("hero_heading"), F("hero_text", "textarea"), F("hero_image_url", "image"), F("story_heading"), F("story_text", "textarea"), F("story_image_url", "image"), F("values", "repeater"), F("chef_heading"), F("chef_quote", "textarea"), F("chef_image_url", "image"), F("milestones", "repeater"), F("team", "repeater"), F("cta_heading"), F("cta_text", "textarea"), F("cta_label"), F("cta_url")],
};

export function MarketplaceEmberAboutBlock({ block }) {
    const d = { ...MarketplaceEmberAboutSchema.defaults, ...block };
    const values = Array.isArray(d.values) && d.values.length ? d.values : emberAboutValues;
    const milestones = Array.isArray(d.milestones) && d.milestones.length ? d.milestones : emberAboutMilestones;
    const team = Array.isArray(d.team) && d.team.length ? d.team : emberAboutTeam;
    const mosaic = [
        "https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=900&q=88",
        "https://images.unsplash.com/photo-1516211697506-8360dbcfe9a4?auto=format&fit=crop&w=900&q=88",
        "https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=900&q=88",
        "https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=900&q=88",
    ];
    return <div className="marketplace-ember-home" style={{ background: "#f5f0e7", color: "#221b16" }}>
        <section data-ember-surface="hero" data-ember-tone="dark" className="marketplace-ember-home relative min-h-[560px] overflow-hidden" style={{ background: "#171713", color: "#f5f0e7" }}>
            <Image src={d.hero_image_url} fieldPath="hero_image_url" background className="absolute inset-0 h-full w-full" />
            <div className="absolute inset-0" style={{ background: "linear-gradient(90deg,rgba(10,10,8,.98),rgba(13,13,10,.88) 36%,rgba(12,12,9,.18) 78%)" }} />
            <div className="relative z-10 mx-auto flex min-h-[560px] max-w-[1360px] items-center px-6 py-20 sm:px-8 lg:px-12"><div className="max-w-[620px]"><Text value={d.hero_heading} fieldPath="hero_heading" cosmicType="h1" className="block font-serif text-[clamp(3.5rem,6vw,6.5rem)] leading-[.92] tracking-[-.045em] text-white" /><Text value={d.hero_text} fieldPath="hero_text" area className="mt-7 block max-w-[520px] text-base leading-8 text-white/70" /></div></div>
        </section>
        <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home px-6 py-20 sm:px-8 lg:px-12 lg:py-24"><div className="mx-auto grid max-w-[1360px] items-center gap-12 lg:grid-cols-[.85fr_1.15fr] lg:gap-16"><div><Text value="OUR ORIGIN" fieldPath="story_eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: "#b77d2f" }} /><Text value={d.story_heading} fieldPath="story_heading" cosmicType="h2" className="mt-4 block max-w-[560px] font-serif text-[clamp(2.7rem,4vw,4.4rem)] leading-[.98]" /><Text value={d.story_text} fieldPath="story_text" area className="mt-7 block max-w-[590px] whitespace-pre-line text-sm leading-7" style={{ color: "#62574f" }} /></div><div className="overflow-hidden rounded-[2px]"><Image src={d.story_image_url} fieldPath="story_image_url" className="aspect-[4/3] w-full" /></div></div></section>
        <section data-ember-surface="feature" data-ember-tone="dark" className="marketplace-ember-home px-6 py-16 sm:px-8 lg:px-12 lg:py-20" style={{ background: "linear-gradient(90deg,#292819,#3a3a20,#292819)", color: "#f5f0e7" }}><div className="mx-auto max-w-[1360px]"><div className="text-center"><Text value="OUR VALUES" fieldPath="values_eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: "#d5a153" }} /><h2 className="mt-4 font-serif text-[clamp(2rem,3vw,3.4rem)]">The principles that guide everything we do.</h2></div><div className="mt-12 grid md:grid-cols-2 xl:grid-cols-4">{values.slice(0,4).map((it,i)=><article key={i} className="relative px-7 py-5 text-center">{i>0&&<span className="absolute left-0 top-[12%] hidden h-[76%] w-px xl:block" style={{ background: "rgba(213,161,83,.3)" }} />}<Text value={it.icon||"✦"} fieldPath={`values.${i}.icon`} className="block text-4xl" style={{ color: "#d5a153" }} /><Text value={it.title} fieldPath={`values.${i}.title`} cosmicType="h3" className="mt-5 block font-serif text-xl text-white" /><Text value={it.text} fieldPath={`values.${i}.text`} area className="mx-auto mt-3 block max-w-[260px] text-xs leading-6 text-white/65" /></article>)}</div></div></section>
        <section data-ember-surface="menu" data-ember-tone="dark" className="marketplace-ember-home px-6 py-16 sm:px-8 lg:px-12 lg:py-20" style={{ background: "#151612", color: "#f5f0e7" }}><div className="mx-auto grid max-w-[1360px] overflow-hidden lg:grid-cols-[.82fr_1.18fr]"><div className="flex items-center px-2 py-10 sm:px-8 lg:px-12"><div><Text value="FROM OUR CHEF" fieldPath="chef_eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: "#d5a153" }} /><Text value={d.chef_heading} fieldPath="chef_heading" cosmicType="h2" className="mt-4 block font-serif text-[clamp(2.6rem,4vw,4.5rem)] leading-[.98] text-white" /><div className="mt-8 font-serif text-5xl" style={{ color: "#d5a153" }}>“</div><Text value={d.chef_quote} fieldPath="chef_quote" area className="-mt-2 block max-w-[520px] font-serif text-xl italic leading-8 text-white/75" /><p className="mt-5 text-xs font-bold" style={{ color: "#d5a153" }}>— Chef &amp; Owner</p></div></div><div className="min-h-[440px] overflow-hidden"><Image src={d.chef_image_url} fieldPath="chef_image_url" className="h-full min-h-[440px] w-full" /></div></div></section>
        <section className="grid grid-cols-2 lg:grid-cols-4">{mosaic.map((src,i)=><div key={src} className="overflow-hidden"><Image src={src} fieldPath={`mosaic.${i}`} className="aspect-[4/3] w-full transition duration-500 hover:scale-105" /></div>)}</section>
        <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home px-6 py-20 sm:px-8 lg:px-12"><div className="mx-auto max-w-[1360px]"><Text value="OUR JOURNEY" fieldPath="journey_eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: "#b77d2f" }} /><h2 className="mt-4 font-serif text-[clamp(2.5rem,3.8vw,4.2rem)]">Milestones that shaped our path.</h2><div className="relative mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-6 lg:gap-5"><span className="absolute left-0 right-0 top-[7px] hidden h-px lg:block" style={{ background: "#c89648" }} />{milestones.slice(0,6).map((it,i)=><article key={i} className="relative"><span className="mb-5 block h-3 w-3 rounded-full" style={{ background: "#c89648" }} /><Text value={it.year} fieldPath={`milestones.${i}.year`} className="block text-xs" /><Text value={it.title} fieldPath={`milestones.${i}.title`} cosmicType="h3" className="mt-2 block font-serif text-lg" /><Text value={it.text} fieldPath={`milestones.${i}.text`} area className="mt-2 block text-xs leading-5" style={{ color: "#6b6255" }} /></article>)}</div><div className="mt-20"><Text value="MEET THE TEAM" fieldPath="team_eyebrow" className="text-[10px] font-black uppercase tracking-[.24em]" style={{ color: "#b77d2f" }} /><h2 className="mt-4 font-serif text-[clamp(2.5rem,3.8vw,4.2rem)]">The people behind Ember &amp; Olive.</h2><div className="mt-9 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{team.slice(0,4).map((it,i)=><article key={i} className="text-center"><div className="overflow-hidden"><Image src={it.image_url} fieldPath={`team.${i}.image_url`} className="aspect-[4/3] w-full" /></div><Text value={it.name} fieldPath={`team.${i}.name`} cosmicType="h3" className="mt-4 block font-serif text-lg" /><Text value={it.role} fieldPath={`team.${i}.role`} className="mt-1 block text-xs" style={{ color: "#6b6255" }} /></article>)}</div></div></div></section>
        <section data-ember-surface="hero" data-ember-tone="dark" className="marketplace-ember-home relative overflow-hidden px-6 py-16 sm:px-8 lg:px-12" style={{ background: "#171713", color: "#f5f0e7" }}><div className="absolute inset-0 opacity-30"><Image src="https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1800&q=88" fieldPath="cta_image_url" background className="h-full w-full" /></div><div className="absolute inset-0" style={{ background: "linear-gradient(90deg,rgba(15,15,12,.98),rgba(15,15,12,.8) 52%,rgba(15,15,12,.25))" }} /><div className="relative mx-auto max-w-[1360px]"><Text value={d.cta_heading} fieldPath="cta_heading" cosmicType="h2" className="block max-w-3xl font-serif text-[clamp(2.6rem,4vw,4.6rem)] leading-[.98] text-white" /><Text value={d.cta_text} fieldPath="cta_text" area className="mt-5 block max-w-xl text-sm leading-7 text-white/65" /><Button label={d.cta_label} url={d.cta_url} fieldPath="cta_label" className="mt-7 inline-flex min-h-12 items-center rounded-[3px] px-7 text-sm font-bold text-[#17140f]" style={{ background: "linear-gradient(180deg,#e7bd71,#c98d36)" }} /></div></section>
    </div>;
}

const emberMenuGroups = [
    { title: "To Begin", items: [
        { name: "Wood-Fired Octopus", description: "Smoked paprika, charred lemon, olive relish", price: "$28" },
        { name: "Burrata & Embered Grapes", description: "Sourdough, basil oil, aged balsamic", price: "$19" },
        { name: "Seared Scallops", description: "Cauliflower purée, brown butter, crispy capers", price: "$26" },
    ] },
    { title: "From the Hearth", items: [
        { name: "Herb-Roasted Chicken", description: "Baby carrots, rosemary jus, toasted farro", price: "$32" },
        { name: "Cedar-Roasted Salmon", description: "Spring peas, preserved lemon, dill", price: "$38" },
        { name: "Ember-Grilled Lamb", description: "White beans, salsa verde, natural jus", price: "$44" },
    ] },
    { title: "For the Table", items: [
        { name: "Coal-Roasted Carrots", description: "Whipped feta, pistachio, local honey", price: "$14" },
        { name: "Crispy Potatoes", description: "Garlic confit, rosemary, sea salt", price: "$13" },
        { name: "Seasonal Greens", description: "Mustard vinaigrette, herbs, toasted seeds", price: "$12" },
    ] },
];
const emberMenuSides = [
    { name: "Grilled Broccolini", description: "Lemon, chile, pecorino", price: "$14" },
    { name: "Warm Sourdough", description: "Cultured butter, smoked salt", price: "$9" },
    { name: "Market Mushrooms", description: "Thyme, garlic, sherry", price: "$15" },
];
const emberMenuDesserts = [
    { name: "Olive Oil Cake", description: "Citrus, mascarpone, rosemary honey", price: "$14" },
    { name: "Dark Chocolate Tart", description: "Espresso, sea salt, crème fraîche", price: "$15" },
    { name: "Seasonal Sorbet", description: "Daily selection", price: "$11" },
];

export const MarketplaceEmberMenuPageSchema = {
    type: "marketplace_ember_menu_page", title: "Ember & Olive Full Menu Page", category: "Marketplace",
    defaults: {
        hero_heading: "A menu guided by fire and season.", hero_text: "Thoughtful dishes, local ingredients, and the unmistakable character of the hearth.", hero_image_url: "https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=2000&q=90",
        groups: emberMenuGroups, signature_heading: "Dry-Aged Ribeye", signature_text: "Twenty-eight day aged beef, ember-roasted onions, bone marrow jus, and hand-cut potatoes.", signature_price: "$58", signature_note: "CHEF'S SIGNATURE · LIMITED NIGHTLY", signature_image_url: "https://images.unsplash.com/photo-1546833999-b9f581a1996d?auto=format&fit=crop&w=1500&q=90",
        sides: emberMenuSides, desserts: emberMenuDesserts,
        gallery: [
            { image_url: "https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=900&q=88" },
            { image_url: "https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=900&q=88" },
            { image_url: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=900&q=88" },
            { image_url: "https://images.unsplash.com/photo-1541544741938-0af808871cc0?auto=format&fit=crop&w=900&q=88" },
        ],
        sourcing_heading: "Cooked with care. Sourced with purpose.", sourcing_text: "Our menu changes with the season and the growers, fishers, and makers we work alongside. Please tell your server about allergies or dietary preferences; we are happy to guide you.",
        cta_heading: "Your table is waiting.", cta_text: "Join us for an evening shaped by fire, season, and warm hospitality.", cta_label: "Reserve a Table", cta_url: "/contact",
    },
    fields: [F("hero_heading"), F("hero_text", "textarea"), F("hero_image_url", "image"), F("groups", "repeater"), F("signature_heading"), F("signature_text", "textarea"), F("signature_price"), F("signature_note"), F("signature_image_url", "image"), F("sides", "repeater"), F("desserts", "repeater"), F("gallery", "repeater"), F("sourcing_heading"), F("sourcing_text", "textarea"), F("cta_heading"), F("cta_text", "textarea"), F("cta_label"), F("cta_url")],
};

const MenuItem = ({ item, path }) => <div className="border-b pb-5" style={{ borderColor: "rgba(125,101,73,.22)" }}><div className="flex items-baseline justify-between gap-5"><Text value={item.name} fieldPath={`${path}.name`} cosmicType="h3" className="block font-serif text-xl" /><Text value={item.price} fieldPath={`${path}.price`} className="shrink-0 text-xs font-semibold tracking-[.12em]" style={{ color: "#a86d2c" }} /></div><Text value={item.description} fieldPath={`${path}.description`} area className="mt-2 block text-xs leading-5" style={{ color: "#75695d" }} /></div>;

export function MarketplaceEmberMenuPageBlock({ block }) {
    const d = { ...MarketplaceEmberMenuPageSchema.defaults, ...block };
    const groups = Array.isArray(d.groups) && d.groups.length ? d.groups : emberMenuGroups;
    const sides = Array.isArray(d.sides) && d.sides.length ? d.sides : emberMenuSides;
    const desserts = Array.isArray(d.desserts) && d.desserts.length ? d.desserts : emberMenuDesserts;
    const gallery = Array.isArray(d.gallery) && d.gallery.length ? d.gallery : MarketplaceEmberMenuPageSchema.defaults.gallery;
    return <div className="marketplace-ember-home" style={{ background: "#f5f0e7", color: "#211a15" }}>
        <section data-ember-surface="hero" data-ember-tone="dark" className="marketplace-ember-home relative min-h-[520px] overflow-hidden" style={{ background: "#151511", color: "#f5f0e7" }}><Image src={d.hero_image_url} fieldPath="hero_image_url" background className="absolute inset-0 h-full w-full" /><div className="absolute inset-0" style={{ background: "linear-gradient(90deg,rgba(10,10,8,.98),rgba(12,12,9,.82) 43%,rgba(12,12,9,.15))" }} /><div className="relative z-10 mx-auto flex min-h-[520px] max-w-[1360px] items-center px-6 py-20 sm:px-8 lg:px-12"><div className="max-w-[690px]"><Text value="DINNER · SEASONAL MENU" fieldPath="hero_eyebrow" className="text-[10px] font-semibold uppercase tracking-[.26em]" style={{ color: "#d4a050" }} /><Text value={d.hero_heading} fieldPath="hero_heading" cosmicType="h1" className="mt-5 block font-serif text-[clamp(3.4rem,6vw,6.4rem)] leading-[.91] tracking-[-.045em] text-white" /><Text value={d.hero_text} fieldPath="hero_text" area className="mt-7 block max-w-[530px] text-base leading-8 text-white/70" /></div></div></section>
        <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home px-6 py-16 sm:px-8 lg:px-12 lg:py-20"><div className="mx-auto max-w-[1360px]"><nav aria-label="Menu categories" className="flex flex-wrap justify-center gap-x-10 gap-y-4 border-b pb-5 text-xs uppercase tracking-[.2em]" style={{ borderColor: "#d9cbbb" }}>{["Dinner", "Lunch", "Drinks", "Dessert"].map((label,i)=><span key={label} className={`relative px-1 pb-4 ${i===0?"font-semibold":""}`} style={{ color: i===0?"#8d5926":"#75695d" }}>{label}{i===0&&<span className="absolute inset-x-0 -bottom-[21px] h-[2px]" style={{ background: "#bd8135" }} />}</span>)}</nav><div className="mt-14 grid gap-14 lg:grid-cols-3 lg:gap-12">{groups.slice(0,3).map((group,gi)=><section key={gi}><Text value={group.title} fieldPath={`groups.${gi}.title`} cosmicType="h2" className="block border-b pb-4 font-serif text-3xl" style={{ borderColor: "#b9833d" }} /><div className="mt-7 grid gap-6">{(group.items||[]).slice(0,5).map((item,ii)=><MenuItem key={ii} item={item} path={`groups.${gi}.items.${ii}`} />)}</div></section>)}</div></div></section>
        <section data-ember-surface="menu" data-ember-tone="dark" className="marketplace-ember-home px-6 py-16 sm:px-8 lg:px-12 lg:py-20" style={{ background: "#272718", color: "#f5f0e7" }}><div className="mx-auto grid max-w-[1360px] items-stretch overflow-hidden lg:grid-cols-[.82fr_1.18fr]"><div className="flex items-center px-2 py-10 sm:px-8 lg:px-12"><div><Text value={d.signature_note} fieldPath="signature_note" className="text-[10px] font-semibold uppercase tracking-[.24em]" style={{ color: "#d5a153" }} /><Text value={d.signature_heading} fieldPath="signature_heading" cosmicType="h2" className="mt-5 block font-serif text-[clamp(3rem,5vw,5.3rem)] leading-[.94] text-white" /><Text value={d.signature_text} fieldPath="signature_text" area className="mt-6 block max-w-[520px] text-sm leading-7 text-white/65" /><Text value={d.signature_price} fieldPath="signature_price" className="mt-7 block font-serif text-2xl" style={{ color: "#d5a153" }} /></div></div><div className="min-h-[440px] overflow-hidden"><Image src={d.signature_image_url} fieldPath="signature_image_url" className="h-full min-h-[440px] w-full" /></div></div></section>
        <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home px-6 py-16 sm:px-8 lg:px-12 lg:py-20"><div className="mx-auto grid max-w-[1360px] gap-14 lg:grid-cols-2 lg:gap-20">{[["Sides",sides,"sides"],["Something Sweet",desserts,"desserts"]].map(([title,items,key])=><section key={key}><h2 className="border-b pb-4 font-serif text-3xl" style={{ borderColor: "#b9833d" }}>{title}</h2><div className="mt-7 grid gap-6">{items.slice(0,5).map((item,i)=><MenuItem key={i} item={item} path={`${key}.${i}`} />)}</div></section>)}</div></section>
        <section className="grid grid-cols-2 lg:grid-cols-4">{gallery.slice(0,4).map((item,i)=><div key={i} className="overflow-hidden"><Image src={item.image_url} fieldPath={`gallery.${i}.image_url`} className="aspect-[4/3] w-full transition duration-500 hover:scale-105" /></div>)}</section>
        <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home px-6 py-16 sm:px-8 lg:px-12"><div className="mx-auto grid max-w-[1360px] items-start gap-8 border-y py-10 lg:grid-cols-[.75fr_1.25fr] lg:gap-16" style={{ borderColor: "#d6c8b7" }}><Text value={d.sourcing_heading} fieldPath="sourcing_heading" cosmicType="h2" className="block max-w-[430px] font-serif text-[clamp(2rem,3vw,3.2rem)] leading-[1.02]" /><Text value={d.sourcing_text} fieldPath="sourcing_text" area className="block max-w-[690px] text-sm leading-8" style={{ color: "#6e6258" }} /></div></section>
        <section data-ember-surface="hero" data-ember-tone="dark" className="marketplace-ember-home px-6 py-16 text-center sm:px-8 lg:px-12 lg:py-20" style={{ background: "#151612", color: "#f5f0e7" }}><div className="mx-auto max-w-[850px]"><Text value={d.cta_heading} fieldPath="cta_heading" cosmicType="h2" className="block font-serif text-[clamp(3rem,5vw,5.2rem)] leading-[.95] text-white" /><Text value={d.cta_text} fieldPath="cta_text" area className="mx-auto mt-5 block max-w-[560px] text-sm leading-7 text-white/65" /><Button label={d.cta_label} url={d.cta_url} fieldPath="cta_label" className="mt-8 inline-flex min-h-12 items-center rounded-[3px] px-7 text-sm font-semibold text-[#17140f]" style={{ background: "linear-gradient(180deg,#e7bd71,#c98d36)" }} /></div></section>
    </div>;
}

const emberPrivateEvents = [
    { icon: "♨", title: "Intimate Dinners", text: "Perfect for close connections and quiet celebrations.", capacity: "8–14 Guests" },
    { icon: "◇", title: "Celebrations", text: "Milestones deserve a beautiful setting and unforgettable service.", capacity: "15–30 Guests" },
    { icon: "▣", title: "Corporate Gatherings", text: "Professional, private, and seamlessly executed.", capacity: "10–30 Guests" },
];
const emberPrivateAmenities = [
    { icon: "♙", title: "Seated Capacity", text: "Up to 30 guests comfortably seated." },
    { icon: "⌒", title: "Custom Menus", text: "Thoughtfully tailored to your occasion and preferences." },
    { icon: "♧", title: "Dedicated Service", text: "A professional team devoted to every detail." },
    { icon: "▭", title: "AV Availability", text: "Screen, mic, and music options available." },
];

export const MarketplaceEmberPrivateDiningPageSchema = {
    type: "marketplace_ember_private_dining_page", title: "Ember & Olive Private Dining Page", category: "Marketplace",
    defaults: {
        hero_heading: "Gather around something unforgettable.", hero_text: "Our private dining spaces are designed for meaningful moments and memorable meals, wrapped in warmth, elegance, and attentive care.", hero_image_url: "https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=2000&q=90", hero_label: "Plan Your Event", hero_url: "#enquire",
        room_heading: "A room of your own.", room_text: "Tucked away from the bustle, our private dining room offers an intimate setting for life's special occasions and important connections.\n\nFrom milestone celebrations and corporate dinners to rehearsal dinners and client entertaining, we’ll help you host with ease and style.", room_image_url: "https://images.unsplash.com/photo-1550966871-3ed3cdb5ed0c?auto=format&fit=crop&w=1500&q=90",
        events: emberPrivateEvents, amenities: emberPrivateAmenities,
        experience_heading: "Made personal, from the first toast to the last course.", experience_text: "Our culinary team crafts seasonal, ingredient-driven menus that reflect your vision. Paired with attentive hospitality, every detail is designed to make your event exceptional.", experience_image_url: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=1500&q=90",
        gallery: [{ image_url: "https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=800&q=88" }, { image_url: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=800&q=88" }, { image_url: "https://images.unsplash.com/photo-1550966871-3ed3cdb5ed0c?auto=format&fit=crop&w=800&q=88" }, { image_url: "https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=800&q=88" }],
        quote: "Every detail felt considered—from the menu and wine pairings to the warmth of the room. Our guests are still talking about it.", quote_by: "— AMELIA R. · ANNIVERSARY DINNER",
        form_heading: "Let’s plan your gathering.", form_text: "Tell us a little about your occasion and our events team will be in touch to shape the details with you.", form_label: "Send an Enquiry",
        cta_heading: "Make the evening yours.", cta_text: "A memorable table, a menu shaped for you, and hospitality that feels effortless.", cta_label: "Enquire About Private Dining", cta_url: "#enquire",
    },
    fields: [F("hero_heading"), F("hero_text", "textarea"), F("hero_image_url", "image"), F("hero_label"), F("hero_url"), F("room_heading"), F("room_text", "textarea"), F("room_image_url", "image"), F("events", "repeater"), F("amenities", "repeater"), F("experience_heading"), F("experience_text", "textarea"), F("experience_image_url", "image"), F("gallery", "repeater"), F("quote", "textarea"), F("quote_by"), F("form_heading"), F("form_text", "textarea"), F("form_label"), F("cta_heading"), F("cta_text", "textarea"), F("cta_label"), F("cta_url")],
};

export function MarketplaceEmberPrivateDiningPageBlock({ block }) {
    const d = { ...MarketplaceEmberPrivateDiningPageSchema.defaults, ...block };
    const events = Array.isArray(d.events) && d.events.length ? d.events : emberPrivateEvents;
    const amenities = Array.isArray(d.amenities) && d.amenities.length ? d.amenities : emberPrivateAmenities;
    const gallery = Array.isArray(d.gallery) && d.gallery.length ? d.gallery : MarketplaceEmberPrivateDiningPageSchema.defaults.gallery;
    const inputClass = "min-h-11 w-full border px-4 text-xs outline-none";
    return <div className="marketplace-ember-home" style={{ background: "#f5f0e7", color: "#211a15" }}>
        <section data-ember-surface="hero" data-ember-tone="dark" className="marketplace-ember-home relative min-h-[560px] overflow-hidden" style={{ background: "#151511", color: "#f5f0e7" }}><Image src={d.hero_image_url} fieldPath="hero_image_url" background className="absolute inset-0 h-full w-full" /><div className="absolute inset-0" style={{ background: "linear-gradient(90deg,rgba(9,9,7,.97),rgba(10,10,8,.78) 45%,rgba(10,10,8,.14))" }} /><div className="relative z-10 mx-auto flex min-h-[560px] max-w-[1360px] items-center px-6 py-20 sm:px-8 lg:px-12"><div className="max-w-[690px]"><Text value="PRIVATE DINING" fieldPath="hero_eyebrow" className="text-[10px] font-semibold uppercase tracking-[.26em]" style={{ color: "#d5a153" }} /><Text value={d.hero_heading} fieldPath="hero_heading" cosmicType="h1" className="mt-5 block font-serif text-[clamp(3.4rem,6vw,6.5rem)] leading-[.91] tracking-[-.045em] text-white" /><Text value={d.hero_text} fieldPath="hero_text" area className="mt-7 block max-w-[560px] text-base leading-8 text-white/70" /><Button label={d.hero_label} url={d.hero_url} fieldPath="hero_label" className="mt-8 inline-flex min-h-12 items-center rounded-[3px] px-7 text-sm font-semibold text-[#17140f]" style={{ background: "linear-gradient(180deg,#e7bd71,#c98d36)" }} /></div></div></section>
        <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home"><div className="mx-auto grid max-w-[1360px] lg:grid-cols-[.82fr_1.18fr]"><div className="flex items-center px-6 py-16 sm:px-8 lg:px-12 lg:py-20"><div><Text value={d.room_heading} fieldPath="room_heading" cosmicType="h2" className="block font-serif text-[clamp(2.8rem,4vw,4.5rem)] leading-[.98]" /><span className="mt-6 block h-px w-12" style={{ background: "#c58a3a" }} /><Text value={d.room_text} fieldPath="room_text" area className="mt-6 block max-w-[500px] whitespace-pre-line text-sm leading-7" style={{ color: "#62574f" }} /></div></div><div className="min-h-[440px] overflow-hidden"><Image src={d.room_image_url} fieldPath="room_image_url" className="h-full min-h-[440px] w-full" /></div></div></section>
        <section data-ember-surface="feature" data-ember-tone="dark" className="marketplace-ember-home px-6 py-14 sm:px-8 lg:px-12" style={{ background: "linear-gradient(90deg,#242416,#34341d,#242416)", color: "#f5f0e7" }}><div className="mx-auto grid max-w-[1360px] md:grid-cols-3">{events.slice(0,3).map((it,i)=><article key={i} className="relative px-8 py-7 text-center">{i>0&&<span className="absolute left-0 top-[10%] hidden h-[80%] w-px md:block" style={{ background: "rgba(213,161,83,.32)" }} />}<Text value={it.icon} fieldPath={`events.${i}.icon`} className="block text-4xl" style={{ color: "#d5a153" }} /><Text value={it.title} fieldPath={`events.${i}.title`} cosmicType="h3" className="mt-5 block font-serif text-2xl text-white" /><Text value={it.text} fieldPath={`events.${i}.text`} area className="mx-auto mt-3 block max-w-[270px] text-xs leading-6 text-white/65" /><Text value={it.capacity} fieldPath={`events.${i}.capacity`} className="mt-5 block text-xs tracking-[.12em]" style={{ color: "#d5a153" }} /></article>)}</div></section>
        <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home px-6 py-12 sm:px-8 lg:px-12"><div className="mx-auto grid max-w-[1360px] sm:grid-cols-2 lg:grid-cols-4">{amenities.slice(0,4).map((it,i)=><article key={i} className="relative px-7 py-6 text-center">{i>0&&<span className="absolute left-0 top-[10%] hidden h-[80%] w-px lg:block" style={{ background: "#d5c4ac" }} />}<Text value={it.icon} fieldPath={`amenities.${i}.icon`} className="block text-4xl" style={{ color: "#bd8135" }} /><Text value={it.title} fieldPath={`amenities.${i}.title`} cosmicType="h3" className="mt-4 block font-serif text-xl" /><Text value={it.text} fieldPath={`amenities.${i}.text`} area className="mx-auto mt-2 block max-w-[250px] text-xs leading-5" style={{ color: "#6b6056" }} /></article>)}</div></section>
        <section data-ember-surface="menu" data-ember-tone="dark" className="marketplace-ember-home" style={{ background: "#151612", color: "#f5f0e7" }}><div className="mx-auto grid max-w-[1360px] lg:grid-cols-[.88fr_1.12fr]"><div className="flex items-center px-6 py-16 sm:px-8 lg:px-12 lg:py-20"><div><Text value={d.experience_heading} fieldPath="experience_heading" cosmicType="h2" className="block font-serif text-[clamp(2.8rem,4vw,4.6rem)] leading-[.97] text-white" /><span className="mt-6 block h-px w-12" style={{ background: "#d5a153" }} /><Text value={d.experience_text} fieldPath="experience_text" area className="mt-6 block max-w-[520px] text-sm leading-7 text-white/65" /></div></div><div className="min-h-[440px] overflow-hidden"><Image src={d.experience_image_url} fieldPath="experience_image_url" className="h-full min-h-[440px] w-full" /></div></div></section>
        <section className="grid grid-cols-2 lg:grid-cols-4">{gallery.slice(0,4).map((it,i)=><div key={i} className="overflow-hidden"><Image src={it.image_url} fieldPath={`gallery.${i}.image_url`} className="aspect-[4/3] w-full transition duration-500 hover:scale-105" /></div>)}</section>
        <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home px-6 py-16 sm:px-8 lg:px-12 lg:py-20"><div className="mx-auto max-w-[1360px]"><div className="text-center"><Text value="FROM ENQUIRY TO EVENING" fieldPath="process_eyebrow" className="text-[10px] font-semibold uppercase tracking-[.24em]" style={{ color: "#a86c2b" }} /><h2 className="mt-4 font-serif text-[clamp(2.5rem,4vw,4rem)]">Thoughtfully planned. Effortlessly enjoyed.</h2></div><div className="mt-12 grid gap-8 md:grid-cols-3">{[["01","Enquire","Share your date, guest count, and the occasion you have in mind."],["02","Curate","We’ll shape a seasonal menu and every detail around your gathering."],["03","Celebrate","Arrive, settle in, and let our team take care of the evening."]].map(([n,title,text])=><article key={n} className="border-t pt-6" style={{ borderColor: "#bd8135" }}><span className="text-xs font-semibold tracking-[.15em]" style={{ color: "#a86c2b" }}>{n}</span><h3 className="mt-4 font-serif text-2xl">{title}</h3><p className="mt-3 max-w-[340px] text-sm leading-7" style={{ color: "#6b6056" }}>{text}</p></article>)}</div></div></section>
        <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home px-6 py-14 text-center sm:px-8 lg:px-12" style={{ background: "#eee6d9" }}><div className="mx-auto max-w-[820px]"><div className="font-serif text-4xl" style={{ color: "#b77d2f" }}>“</div><Text value={d.quote} fieldPath="quote" area className="mt-2 block font-serif text-xl italic leading-9" /><Text value={d.quote_by} fieldPath="quote_by" className="mt-5 block text-[10px] font-semibold uppercase tracking-[.2em]" style={{ color: "#9b6a2c" }} /></div></section>
        <section id="enquire" data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home px-6 py-16 sm:px-8 lg:px-12 lg:py-20"><div className="mx-auto grid max-w-[1360px] gap-12 lg:grid-cols-[.72fr_1.28fr] lg:gap-20"><div><Text value="PRIVATE EVENTS" fieldPath="form_eyebrow" className="text-[10px] font-semibold uppercase tracking-[.24em]" style={{ color: "#a86c2b" }} /><Text value={d.form_heading} fieldPath="form_heading" cosmicType="h2" className="mt-4 block font-serif text-[clamp(2.8rem,4vw,4.6rem)] leading-[.97]" /><Text value={d.form_text} fieldPath="form_text" area className="mt-6 block max-w-[470px] text-sm leading-7" style={{ color: "#6b6056" }} /></div><form className="grid gap-4 sm:grid-cols-2" onSubmit={(e)=>e.preventDefault()}>{["Preferred Date","Guest Count","Occasion","Your Name","Email","Phone"].map(x=><input key={x} aria-label={x} placeholder={x} className={inputClass} style={{ borderColor: "#d4c6b5", background: "#fffaf3", color: "#554b42" }} />)}<textarea aria-label="Tell us about your event" placeholder="Tell us about your event" rows="5" className={`${inputClass} py-3 sm:col-span-2`} style={{ borderColor: "#d4c6b5", background: "#fffaf3", color: "#554b42" }} /><Button label={d.form_label} url="#enquire" fieldPath="form_label" className="inline-flex min-h-12 items-center justify-center rounded-[3px] px-7 text-sm font-semibold text-[#17140f] sm:col-span-2" style={{ background: "linear-gradient(180deg,#e7bd71,#c98d36)" }} /></form></div></section>
        <section data-ember-surface="hero" data-ember-tone="dark" className="marketplace-ember-home relative overflow-hidden px-6 py-20 text-center sm:px-8 lg:px-12" style={{ background: "#151612", color: "#f5f0e7" }}><div className="absolute inset-0 opacity-20"><Image src={d.hero_image_url} fieldPath="cta_image_url" background className="h-full w-full" /></div><div className="absolute inset-0 bg-black/60" /><div className="relative mx-auto max-w-[850px]"><Text value={d.cta_heading} fieldPath="cta_heading" cosmicType="h2" className="block font-serif text-[clamp(3rem,5vw,5.2rem)] leading-[.95] text-white" /><Text value={d.cta_text} fieldPath="cta_text" area className="mx-auto mt-5 block max-w-[570px] text-sm leading-7 text-white/65" /><Button label={d.cta_label} url={d.cta_url} fieldPath="cta_label" className="mt-8 inline-flex min-h-12 items-center rounded-[3px] px-7 text-sm font-semibold text-[#17140f]" style={{ background: "linear-gradient(180deg,#e7bd71,#c98d36)" }} /></div></section>
    </div>;
}

const emberContactFaqs = [
    { question: "How do I make a reservation?", answer: "Use the form above or call us daily between 10am and 10pm." },
    { question: "Do you accommodate large parties?", answer: "For groups of eight or more, please contact our private dining team." },
    { question: "Can you accommodate dietary restrictions?", answer: "Yes. Add a note to your reservation and speak with your server on arrival." },
    { question: "What is your cancellation policy?", answer: "Please give us at least 24 hours notice when plans change." },
];

export const MarketplaceEmberContactPageSchema = {
    type: "marketplace_ember_contact_page", title: "Ember & Olive Contact & Reservations Page", category: "Marketplace",
    defaults: {
        heading: "We’ll save you a seat.", text: "Reserve your table and let us take care of the rest. We can’t wait to welcome you to Ember & Olive.", phone: "(503) 227-7412", email: "hello@emberandolive.com", reservation_image_url: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=1200&q=90", form_label: "Find a Table",
        address: "509 NW 13th Ave\nPortland, OR 97209", hours: "Mon – Thu     5:00pm – 10:00pm\nFri – Sat       4:30pm – 11:00pm\nSunday          4:30pm – 9:00pm",
        visit_heading: "Come as you are. Stay for the evening.", visit_image_url: "https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=1500&q=90", parking: "Valet parking is available at our entrance. Street parking and nearby garages are also available.", accessibility: "Our space is fully accessible. Please let us know how we can best accommodate you.", transit: "We’re a short walk from the NW 13th Ave and Lovejoy MAX station.",
        faqs: emberContactFaqs, gallery: [{ image_url: "https://images.unsplash.com/photo-1550966871-3ed3cdb5ed0c?auto=format&fit=crop&w=800&q=88" }, { image_url: "https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=800&q=88" }, { image_url: "https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=800&q=88" }, { image_url: "https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=800&q=88" }],
        cta_heading: "Dinner starts here.", cta_label: "Reserve a Table", cta_url: "#reserve",
    },
    fields: [F("heading"), F("text", "textarea"), F("phone"), F("email"), F("reservation_image_url", "image"), F("form_label"), F("address", "textarea"), F("hours", "textarea"), F("visit_heading"), F("visit_image_url", "image"), F("parking", "textarea"), F("accessibility", "textarea"), F("transit", "textarea"), F("faqs", "repeater"), F("gallery", "repeater"), F("cta_heading"), F("cta_label"), F("cta_url")],
};

export function MarketplaceEmberContactPageBlock({ block }) {
    const d = { ...MarketplaceEmberContactPageSchema.defaults, ...block };
    const faqs = Array.isArray(d.faqs) && d.faqs.length ? d.faqs : emberContactFaqs;
    const gallery = Array.isArray(d.gallery) && d.gallery.length ? d.gallery : MarketplaceEmberContactPageSchema.defaults.gallery;
    const field = "min-h-11 w-full border px-4 text-xs outline-none";
    return <div className="marketplace-ember-home" style={{ background: "#f5f0e7", color: "#211a15" }}>
        <section id="reserve" data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home relative overflow-hidden px-6 py-16 sm:px-8 lg:px-12 lg:py-20"><div className="absolute bottom-0 left-0 h-[52%] w-[55%] opacity-20"><Image src={d.reservation_image_url} fieldPath="reservation_image_url" background className="h-full w-full" /></div><div className="relative mx-auto grid max-w-[1360px] gap-12 lg:grid-cols-[.92fr_1.08fr] lg:gap-16"><div><Text value="CONTACT & RESERVATIONS" fieldPath="eyebrow" className="text-[10px] font-semibold uppercase tracking-[.24em]" style={{ color: "#a96d2b" }} /><Text value={d.heading} fieldPath="heading" cosmicType="h1" className="mt-5 block max-w-[650px] font-serif text-[clamp(3.5rem,6vw,6.4rem)] leading-[.91] tracking-[-.045em]" /><Text value={d.text} fieldPath="text" area className="mt-7 block max-w-[520px] text-base leading-8" style={{ color: "#62574f" }} /><div className="mt-8 grid gap-5 text-sm"><div><span className="mr-4 text-xl" style={{ color: "#c18434" }}>◯</span><Text value={d.phone} fieldPath="phone" className="font-semibold" /></div><div><span className="mr-4 text-xl" style={{ color: "#c18434" }}>✉</span><Text value={d.email} fieldPath="email" className="font-semibold" /></div></div><div className="mt-10 h-48 lg:h-64" /></div><form className="self-start border p-6 shadow-[0_24px_70px_rgba(45,35,22,.12)] sm:p-9" style={{ borderColor: "#ded2c4", background: "rgba(255,250,243,.96)" }} onSubmit={(e)=>e.preventDefault()}><h2 className="font-serif text-4xl">Find a Table</h2><div className="mt-7 grid gap-4 sm:grid-cols-2">{["Date","Time","Party Size","Your Name","Email","Phone"].map(x=><input key={x} aria-label={x} placeholder={x} className={field} style={{ borderColor: "#d4c6b5", background: "#fffaf3", color: "#554b42" }} />)}<Button label={d.form_label} url="#reserve" fieldPath="form_label" className="inline-flex min-h-12 items-center justify-center rounded-[2px] text-sm font-semibold text-[#17140f] sm:col-span-2" style={{ background: "linear-gradient(180deg,#e7bd71,#c98d36)" }} /></div></form></div></section>
        <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home px-6 py-14 sm:px-8 lg:px-12"><div className="mx-auto max-w-[1360px]"><div className="grid gap-8 md:grid-cols-3">{[["⌖","Find Us",d.address,"address"],["◷","Hours",d.hours,"hours"],["✉","Get in Touch",`${d.phone}\n${d.email}`,"contact_details"]].map(([icon,title,text,key],i)=><article key={key} className={`px-4 py-4 ${i?"md:border-l md:pl-10":""}`} style={{ borderColor: "#d8c9b8" }}><span className="text-3xl" style={{ color: "#c18434" }}>{icon}</span><h2 className="mt-4 font-serif text-2xl">{title}</h2><Text value={text} fieldPath={key} area className="mt-4 block whitespace-pre-line text-xs leading-6" style={{ color: "#65594f" }} /></article>)}</div><div className="relative mt-10 h-[260px] overflow-hidden border" style={{ borderColor: "#d8c9b8", background: "#eee8dc" }}><div className="absolute inset-0 opacity-40" style={{ backgroundImage: "linear-gradient(#c8bdad 1px,transparent 1px),linear-gradient(90deg,#c8bdad 1px,transparent 1px)", backgroundSize: "46px 46px" }} /><div className="absolute inset-0 flex items-center justify-center"><div className="text-center"><span className="text-5xl" style={{ color: "#2c2c20" }}>●</span><p className="mt-2 text-[10px] font-semibold uppercase tracking-[.24em]">Ember & Olive · Pearl District</p></div></div></div></div></section>
        <section data-ember-surface="feature" data-ember-tone="dark" className="marketplace-ember-home" style={{ background: "#292919", color: "#f5f0e7" }}><div className="mx-auto grid max-w-[1360px] lg:grid-cols-[.9fr_1.1fr]"><div className="px-6 py-16 sm:px-8 lg:px-12 lg:py-20"><Text value={d.visit_heading} fieldPath="visit_heading" cosmicType="h2" className="block font-serif text-[clamp(2.8rem,4vw,4.5rem)] leading-[.98] text-white" /><div className="mt-9 grid gap-6">{[["P","PARKING",d.parking,"parking"],["♿","ACCESSIBILITY",d.accessibility,"accessibility"],["▣","PUBLIC TRANSIT",d.transit,"transit"]].map(([icon,title,text,key])=><div key={key} className="grid grid-cols-[36px_1fr] gap-4"><span className="text-xl" style={{ color: "#d5a153" }}>{icon}</span><div><p className="text-[10px] font-semibold tracking-[.18em]" style={{ color: "#d5a153" }}>{title}</p><Text value={text} fieldPath={key} area className="mt-2 block text-xs leading-6 text-white/65" /></div></div>)}</div></div><div className="min-h-[520px] overflow-hidden"><Image src={d.visit_image_url} fieldPath="visit_image_url" className="h-full min-h-[520px] w-full" /></div></div></section>
        <section data-ember-surface="cream" data-ember-tone="light" className="marketplace-ember-home px-6 py-16 sm:px-8 lg:px-12"><div className="mx-auto grid max-w-[1360px] gap-10 lg:grid-cols-[.55fr_1.45fr]"><h2 className="font-serif text-[clamp(2.5rem,4vw,4rem)] leading-[1.02]">Frequently<br />asked questions.</h2><div className="border-t" style={{ borderColor: "#cfc1af" }}>{faqs.slice(0,6).map((item,i)=><details key={i} className="group border-b py-5" style={{ borderColor: "#cfc1af" }}><summary className="flex cursor-pointer list-none items-center justify-between gap-6 font-serif text-lg"><Text value={item.question} fieldPath={`faqs.${i}.question`} /><span className="text-xl">+</span></summary><Text value={item.answer} fieldPath={`faqs.${i}.answer`} area className="mt-4 block max-w-[720px] text-sm leading-7" style={{ color: "#6b6056" }} /></details>)}</div></div></section>
        <section className="grid grid-cols-2 lg:grid-cols-4">{gallery.slice(0,4).map((it,i)=><div key={i} className="overflow-hidden"><Image src={it.image_url} fieldPath={`gallery.${i}.image_url`} className="aspect-[4/3] w-full transition duration-500 hover:scale-105" /></div>)}</section>
        <section data-ember-surface="hero" data-ember-tone="dark" className="marketplace-ember-home relative overflow-hidden px-6 py-20 text-center sm:px-8 lg:px-12" style={{ background: "#151612", color: "#f5f0e7" }}><div className="absolute inset-0 opacity-25"><Image src={d.reservation_image_url} fieldPath="cta_image_url" background className="h-full w-full" /></div><div className="absolute inset-0 bg-black/60" /><div className="relative mx-auto max-w-[820px]"><Text value={d.cta_heading} fieldPath="cta_heading" cosmicType="h2" className="block font-serif text-[clamp(3rem,5vw,5.2rem)] leading-[.95] text-white" /><Button label={d.cta_label} url={d.cta_url} fieldPath="cta_label" className="mt-8 inline-flex min-h-12 items-center rounded-[3px] px-8 text-sm font-semibold text-[#17140f]" style={{ background: "linear-gradient(180deg,#e7bd71,#c98d36)" }} /></div></section>
    </div>;
}
