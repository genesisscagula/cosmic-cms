import { useEffect, useRef, useState } from "react";
import { useForm } from "@inertiajs/react";

const INDUSTRY_OPTIONS = [["automotive","Automotive"],["bakery","Bakery"],["cleaning","Cleaning"],["coffee","Coffee shop"],["construction","Construction"],["dentist","Dental clinic"],["education","Education"],["electrician","Electrician"],["finance","Finance"],["fitness","Fitness"],["hotel","Hotel"],["landscaping","Landscaping"],["lawyer","Law firm"],["medical","Medical"],["plumbing","Plumbing"],["real-estate","Real estate"],["restaurant","Restaurant"],["technology","Technology"],["travel","Travel"]];

const CREATION_PATHS = {
 luna_ai: {
  icon: "✦",
  title: "Build with Luna AI",
  description: "Tell Luna about your business and let AI compose the website for you.",
  action: "Start with Luna",
  accent: "violet",
 },
 sparks: {
  icon: "▦",
  title: "Build with Sparks",
  description: "Assemble your website from ready-made premium sections and layouts.",
  action: "Create & Choose Sparks",
  accent: "sky",
 },
 page_builder: {
  icon: "⊞",
  title: "Build with Page Builder",
  description: "Create freely with drag-and-drop rows, columns, and smart global-styled elements.",
  action: "Create & Open Page Builder",
  accent: "amber",
 },
 marketplace: {
  icon: "◆",
  title: "Choose from Marketplace",
  description: "Choose a complete professionally designed website, install it with Cosmic Credits, then personalize it without losing the original design system.",
  action: "Browse Complete Websites",
  accent: "emerald",
 },
};

const CARD_CLASSES = {
 violet: "border-violet-400/20 bg-violet-400/[0.06] hover:border-violet-300/40 hover:bg-violet-400/[0.1] focus-visible:ring-violet-300",
 sky: "border-sky-400/20 bg-sky-400/[0.055] hover:border-sky-300/40 hover:bg-sky-400/[0.095] focus-visible:ring-sky-300",
 amber: "border-amber-300/20 bg-amber-300/[0.05] hover:border-amber-200/40 hover:bg-amber-300/[0.09] focus-visible:ring-amber-200",
 emerald: "border-emerald-400/20 bg-emerald-400/[0.055] hover:border-emerald-300/40 hover:bg-emerald-400/[0.095] focus-visible:ring-emerald-300",
};

const ICON_CLASSES = {
 violet: "bg-violet-400/15 text-violet-200",
 sky: "bg-sky-400/15 text-sky-200",
 amber: "bg-amber-300/15 text-amber-100",
 emerald: "bg-emerald-400/15 text-emerald-200",
};

const ACTION_CLASSES = {
 violet: "text-violet-200",
 sky: "text-sky-200",
 amber: "text-amber-100",
 emerald: "text-emerald-200",
};

export default function NewWebsiteModal({open,onClose,template=null,marketplaceUrl=null}){
 const nameInput=useRef(null);
 const [step,setStep]=useState("choose");
 const {data,setData,post,processing,errors,reset,clearErrors}=useForm({
  name:"",domain:"",industry:"",location:"",business_description:"",template:null,
  website_type:"builder",design_system:null,creation_mode:"luna_ai",luna_prompt:"",
 });

 useEffect(()=>{
  if(!open)return;
  const legacyTemplateMode = template ? "sparks" : "luna_ai";
  setStep(template ? "details" : "choose");
  setData('website_type','builder');
  setData('template',template?.slug||null);
  setData('design_system',null);
  setData('creation_mode',legacyTemplateMode);
  if(template)window.setTimeout(()=>nameInput.current?.focus(),80);
 },[open,template]);

 if(!open)return null;

 const close=()=>{if(processing)return;setStep("choose");reset();clearErrors();onClose()};
 const chooseMode=(mode)=>{
  if(mode === "marketplace"){
   if(processing)return;
   const destination=marketplaceUrl || (typeof route === 'function' ? route('marketplace.local.home') : '/marketplace');
   window.location.assign(destination);
   return;
  }
  clearErrors();
  setData('creation_mode',mode);
  setStep("details");
  window.setTimeout(()=>nameInput.current?.focus(),80);
 };
 const submit=e=>{e.preventDefault();post(route('websites.store'),{onSuccess:()=>{setStep("choose");reset();onClose()}})};
 const selectedPath=CREATION_PATHS[data.creation_mode] || CREATION_PATHS.luna_ai;

 return <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
  <button aria-label="Close create website" className="absolute inset-0" onClick={close}/>
  <section className="relative max-h-[calc(100vh-2rem)] w-full max-w-4xl overflow-y-auto rounded-2xl border border-white/10 bg-[#151519] p-5 text-slate-100 shadow-2xl sm:p-6">
   <div className="flex items-start justify-between gap-4">
    <div>
     <p className="text-xs font-bold uppercase tracking-[.16em] text-violet-300">Create Website</p>
     <h2 className="mt-2 text-xl font-semibold text-white">{step==="choose"?"Choose how you want to build":selectedPath.title}</h2>
     <p className="mt-1 max-w-2xl text-sm leading-6 text-slate-400">{step==="choose"?"Four clear starting paths. Every path creates the same Cosmic CMS Website and uses the same Agency website allowance.":selectedPath.description}</p>
    </div>
    <button type="button" onClick={close} className="h-9 w-9 shrink-0 rounded-lg text-slate-400 hover:bg-white/10">×</button>
   </div>

   {step==="choose" ? <div className="mt-6 grid gap-4 md:grid-cols-2">
    {Object.entries(CREATION_PATHS).map(([mode,path])=><button key={mode} type="button" onClick={()=>chooseMode(mode)} className={`group min-h-[196px] rounded-2xl border p-5 text-left transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 ${CARD_CLASSES[path.accent]}`}>
     <span className={`flex h-11 w-11 items-center justify-center rounded-xl text-xl ${ICON_CLASSES[path.accent]}`}>{path.icon}</span>
     <span className="mt-5 block text-lg font-semibold text-white">{path.title}</span>
     <span className="mt-2 block text-sm leading-6 text-slate-400">{path.description}</span>
     <span className={`mt-5 inline-flex items-center gap-2 text-sm font-semibold ${ACTION_CLASSES[path.accent]}`}>{path.action} <span aria-hidden="true">→</span></span>
    </button>)}
    <div className="md:col-span-2 rounded-xl border border-white/[0.07] bg-white/[0.025] px-4 py-3 text-center text-xs leading-5 text-slate-500">
     Luna AI, Sparks, and Page Builder are creation modes—not separate website products. Marketplace browsing never spends credits; credits are used only when you confirm a template installation.
    </div>
   </div> : <form onSubmit={submit} className="mt-6">
    <div className="mb-5 flex items-center gap-3 rounded-xl border border-white/[0.08] bg-white/[0.035] px-4 py-3">
     <span className={`flex h-9 w-9 items-center justify-center rounded-lg ${ICON_CLASSES[selectedPath.accent]}`}>{selectedPath.icon}</span>
     <div className="min-w-0"><p className="text-sm font-semibold text-white">{selectedPath.title}</p><p className="text-xs text-slate-500">Starting mode is saved with the website so each workflow can stay clean and focused.</p></div>
    </div>
    <Details data={data} setData={setData} errors={errors} nameInput={nameInput} mode={data.creation_mode}/>
    {errors.website_limit&&<p className="mt-3 text-sm text-amber-300">{errors.website_limit}</p>}
    {errors.creation_mode&&<p className="mt-3 text-sm text-red-300">{errors.creation_mode}</p>}
    <div className="mt-6 flex flex-col-reverse gap-2 border-t border-white/10 pt-5 sm:flex-row sm:justify-between">
     <button type="button" disabled={processing} onClick={()=>setStep("choose")} className="rounded-xl px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-white/[0.05] disabled:opacity-50">← Back</button>
     <div className="flex justify-end gap-2"><button type="button" onClick={close} className="rounded-xl px-4 py-2 text-sm font-semibold text-slate-300">Cancel</button><button type="submit" disabled={processing} className="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-950 disabled:opacity-50">{processing?'Creating…':selectedPath.action}</button></div>
    </div>
   </form>}
  </section>
 </div>
}

function Details({data,setData,errors,nameInput,mode}){
 const lunaMode=mode==='luna_ai';
 const sparksMode=mode==='sparks';
 const pageBuilderMode=mode==='page_builder';
 return <div className="space-y-4">
  {lunaMode ? <div className="rounded-2xl border border-violet-400/20 bg-violet-400/[0.06] p-4">
   <p className="text-xs font-bold uppercase tracking-[0.16em] text-violet-300">✦ Luna website brief</p>
   <p className="mt-2 text-sm leading-6 text-slate-300">Give Luna the business context once. After the website record is created, Luna opens automatically, recommends the best complete page bundle, and asks for confirmation before spending AI credits.</p>
  </div> : null}
  {sparksMode ? <div className="rounded-2xl border border-sky-400/20 bg-sky-400/[0.06] p-4">
   <p className="text-xs font-bold uppercase tracking-[0.16em] text-sky-300">▦ Sparks workspace</p>
   <p className="mt-2 text-sm leading-6 text-slate-300">Cosmic creates a clean Home page, opens the Sparks section library automatically, and lets you install ready-made sections directly. Customize each installed Spark later from its normal Edit Section controls.</p>
  </div> : null}
  {pageBuilderMode ? <div className="rounded-2xl border border-amber-300/20 bg-amber-300/[0.055] p-4">
   <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-200">⊞ Page Builder workspace</p>
   <p className="mt-2 text-sm leading-6 text-slate-300">Cosmic creates a clean Home page, opens a smart Build Your Own section automatically, and gives you the draggable rows, columns, and globally styled elements panel. Sparks and Luna creation prompts stay out of this starting flow.</p>
  </div> : null}
  <div><label className="text-sm font-medium">Website name</label><input ref={nameInput} value={data.name} onChange={e=>setData('name',e.target.value)} required className="mt-2 h-11 w-full rounded-xl border border-white/10 bg-black/25 px-3"/>{errors.name&&<p className="text-xs text-red-300">{errors.name}</p>}</div>
  <div className="grid gap-3 sm:grid-cols-2"><select value={data.industry} onChange={e=>setData('industry',e.target.value)} required className="h-11 rounded-xl border border-white/10 bg-[#19191d] px-3"><option value="">Select industry</option>{INDUSTRY_OPTIONS.map(([v,l])=><option key={v} value={v}>{l}</option>)}</select><input value={data.location} onChange={e=>setData('location',e.target.value)} required placeholder="Location" className="h-11 rounded-xl border border-white/10 bg-black/25 px-3"/></div>
  <div><label className="text-sm font-medium">Domain</label><input type="url" value={data.domain} onChange={e=>setData('domain',e.target.value)} required placeholder="https://example.com" className="mt-2 h-11 w-full rounded-xl border border-white/10 bg-black/25 px-3"/>{errors.domain&&<p className="mt-1 text-xs text-red-300">{errors.domain}</p>}</div>
  <div><label className="text-sm font-medium">Business summary</label><textarea rows={3} value={data.business_description} onChange={e=>setData('business_description',e.target.value)} required placeholder="What you offer, who you serve, and what makes you different." className="mt-2 w-full rounded-xl border border-white/10 bg-black/25 px-3 py-2.5"/></div>
  {lunaMode ? <div><label className="text-sm font-medium">What should Luna create? <span className="font-normal text-slate-500">Optional</span></label><textarea rows={3} value={data.luna_prompt||''} onChange={e=>setData('luna_prompt',e.target.value)} placeholder="e.g. Premium marine services website with Home, Services, Gallery, About and Contact. Clean editorial style with strong photography." className="mt-2 w-full rounded-xl border border-violet-400/20 bg-violet-400/[0.04] px-3 py-2.5 placeholder:text-slate-600"/><p className="mt-1.5 text-xs leading-5 text-slate-500">Optional direction only. Luna still uses your industry, location, business summary, global brand system and responsive defaults.</p>{errors.luna_prompt&&<p className="mt-1 text-xs text-red-300">{errors.luna_prompt}</p>}</div> : null}
 </div>
}
