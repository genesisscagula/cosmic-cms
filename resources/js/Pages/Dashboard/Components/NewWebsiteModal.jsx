import { useEffect, useRef } from "react";
import { useForm } from "@inertiajs/react";

const INDUSTRY_OPTIONS = [["automotive","Automotive"],["bakery","Bakery"],["cleaning","Cleaning"],["coffee","Coffee shop"],["construction","Construction"],["dentist","Dental clinic"],["education","Education"],["electrician","Electrician"],["finance","Finance"],["fitness","Fitness"],["hotel","Hotel"],["landscaping","Landscaping"],["lawyer","Law firm"],["medical","Medical"],["plumbing","Plumbing"],["real-estate","Real estate"],["restaurant","Restaurant"],["technology","Technology"],["travel","Travel"]];

export default function NewWebsiteModal({open,onClose,template=null}){
 const nameInput=useRef(null);
 const {data,setData,post,processing,errors,reset,clearErrors}=useForm({name:"",domain:"",industry:"",location:"",business_description:"",template:null,website_type:"builder",design_system:null});
 useEffect(()=>{if(open){setData('website_type','builder');setData('template',template?.slug||null);setData('design_system',null);window.setTimeout(()=>nameInput.current?.focus(),80)}},[open,template]);
 if(!open)return null;
 const close=()=>{if(processing)return;reset();clearErrors();onClose()};
 const submit=e=>{e.preventDefault();post(route('websites.store'),{onSuccess:()=>{reset();onClose()}})};
 return <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
  <button className="absolute inset-0" onClick={close}/>
  <section className="relative w-full max-w-2xl rounded-2xl border border-white/10 bg-[#151519] p-6 text-slate-100 shadow-2xl">
   <div className="flex items-start justify-between"><div><p className="text-xs font-bold uppercase tracking-[.16em] text-violet-300">Workspace</p><h2 className="mt-2 text-xl font-semibold text-white">Create a website</h2><p className="mt-1 text-sm text-slate-400">Add the site details. Luna will handle layouts, Sparks and design changes inside the Builder.</p></div><button onClick={close} className="h-9 w-9 rounded-lg text-slate-400 hover:bg-white/10">×</button></div>
   <form onSubmit={submit} className="mt-6">
    <Details data={data} setData={setData} errors={errors} nameInput={nameInput}/>
    {errors.website_limit&&<p className="mt-3 text-sm text-amber-300">{errors.website_limit}</p>}
    <div className="mt-6 flex justify-end gap-2 border-t border-white/10 pt-5"><button type="button" onClick={close} className="rounded-xl px-4 py-2 text-sm font-semibold text-slate-300">Cancel</button><button type="submit" disabled={processing} className="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-950 disabled:opacity-50">{processing?'Creating…':'Create Website'}</button></div>
   </form>
  </section>
 </div>
}

function Details({data,setData,errors,nameInput}){return <div className="space-y-4"><div><label className="text-sm font-medium">Website name</label><input ref={nameInput} value={data.name} onChange={e=>setData('name',e.target.value)} required className="mt-2 h-11 w-full rounded-xl border border-white/10 bg-black/25 px-3"/>{errors.name&&<p className="text-xs text-red-300">{errors.name}</p>}</div><div><label className="text-sm font-medium">Domain</label><input type="url" value={data.domain} onChange={e=>setData('domain',e.target.value)} required placeholder="https://example.com" className="mt-2 h-11 w-full rounded-xl border border-white/10 bg-black/25 px-3"/></div><div className="grid gap-3 sm:grid-cols-2"><select value={data.industry} onChange={e=>setData('industry',e.target.value)} required className="h-11 rounded-xl border border-white/10 bg-[#19191d] px-3"><option value="">Select industry</option>{INDUSTRY_OPTIONS.map(([v,l])=><option key={v} value={v}>{l}</option>)}</select><input value={data.location} onChange={e=>setData('location',e.target.value)} required placeholder="Location" className="h-11 rounded-xl border border-white/10 bg-black/25 px-3"/></div><textarea rows={3} value={data.business_description} onChange={e=>setData('business_description',e.target.value)} required placeholder="What you offer, who you serve, and what makes you different." className="w-full rounded-xl border border-white/10 bg-black/25 px-3 py-2.5"/></div>}
