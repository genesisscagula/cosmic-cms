import { useEffect, useRef, useState } from "react";
import { useForm } from "@inertiajs/react";

const INDUSTRY_OPTIONS = [["automotive","Automotive"],["bakery","Bakery"],["cleaning","Cleaning"],["coffee","Coffee shop"],["construction","Construction"],["dentist","Dental clinic"],["education","Education"],["electrician","Electrician"],["finance","Finance"],["fitness","Fitness"],["hotel","Hotel"],["landscaping","Landscaping"],["lawyer","Law firm"],["medical","Medical"],["plumbing","Plumbing"],["real-estate","Real estate"],["restaurant","Restaurant"],["technology","Technology"],["travel","Travel"]];

export default function NewWebsiteModal({open,onClose,template=null,marketplaceUrl=null}){
 const nameInput=useRef(null);
 const [step,setStep]=useState("choose");
 const {data,setData,post,processing,errors,reset,clearErrors}=useForm({name:"",domain:"",industry:"",location:"",business_description:"",template:null,website_type:"builder",design_system:null});
 useEffect(()=>{
  if(!open)return;
  setStep(template ? "studio" : "choose");
  setData('website_type','builder');
  setData('template',template?.slug||null);
  setData('design_system',null);
  if(template)window.setTimeout(()=>nameInput.current?.focus(),80);
 },[open,template]);
 if(!open)return null;
 const close=()=>{if(processing)return;setStep("choose");reset();clearErrors();onClose()};
 const chooseStudio=()=>{clearErrors();setStep("studio");window.setTimeout(()=>nameInput.current?.focus(),80)};
 const browseMarketplace=()=>{
  if(processing)return;
  const destination=marketplaceUrl || (typeof route === 'function' ? route('marketplace.local.home') : '/marketplace');
  window.location.assign(destination);
 };
 const submit=e=>{e.preventDefault();post(route('websites.store'),{onSuccess:()=>{setStep("choose");reset();onClose()}})};
 return <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
  <button aria-label="Close create website" className="absolute inset-0" onClick={close}/>
  <section className="relative max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto rounded-2xl border border-white/10 bg-[#151519] p-5 text-slate-100 shadow-2xl sm:p-6">
   <div className="flex items-start justify-between gap-4"><div><p className="text-xs font-bold uppercase tracking-[.16em] text-violet-300">Create Website</p><h2 className="mt-2 text-xl font-semibold text-white">{step==="choose"?"How would you like to start?":"Build with Cosmic Studio"}</h2><p className="mt-1 max-w-2xl text-sm leading-6 text-slate-400">{step==="choose"?"Choose a creation path. Both Studio and Marketplace websites use the same Agency website allowance.":"Start from scratch and build your website with Luna and Cosmic Studio."}</p></div><button type="button" onClick={close} className="h-9 w-9 shrink-0 rounded-lg text-slate-400 hover:bg-white/10">×</button></div>

   {step==="choose" ? <div className="mt-6 grid gap-4 md:grid-cols-2">
    <button type="button" onClick={chooseStudio} className="group min-h-[210px] rounded-2xl border border-violet-400/20 bg-violet-400/[0.06] p-5 text-left transition hover:-translate-y-0.5 hover:border-violet-300/40 hover:bg-violet-400/[0.1] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-300">
     <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-400/15 text-xl text-violet-200">✦</span>
     <span className="mt-5 block text-lg font-semibold text-white">Build with Cosmic Studio</span>
     <span className="mt-2 block text-sm leading-6 text-slate-400">Start from scratch and build your website with Luna and Cosmic Studio.</span>
     <span className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-violet-200">Create with Studio <span aria-hidden="true">→</span></span>
    </button>
    <button type="button" onClick={browseMarketplace} className="group min-h-[210px] rounded-2xl border border-emerald-400/20 bg-emerald-400/[0.055] p-5 text-left transition hover:-translate-y-0.5 hover:border-emerald-300/40 hover:bg-emerald-400/[0.095] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
     <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-400/15 text-xl text-emerald-200">▦</span>
     <span className="mt-5 block text-lg font-semibold text-white">Choose from Marketplace</span>
     <span className="mt-2 block text-sm leading-6 text-slate-400">Start with a professionally designed complete website and personalize it with Luna.</span>
     <span className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-emerald-200">Browse Marketplace <span aria-hidden="true">→</span></span>
    </button>
    <p className="md:col-span-2 text-center text-xs leading-5 text-slate-500">Browsing Marketplace does not spend Cosmic Credits. Credits are used only after you confirm installation of a specific template.</p>
   </div> : <form onSubmit={submit} className="mt-6">
    <Details data={data} setData={setData} errors={errors} nameInput={nameInput}/>
    {errors.website_limit&&<p className="mt-3 text-sm text-amber-300">{errors.website_limit}</p>}
    <div className="mt-6 flex flex-col-reverse gap-2 border-t border-white/10 pt-5 sm:flex-row sm:justify-between">
     <button type="button" disabled={processing} onClick={()=>setStep("choose")} className="rounded-xl px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-white/[0.05] disabled:opacity-50">← Back</button>
     <div className="flex justify-end gap-2"><button type="button" onClick={close} className="rounded-xl px-4 py-2 text-sm font-semibold text-slate-300">Cancel</button><button type="submit" disabled={processing} className="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-950 disabled:opacity-50">{processing?'Creating…':'Create with Studio'}</button></div>
    </div>
   </form>}
  </section>
 </div>
}

function Details({data,setData,errors,nameInput}){return <div className="space-y-4"><div><label className="text-sm font-medium">Website name</label><input ref={nameInput} value={data.name} onChange={e=>setData('name',e.target.value)} required className="mt-2 h-11 w-full rounded-xl border border-white/10 bg-black/25 px-3"/>{errors.name&&<p className="text-xs text-red-300">{errors.name}</p>}</div><div><label className="text-sm font-medium">Domain</label><input type="url" value={data.domain} onChange={e=>setData('domain',e.target.value)} required placeholder="https://example.com" className="mt-2 h-11 w-full rounded-xl border border-white/10 bg-black/25 px-3"/>{errors.domain&&<p className="mt-1 text-xs text-red-300">{errors.domain}</p>}</div><div className="grid gap-3 sm:grid-cols-2"><select value={data.industry} onChange={e=>setData('industry',e.target.value)} required className="h-11 rounded-xl border border-white/10 bg-[#19191d] px-3"><option value="">Select industry</option>{INDUSTRY_OPTIONS.map(([v,l])=><option key={v} value={v}>{l}</option>)}</select><input value={data.location} onChange={e=>setData('location',e.target.value)} required placeholder="Location" className="h-11 rounded-xl border border-white/10 bg-black/25 px-3"/></div><textarea rows={3} value={data.business_description} onChange={e=>setData('business_description',e.target.value)} required placeholder="What you offer, who you serve, and what makes you different." className="w-full rounded-xl border border-white/10 bg-black/25 px-3 py-2.5"/></div>}
