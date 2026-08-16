import { useMemo, useState } from "react";
import { EditableText } from "../Shared/EditableText";
import { RepeatableControls, RepeatableRemoveButton, cloneLast, removeAt } from "../Shared/RepeatableControls";
import { getEffectiveTheme } from "../../../../theme/Theme";

const baseFields = [
    { key: "eyebrow", type: "text", label: "Eyebrow" },
    { key: "heading", type: "text", label: "Heading" },
    { key: "text", type: "textarea", label: "Supporting text" },
];
const faqFields = [{ key: "question", type: "text", label: "Question" }, { key: "answer", type: "textarea", label: "Answer" }];
const safeFaqs = [
    { question: "What should I know before getting started?", answer: "Use this answer for the real information visitors need before they take the next step." },
    { question: "How does the process work?", answer: "Explain the real process clearly and avoid promises that are not part of your service." },
    { question: "What options are available?", answer: "Replace this copy with the actual options, inclusions, or service choices you offer." },
    { question: "Where can I get more help?", answer: "Point visitors to a real contact, support, booking, or documentation path." },
];
const normaliseFaqs = (value) => Array.isArray(value) && value.length ? value : safeFaqs;

export const FaqAccordionProSchema = {
    type: "faq_accordion_pro", title: "Accordion Pro", category: "FAQ",
    purpose: "Answer important questions with a premium editorial accordion.",
    description: "A spacious two-column FAQ with numbered questions, visual hierarchy, and accessible disclosure controls.",
    tags: ["faq","accordion","questions","support","premium","pro"],
    defaults: { eyebrow: "FREQUENTLY ASKED", heading: "Answers without the fine-print feeling.", text: "Keep the most useful questions easy to scan, then give visitors a clear next step.", faqs: safeFaqs },
    fields: [...baseFields, { key: "faqs", type: "repeater", label: "Questions", fields: faqFields }],
};

export const FaqSearchPremiumSchema = {
    type: "faq_search_premium", title: "Search FAQ", category: "FAQ",
    purpose: "Help visitors quickly find answers in a larger FAQ collection.",
    description: "A premium search-led knowledge section with client-side filtering and a readable static fallback.",
    tags: ["faq","search","knowledge base","support","premium","pro"],
    defaults: { eyebrow: "SEARCH HELP", heading: "Find the answer in seconds.", text: "Search across the questions below. Keep answers factual and specific to the business.", search_placeholder: "Search questions…", faqs: [...safeFaqs, { question:"Can I contact someone directly?", answer:"Add the real support or contact route visitors should use for questions that need a person." }, { question:"Where can I find policies or terms?", answer:"Link or describe the real policy, terms, returns, booking, or service information relevant to your business." }] },
    fields: [...baseFields, {key:"search_placeholder",type:"text",label:"Search placeholder"}, { key: "faqs", type: "repeater", label: "Questions", fields: faqFields }],
};

export const FaqCategoriesPremiumSchema = {
    type: "faq_categories_premium", title: "FAQ Categories", category: "FAQ",
    purpose: "Organize common questions into clear topic groups.",
    description: "A category-led FAQ layout for products, services, billing, onboarding, policies, or support topics.",
    tags: ["faq","categories","topics","support","premium","pro"],
    defaults: { eyebrow:"BROWSE BY TOPIC", heading:"Everything is easier when it is organised.", text:"Group related questions into useful topics so visitors can scan the right answers quickly.", categories:[
        {title:"Getting started", description:"First steps, preparation, and what to expect.", faqs:safeFaqs.slice(0,2)},
        {title:"Services & options", description:"Scope, inclusions, and practical choices.", faqs:[safeFaqs[2], {question:"Can this be customised?",answer:"Describe the actual customisation or scope options your business supports."}]},
        {title:"Help & support", description:"Where to go when visitors need assistance.", faqs:[safeFaqs[3], {question:"How do I contact support?",answer:"Add your real support channel or contact method before publishing."}]},
    ]},
    fields:[...baseFields,{key:"categories",type:"repeater",label:"Categories",fields:[{key:"title",type:"text",label:"Category title"},{key:"description",type:"textarea",label:"Description"},{key:"faqs",type:"repeater",label:"Questions",fields:faqFields}]}],
};

export const FaqSupportPortalPremiumSchema = {
    type: "faq_support_portal_premium", title: "Support Portal", category: "FAQ",
    purpose: "Create a self-service help hub with clear routes to common support topics.",
    description: "A premium portal-style FAQ combining topic cards, featured answers, and a real escalation CTA.",
    tags: ["faq","support portal","help center","documentation","premium","pro"],
    defaults: { eyebrow:"SUPPORT PORTAL", heading:"Start with the answer. Escalate when you need to.", text:"Give visitors useful self-service paths without inventing availability, response times, or support guarantees.", primary_label:"Contact support", primary_url:"/contact", topics:[
        {title:"Getting started", description:"Setup, onboarding, and first steps.", count_label:"Guide"},
        {title:"Using the service", description:"Common workflows and practical help.", count_label:"How-to"},
        {title:"Plans & billing", description:"Real billing, plan, or payment information.", count_label:"Account"},
        {title:"Policies", description:"Terms, cancellations, returns, or service policies.", count_label:"Policy"},
    ], faqs:safeFaqs },
    fields:[...baseFields,{key:"primary_label",type:"text",label:"Support button"},{key:"primary_url",type:"text",label:"Support URL"},{key:"topics",type:"repeater",label:"Support topics",fields:[{key:"title",type:"text",label:"Topic"},{key:"description",type:"textarea",label:"Description"},{key:"count_label",type:"text",label:"Type label"}]},{key:"faqs",type:"repeater",label:"Featured questions",fields:faqFields}],
};

function Header({d,theme,onUpdate}) { return <div className="max-w-2xl"><EditableText value={d.eyebrow} onSave={(eyebrow)=>onUpdate({eyebrow})} className={`block text-xs font-bold uppercase tracking-[.24em] ${theme.sub}`}/><EditableText value={d.heading} onSave={(heading)=>onUpdate({heading})} className={`mt-4 block text-4xl font-bold leading-tight tracking-tight sm:text-5xl ${theme.text}`}/><EditableText value={d.text} isTextArea onSave={(text)=>onUpdate({text})} className={`mt-4 block text-base leading-7 ${theme.sub}`}/></div> }
function faqUpdate(data,onUpdate,index,key,value){ onUpdate({faqs:data.faqs.map((x,i)=>i===index?{...x,[key]:value}:x)}); }

export function FaqAccordionProBlock({block,onUpdate,globalTheme}){
    const theme=getEffectiveTheme(block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme,globalTheme); const d={...FaqAccordionProSchema.defaults,...block,faqs:normaliseFaqs(block.faqs)}; const [open,setOpen]=useState(0);
    return <section className={`group/repeatable-section px-6 py-20 sm:px-8 lg:py-28 ${theme.bg}`}><div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[.8fr_1.2fr] lg:gap-20"><Header d={d} theme={theme} onUpdate={onUpdate}/><div className={`overflow-hidden rounded-[2rem] border ${theme.border} ${theme.card}`}>{d.faqs.map((faq,i)=><article key={i} className={`group relative border-b last:border-b-0 ${theme.border}`}><button type="button" onClick={()=>setOpen(open===i?-1:i)} className="flex w-full items-start gap-4 p-6 pr-16 text-left sm:p-7 sm:pr-16"><span className={`mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-current/20 text-sm font-bold ${theme.text}`}>{open===i?'−':'+'}</span><span className={`pt-1 text-xs font-bold ${theme.sub}`}>{String(i+1).padStart(2,'0')}</span><EditableText value={faq.question} onSave={(v)=>faqUpdate(d,onUpdate,i,'question',v)} className={`flex-1 text-lg font-semibold ${theme.text}`}/></button>{open===i&&<div className="px-6 pb-7 pl-[4.25rem] sm:px-7 sm:pb-8 sm:pl-[4.5rem]"><EditableText value={faq.answer} isTextArea onSave={(v)=>faqUpdate(d,onUpdate,i,'answer',v)} className={`block text-sm leading-7 ${theme.sub}`}/></div>}<RepeatableRemoveButton overlay onRemove={()=>onUpdate({faqs:removeAt(d.faqs,i,1)})} disabled={d.faqs.length<=1}/></article>)}</div><RepeatableControls onAdd={()=>d.faqs.length<12&&onUpdate({faqs:cloneLast(d.faqs)})} onRemove={()=>onUpdate({faqs:removeAt(d.faqs,d.faqs.length-1,1)})} canAdd={d.faqs.length<12} canRemove={d.faqs.length>1} addLabel="Add question" removeLabel="Remove last" showRemove={false}/></div></section>;
}

export function FaqSearchPremiumBlock({block,onUpdate,globalTheme}){
    const theme=getEffectiveTheme(block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme,globalTheme); const d={...FaqSearchPremiumSchema.defaults,...block,faqs:normaliseFaqs(block.faqs)}; const [q,setQ]=useState(''); const shown=useMemo(()=>d.faqs.map((item,index)=>({item,index})).filter(({item})=>`${item.question} ${item.answer}`.toLowerCase().includes(q.trim().toLowerCase())),[d.faqs,q]);
    return <section className={`group/repeatable-section px-6 py-20 sm:px-8 lg:py-28 ${theme.bg}`}><div className="mx-auto max-w-6xl"><div className="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between"><Header d={d} theme={theme} onUpdate={onUpdate}/><div className={`flex min-w-0 items-center rounded-full border px-5 py-3 lg:w-96 ${theme.border} ${theme.card}`}><span className={`mr-3 ${theme.sub}`}>⌕</span><input value={q} onChange={e=>setQ(e.target.value)} placeholder={d.search_placeholder} className={`w-full bg-transparent text-sm outline-none ${theme.text}`}/></div></div><div className="mt-10 grid gap-4 md:grid-cols-2">{shown.map(({item:faq,index})=><article key={index} className={`group relative rounded-2xl border p-6 ${theme.border} ${theme.card}`}><EditableText value={faq.question} onSave={(v)=>faqUpdate(d,onUpdate,index,'question',v)} className={`block font-semibold ${theme.text}`}/><EditableText value={faq.answer} isTextArea onSave={(v)=>faqUpdate(d,onUpdate,index,'answer',v)} className={`mt-3 block text-sm leading-6 ${theme.sub}`}/><RepeatableRemoveButton overlay onRemove={()=>onUpdate({faqs:removeAt(d.faqs,index,1)})} disabled={d.faqs.length<=1}/></article>)}{shown.length===0&&<div className={`md:col-span-2 rounded-2xl border p-8 text-center text-sm ${theme.border} ${theme.sub}`}>No matching questions. Try another search.</div>}</div><RepeatableControls onAdd={()=>d.faqs.length<12&&onUpdate({faqs:cloneLast(d.faqs)})} onRemove={()=>onUpdate({faqs:removeAt(d.faqs,d.faqs.length-1,1)})} canAdd={d.faqs.length<12} canRemove={d.faqs.length>1} addLabel="Add question" removeLabel="Remove last" showRemove={false}/></div></section>;
}

export function FaqCategoriesPremiumBlock({block,onUpdate,globalTheme}){
    const theme=getEffectiveTheme(block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme,globalTheme); const d={...FaqCategoriesPremiumSchema.defaults,...block,categories:Array.isArray(block.categories)&&block.categories.length?block.categories:FaqCategoriesPremiumSchema.defaults.categories};
    const update=(ci,key,value)=>onUpdate({categories:d.categories.map((x,i)=>i===ci?{...x,[key]:value}:x)}); const updateFaq=(ci,fi,key,value)=>onUpdate({categories:d.categories.map((x,i)=>i===ci?{...x,faqs:(x.faqs||[]).map((f,j)=>j===fi?{...f,[key]:value}:f)}:x)});
    return <section className={`group/repeatable-section px-6 py-20 sm:px-8 lg:py-28 ${theme.bg}`}><div className="mx-auto max-w-7xl"><Header d={d} theme={theme} onUpdate={onUpdate}/><div className="mt-12 grid gap-6 lg:grid-cols-3">{d.categories.map((cat,ci)=><article key={ci} className={`group relative rounded-[1.75rem] border p-6 ${theme.border} ${theme.card}`}><EditableText value={cat.title} onSave={v=>update(ci,'title',v)} className={`block text-xl font-bold ${theme.text}`}/><EditableText value={cat.description} isTextArea onSave={v=>update(ci,'description',v)} className={`mt-2 block text-sm leading-6 ${theme.sub}`}/><div className={`mt-6 divide-y ${theme.border}`}>{(cat.faqs||[]).slice(0,4).map((faq,fi)=><details key={fi} className="group relative py-4 pr-10" defaultOpen={fi===0}><summary className={`cursor-pointer text-sm font-semibold ${theme.text}`}><EditableText value={faq.question} onSave={v=>updateFaq(ci,fi,'question',v)} className="inline"/></summary><EditableText value={faq.answer} isTextArea onSave={v=>updateFaq(ci,fi,'answer',v)} className={`mt-2 block text-sm leading-6 ${theme.sub}`}/><RepeatableRemoveButton overlay onRemove={()=>onUpdate({categories:d.categories.map((x,i)=>i===ci?{...x,faqs:removeAt(x.faqs||[],fi,1)}:x)})} disabled={(cat.faqs||[]).length<=1} label="Remove question"/></details>)}</div><RepeatableControls onAdd={()=>{const faqs=cat.faqs||[];if(faqs.length<4)onUpdate({categories:d.categories.map((x,i)=>i===ci?{...x,faqs:cloneLast(faqs,{question:"New question",answer:"Add the answer here."})}:x)})}} onRemove={()=>onUpdate({categories:d.categories.map((x,i)=>i===ci?{...x,faqs:removeAt(x.faqs||[],(x.faqs||[]).length-1,1)}:x)})} canAdd={(cat.faqs||[]).length<4} canRemove={(cat.faqs||[]).length>1} addLabel="Add question" removeLabel="Remove last question" showRemove={false}/><RepeatableRemoveButton overlay onRemove={()=>onUpdate({categories:removeAt(d.categories,ci,1)})} disabled={d.categories.length<=1} label="Remove category"/></article>)}</div><RepeatableControls onAdd={()=>d.categories.length<6&&onUpdate({categories:cloneLast(d.categories)})} onRemove={()=>onUpdate({categories:removeAt(d.categories,d.categories.length-1,1)})} canAdd={d.categories.length<6} canRemove={d.categories.length>1} addLabel="Add category" removeLabel="Remove last" showRemove={false}/></div></section>;
}

export function FaqSupportPortalPremiumBlock({block,onUpdate,globalTheme}){
    const theme=getEffectiveTheme(block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme,globalTheme); const d={...FaqSupportPortalPremiumSchema.defaults,...block,topics:Array.isArray(block.topics)&&block.topics.length?block.topics:FaqSupportPortalPremiumSchema.defaults.topics,faqs:normaliseFaqs(block.faqs)};
    const topicUpdate=(i,key,value)=>onUpdate({topics:d.topics.map((x,j)=>j===i?{...x,[key]:value}:x)});
    return <section className={`group/repeatable-section px-6 py-20 sm:px-8 lg:py-28 ${theme.bg}`}><div className="mx-auto max-w-7xl"><div className="flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between"><Header d={d} theme={theme} onUpdate={onUpdate}/><a href={d.primary_url||'/contact'} className="inline-flex w-fit rounded-full bg-slate-900 px-6 py-3 text-sm font-bold text-white"><EditableText value={d.primary_label} onSave={primary_label=>onUpdate({primary_label})} className="inline"/></a></div><div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{d.topics.map((t,i)=><article key={i} className={`group relative rounded-2xl border p-5 ${theme.border} ${theme.card}`}><EditableText value={t.count_label} onSave={v=>topicUpdate(i,'count_label',v)} className={`block text-[11px] font-bold uppercase tracking-[.18em] ${theme.sub}`}/><EditableText value={t.title} onSave={v=>topicUpdate(i,'title',v)} className={`mt-4 block text-lg font-bold ${theme.text}`}/><EditableText value={t.description} isTextArea onSave={v=>topicUpdate(i,'description',v)} className={`mt-2 block text-sm leading-6 ${theme.sub}`}/><RepeatableRemoveButton overlay onRemove={()=>onUpdate({topics:removeAt(d.topics,i,1)})} disabled={d.topics.length<=1}/></article>)}</div><RepeatableControls onAdd={()=>d.topics.length<8&&onUpdate({topics:cloneLast(d.topics)})} onRemove={()=>onUpdate({topics:removeAt(d.topics,d.topics.length-1,1)})} canAdd={d.topics.length<8} canRemove={d.topics.length>1} addLabel="Add topic" removeLabel="Remove last" showRemove={false}/><div className="mt-8 grid gap-4 md:grid-cols-2">{d.faqs.slice(0,4).map((faq,i)=><details key={i} className={`group relative rounded-2xl border p-5 ${theme.border} ${theme.card}`}><summary className={`cursor-pointer font-semibold ${theme.text}`}><EditableText value={faq.question} onSave={v=>faqUpdate(d,onUpdate,i,'question',v)} className="inline"/></summary><EditableText value={faq.answer} isTextArea onSave={v=>faqUpdate(d,onUpdate,i,'answer',v)} className={`mt-3 block text-sm leading-6 ${theme.sub}`}/><RepeatableRemoveButton overlay onRemove={()=>onUpdate({faqs:removeAt(d.faqs,i,1)})} disabled={d.faqs.length<=1} label="Remove question"/></details>)}</div><RepeatableControls onAdd={()=>d.faqs.length<4&&onUpdate({faqs:cloneLast(d.faqs)})} onRemove={()=>onUpdate({faqs:removeAt(d.faqs,d.faqs.length-1,1)})} canAdd={d.faqs.length<4} canRemove={d.faqs.length>1} addLabel="Add featured question" removeLabel="Remove last question" showRemove={false}/></div></section>;
}
