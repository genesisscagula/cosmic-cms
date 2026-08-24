import { useState } from 'react';
import { EditableText } from '../Shared/EditableText';
import { EditableButton } from '../Shared/EditableButton';
import { EditableImage } from '../Shared/EditableImage';
import { sparkTw } from "../Shared/sparkTailwindRuntime";

export const LunaCustomSectionSchema = {
    type:'luna_custom_section', title:'Cosmic AI Custom Section', category:'Cosmic AI Custom', purpose:'Screenshot-rebuilt custom section',
    defaults:{category:'content',layout:'editorial',alignment:'left',media_position:'none',density:'balanced',accent_shape:'none',eyebrow:'',heading:'Custom section',text:'',primary_label:'',primary_url:'#',secondary_label:'',secondary_url:'#',image_url:'',items:[],review:null,form:null,runtime:null,visual_style:{},style_overrides:{}}, fields:[],
};
const num=(v,f)=>Number.isFinite(Number(v))?Number(v):f;
const color=(v,f)=>/^#[0-9a-f]{6}([0-9a-f]{2})?$/i.test(v||'')?v:f;
const ICON_PATHS={
 'shield-check':'M12 3 5 6v5c0 4.6 2.9 7.5 7 10 4.1-2.5 7-5.4 7-10V6l-7-3zm-3 8 2 2 4-4',
 settings:'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm0-5v2m0 14v2M3 12h2m14 0h2M5.6 5.6 7 7m10 10 1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4',
 truck:'M3 6h11v10H3V6zm11 4h4l3 3v3h-7v-6zM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
 headset:'M4 13v-1a8 8 0 0 1 16 0v1m-16 0h3v6H5a1 1 0 0 1-1-1v-5zm16 0h-3v6h2a1 1 0 0 0 1-1v-5zm-3 6c0 2-2 2-4 2',
 'check-circle':'M21 12a9 9 0 1 1-4-7.5M9 12l2 2 6-7', star:'M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3z',
 building:'M5 21V4h10v17M9 8h2m-2 4h2m-2 4h2m6-7h3v12', home:'M3 11 12 4l9 7v10h-6v-6H9v6H3V11z',
 ruler:'M4 17 17 4l3 3L7 20l-3-3zm9-9 3 3m-6 0 2 2m-5 1 2 2', layers:'M12 3 3 8l9 5 9-5-9-5zm-9 10 9 5 9-5m-18 5 9 5 9-5',
 sparkles:'M12 3l1.3 3.7L17 8l-3.7 1.3L12 13l-1.3-3.7L7 8l3.7-1.3L12 3zM5 14l.8 2.2L8 17l-2.2.8L5 20l-.8-2.2L2 17l2.2-.8L5 14z',
 phone:'M6 3h3l1.5 4-2 1.5a16 16 0 0 0 7 7l1.5-2 4 1.5v3c0 1.7-1.3 3-3 3C9.7 21 3 14.3 3 6c0-1.7 1.3-3 3-3z',
 mail:'M3 6h18v12H3V6zm0 1 9 6 9-6', 'map-pin':'M12 21s7-6.1 7-12a7 7 0 1 0-14 0c0 5.9 7 12 7 12zm0-9a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
 clock:'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zm0-13v5l3 2', users:'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm6-1a3 3 0 1 0 0-6m-12 17v-2a6 6 0 0 1 12 0v2m2-7a5 5 0 0 1 5 5v2'
};
function CosmicIcon({name,color='#2563eb',size=30}){const d=ICON_PATHS[String(name||'').toLowerCase()];if(!d)return null;return <svg aria-hidden="true" viewBox="0 0 24 24" width={size} height={size} fill="none" stroke={color} strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d={d}/></svg>}


function AccentHeading({block,style,onUpdate}){
 const heading=String(block.heading||'Custom section'),accent=String(block.heading_accent_text||'').trim();
 if(!accent||!heading.includes(accent))return <EditableText value={heading} className={sparkTw(block, "text", "cosmic-custom-heading block font-black")} style={style} onSave={heading=>onUpdate({heading})}/>;
 const parts=heading.split(accent);return <div className={sparkTw(block, "wrapper", "relative group/text cursor-pointer max-w-full block w-full")} onDoubleClick={()=>{const next=window.prompt('Edit heading',heading);if(next!==null)onUpdate({heading:next})}}><span className={sparkTw(block, "label", "cosmic-custom-heading block font-black whitespace-pre-line")} style={style}>{parts.map((part,i)=><span key={i}>{part}{i<parts.length-1?<span style={{color:color(block.visual_style?.heading_accent_color,style.color)}}>{accent}</span>:null}</span>)}</span></div>;
}

function Review({block,review,style}){if(!review?.text&&!review?.stars)return null;return <div className={sparkTw(block, "wrapper_2", "inline-flex flex-wrap items-center gap-3 px-4 py-2 text-sm")} style={{background:style.reviewBg,borderRadius:style.reviewRadius,boxShadow:style.reviewShadow}}><span style={{color:style.reviewStars}}> {'★'.repeat(Math.max(0,Math.min(5,Number(review.stars)||5)))} </span><span style={{color:style.reviewText,fontSize:style.reviewSize}}>{review.text}</span></div>}

const runtimeNumber=(value,fallback=0)=>Number.isFinite(Number(value))?Number(value):fallback;
const runtimeFormat=(value,format,currency='USD')=>{
 const n=runtimeNumber(value,0);
 if(format==='currency')return new Intl.NumberFormat(undefined,{style:'currency',currency:currency||'USD',maximumFractionDigits:0}).format(n);
 if(format==='percent')return `${Math.round(n*100)/100}%`;
 if(format==='integer')return new Intl.NumberFormat(undefined,{maximumFractionDigits:0}).format(n);
 return new Intl.NumberFormat(undefined,{maximumFractionDigits:2}).format(n);
};
const runtimeFormula=(formula,values)=>{
 const raw=String(formula||'').trim();
 if(!raw||raw.length>500)return 0;
 const identifiers=raw.match(/[A-Za-z_][A-Za-z0-9_]*/g)||[];
 const allowedFns={min:Math.min,max:Math.max,round:Math.round,floor:Math.floor,ceil:Math.ceil,abs:Math.abs,pow:Math.pow,if:(c,a,b)=>c?a:b};
 for(const id of identifiers){if(!(id in values)&&!(id in allowedFns))return 0;}
 if(!/^[0-9A-Za-z_+\-*/%().,<>=!&|\s]+$/.test(raw))return 0;
 try{
   const names=[...Object.keys(values),...Object.keys(allowedFns)];
   const args=[...Object.values(values).map(v=>runtimeNumber(v,0)),...Object.values(allowedFns)];
   // Formula is constrained above: no property access, quotes, brackets, semicolons, assignments, browser globals or unknown identifiers.
   return runtimeNumber(Function(...names,`"use strict";return (${raw});`)(...args),0);
 }catch{return 0;}
};

function RuntimeCard({block,runtime,form,onRuntimeValues,style}){
 const states=Array.isArray(runtime?.state)?runtime.state.slice(0,16):[];
 const inputs=Array.isArray(runtime?.inputs)?runtime.inputs.slice(0,16):[];
 const computed=Array.isArray(runtime?.computed)?runtime.computed.slice(0,16):[];
 const conditions=Array.isArray(runtime?.conditions)?runtime.conditions.slice(0,16):[];
 const actions=Array.isArray(runtime?.actions)?runtime.actions.slice(0,12):[];
 const views=Array.isArray(runtime?.views)?runtime.views.slice(0,10):[];
 const steps=Array.isArray(runtime?.steps)?runtime.steps.slice(0,10):[];
 const modals=Array.isArray(runtime?.modals)?runtime.modals.slice(0,8):[];
 const collections=Array.isArray(runtime?.collections)?runtime.collections.slice(0,4):[];
 const outputs=Array.isArray(runtime?.outputs)?runtime.outputs.slice(0,12):[];
 const initial={...Object.fromEntries(states.map(x=>[x.key,x.default??0])),...Object.fromEntries(inputs.map(x=>[x.key,x.default??'']))};
 if(views.length&&!('active_tab' in initial))initial.active_tab=views[0]?.key;
 if(steps.length&&!('current_step' in initial))initial.current_step=0;
 const [values,setValues]=useState(initial);
 const [openModal,setOpenModal]=useState(null);
 const [collectionQuery,setCollectionQuery]=useState({});
 const [collectionFilter,setCollectionFilter]=useState({});
 const all={...values};
 computed.forEach(item=>{all[item.key]=runtimeFormula(item.formula,all)});
 const update=(key,value)=>{const next={...values,[key]:value};setValues(next);onRuntimeValues?.({...next,...Object.fromEntries(computed.map(item=>[item.key,runtimeFormula(item.formula,{...next})]))});};
 const test=(condition)=>{
   const source=all[condition?.source], target=condition?.value;
   switch(condition?.operator){case'neq':return source!=target;case'gt':return Number(source)>Number(target);case'gte':return Number(source)>=Number(target);case'lt':return Number(source)<Number(target);case'lte':return Number(source)<=Number(target);case'contains':return String(source??'').includes(String(target??''));case'truthy':return Boolean(source);case'falsy':return !source;default:return source==target;}
 };
 const visible=(key)=>!conditions.some(c=>c.target===key&&((c.effect==='show'&&!test(c))||(c.effect==='hide'&&test(c))));
 const disabled=(key)=>conditions.some(c=>c.target===key&&((c.effect==='enable'&&!test(c))||(c.effect==='disable'&&test(c))));
 const act=(a)=>{
   const type=a?.type,target=a?.target;
   if(type==='set_value'||type==='select_tab'||type==='select_item'||type==='filter'||type==='search'||type==='sort')update(target,a.value);
   else if(type==='toggle')update(target,!values[target]);
   else if(type==='increment')update(target,runtimeNumber(values[target])+runtimeNumber(a.value,1));
   else if(type==='decrement')update(target,runtimeNumber(values[target])-runtimeNumber(a.value,1));
   else if(type==='open_modal')setOpenModal(target);
   else if(type==='close_modal')setOpenModal(null);
   else if(type==='next_step')update('current_step',Math.min(steps.length-1,runtimeNumber(values.current_step)+1));
   else if(type==='previous_step')update('current_step',Math.max(0,runtimeNumber(values.current_step)-1));
   else if(type==='go_to_step')update('current_step',Math.max(0,Math.min(steps.length-1,runtimeNumber(a.value))));
   else if(type==='reset')setValues(initial);
 };
 if(!inputs.length&&!computed.length&&!views.length&&!steps.length&&!modals.length&&!collections.length&&!outputs.length)return null;
 return <div className={sparkTw(block, "wrapper_3", "w-full space-y-5 rounded-2xl border border-black/10")} style={{background:style.cardBg,padding:style.cardPadding}}>
   {views.length?<div className={sparkTw(block, "wrapper_4", "flex flex-wrap gap-2")}>{views.map(v=><button key={v.key} type="button" onClick={()=>update('active_tab',v.key)} className="rounded-full border px-4 py-2 text-sm font-bold" style={{background:values.active_tab===v.key?style.accent:'transparent',color:style.heading}}>{v.label||v.title||v.key}</button>)}</div>:null}
   {views.filter(v=>values.active_tab===v.key&&visible(v.key)).map(v=><div key={v.key} className={sparkTw(block, "wrapper_5", "rounded-xl border border-black/10 p-4")}><div className={sparkTw(block, "wrapper_6", "font-bold")} style={{color:style.heading}}>{v.title}</div>{v.text?<p className={sparkTw(block, "body", "mt-2 text-sm")} style={{color:style.body}}>{v.text}</p>:null}</div>)}
   {steps.length?<div><div className={sparkTw(block, "wrapper_7", "mb-3 flex gap-1")}>{steps.map((_,i)=><span key={i} className={sparkTw(block, "label_2", "h-1.5 flex-1 rounded-full")} style={{background:i<=runtimeNumber(values.current_step)?style.accent:'rgba(0,0,0,.1)'}}/>)}</div>{steps[runtimeNumber(values.current_step)]?<div><div className={sparkTw(block, "wrapper_8", "font-bold")} style={{color:style.heading}}>{steps[runtimeNumber(values.current_step)].title||steps[runtimeNumber(values.current_step)].label}</div><p className={sparkTw(block, "body_2", "mt-2 text-sm")} style={{color:style.body}}>{steps[runtimeNumber(values.current_step)].text}</p></div>:null}</div>:null}
   <div className={sparkTw(block, "wrapper_9", "grid gap-5 md:grid-cols-2")}>{inputs.filter(input=>visible(input.key)).map((input,i)=>{
     const control=['text','email','tel','number','range','select','radio','checkbox','toggle','date','time','search'].includes(input.control)?input.control:'number', value=values[input.key]??input.default??'';
     const common="h-12 w-full rounded-xl border border-black/10 bg-white/80 px-3";
     return <label key={input.key||i} className={input.width==='full'?'md:col-span-2':'block'}><span className={sparkTw(block, "label_3", "mb-2 flex justify-between gap-3 text-sm font-semibold")} style={{color:style.heading}}><span>{input.label||input.key}</span>{control==='range'?<span>{runtimeFormat(value,input.format,input.currency)}</span>:null}</span>
     {control==='select'||control==='radio'?<div className={control==='radio'?'flex flex-wrap gap-3':''}>{control==='select'?<select disabled={disabled(input.key)} value={value} onChange={e=>update(input.key,e.target.value)} className={common}>{(input.options||[]).slice(0,20).map((o,j)=>{const ov=typeof o==='object'?o.value:o,ol=typeof o==='object'?o.label:o;return <option key={j} value={ov}>{ol}</option>})}</select>:(input.options||[]).slice(0,12).map((o,j)=>{const ov=typeof o==='object'?o.value:o,ol=typeof o==='object'?o.label:o;return <span key={j} className={sparkTw(block, "label_4", "inline-flex items-center gap-1")}><input type="radio" checked={String(value)===String(ov)} onChange={()=>update(input.key,ov)}/>{ol}</span>})}</div>
     :['checkbox','toggle'].includes(control)?<input disabled={disabled(input.key)} type="checkbox" checked={Boolean(value)} onChange={e=>update(input.key,e.target.checked?1:0)} className={sparkTw(block, "runtime_checkbox", "h-5 w-5")}/>
     :<input disabled={disabled(input.key)} type={control==='range'?'range':control} value={value} min={input.min} max={input.max} step={input.step||1} onChange={e=>update(input.key,['number','range'].includes(control)?Number(e.target.value):e.target.value)} className={sparkTw(block, 'runtime_input', control==='range'?'w-full accent-emerald-600':common)}/>}</label>
   })}</div>
   {computed.length||outputs.length?<div className={sparkTw(block, "wrapper_10", "grid gap-3 sm:grid-cols-2")}>{(outputs.length?outputs:computed).map((item,i)=>{const source=item.source||item.key,val=all[source],hero=item.display==='hero_result';return visible(item.key||source)?<div key={item.key||i} className={sparkTw(block, "wrapper_11", `rounded-xl border border-black/10 bg-black/[.025] p-4 ${hero?'sm:col-span-2 text-center':''}`)}><div className={sparkTw(block, "wrapper_12", "text-xs font-semibold uppercase tracking-wide")} style={{color:style.body}}>{item.label||source}</div><div className={hero?'mt-2 text-4xl font-black':'mt-1 text-2xl font-black'} style={{color:style.heading}}>{item.prefix||''}{runtimeFormat(val,item.format,item.currency)}{item.suffix||''}</div></div>:null})}</div>:null}
   {collections.map(col=>{const items=Array.isArray(col.items)?col.items:[],q=String(collectionQuery[col.key]||'').toLowerCase(),filter=collectionFilter[col.key]||'all',filterKey=(col.filterable||[])[0],cats=filterKey?[...new Set(items.map(x=>x?.[filterKey]).filter(Boolean))]:[],shown=items.filter(x=>(!q||JSON.stringify(x).toLowerCase().includes(q))&&(filter==='all'||x?.[filterKey]===filter));return <div key={col.key}><div className={sparkTw(block, "wrapper_13", "mb-3 flex flex-wrap gap-2")}><input placeholder="Search" value={collectionQuery[col.key]||''} onChange={e=>setCollectionQuery(v=>({...v,[col.key]:e.target.value}))} className={sparkTw(block, "collection_search", "h-10 rounded-lg border px-3")}/>{cats.length?<select value={filter} onChange={e=>setCollectionFilter(v=>({...v,[col.key]:e.target.value}))} className={sparkTw(block, "collection_search", "h-10 rounded-lg border px-3")}><option value="all">All</option>{cats.map(c=><option key={c} value={c}>{c}</option>)}</select>:null}</div><div className={sparkTw(block, "wrapper_14", "grid gap-3 sm:grid-cols-2 lg:grid-cols-3")}>{shown.slice(0,24).map((item,i)=><div key={i} className={sparkTw(block, "wrapper_15", "rounded-xl border border-black/10 p-4")}><div className={sparkTw(block, "wrapper_16", "font-bold")} style={{color:style.heading}}>{item.title||item.name||`Item ${i+1}`}</div>{item.text||item.description?<p className={sparkTw(block, "body_3", "mt-2 text-sm")} style={{color:style.body}}>{item.text||item.description}</p>:null}</div>)}</div></div>})}
   {actions.length?<div className={sparkTw(block, "wrapper_17", "flex flex-wrap gap-2")}>{actions.filter(a=>a.type!=='submit_form').map((a,i)=><button key={i} type="button" onClick={()=>act(a)} className="rounded-full border px-4 py-2 text-sm font-bold" style={{background:a.type==='reset'?'transparent':style.accent,color:style.heading}}>{a.label||a.type.replaceAll('_',' ')}</button>)}</div>:null}
   {openModal?modals.filter(m=>m.key===openModal).map(m=><div key={m.key} className={sparkTw(block, "wrapper_18", "fixed inset-0 z-[10050] grid place-items-center bg-black/60 p-4")} onClick={()=>setOpenModal(null)}><div className={sparkTw(block, "wrapper_19", "w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl")} onClick={e=>e.stopPropagation()}><div className={sparkTw(block, "wrapper_20", "text-xl font-black text-slate-900")}>{m.title}</div><p className={sparkTw(block, "body_4", "mt-3 text-sm text-slate-600")}>{m.text}</p><button type="button" onClick={()=>setOpenModal(null)} className="mt-5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">{m.button_label||'Close'}</button></div></div>):null}
 </div>;
}

function FormCard({form,block,onUpdate,style}){
 if(!form)return null;
 const fields=Array.isArray(form.fields)?form.fields:[];
 const columns=Number(form.columns)===1?1:2;
 const align=form.button_alignment==='center'?'justify-center':form.button_alignment==='right'?'justify-end':'justify-start';
 const noteBelow=form.note_position==='below';
 const controlClass="h-14 w-full rounded-xl border border-black/10 bg-black/[.025] px-4 text-sm";
 const renderField=(f,i)=>{
   const type=['email','tel','textarea','select','checkbox','hidden'].includes(f.type)?f.type:'text';
   if(type==='hidden')return null;
   const span=f.width==='full'?'sm:col-span-2':'';
   if(type==='textarea')return <textarea key={i} placeholder={f.placeholder||f.label||f.name||''} disabled rows={4} className={sparkTw(block, "wrapper_21", `${controlClass} ${span} h-auto min-h-28 py-3`)}/>;
   if(type==='select')return <select key={i} disabled className={sparkTw(block, "wrapper_22", `${controlClass} ${span}`)} defaultValue=""><option value="">{f.placeholder||f.label||'Select an option'}</option>{(Array.isArray(f.options)?f.options:[]).map((option,j)=><option key={j} value={option}>{option}</option>)}</select>;
   if(type==='checkbox')return <label key={i} className={sparkTw(block, "wrapper_23", `${span} flex items-start gap-2 text-sm`)} style={{color:style.body}}><input type="checkbox" disabled className={sparkTw(block, "input", "mt-1")}/><span>{f.label||f.placeholder||f.name}{f.required?' *':''}</span></label>;
   return <input key={i} type={type} placeholder={`${f.placeholder||f.label||f.name||''}${f.required?' *':''}`} disabled className={sparkTw(block, "input_2", `${controlClass} ${span}`)}/>;
 };
 return <div className={sparkTw(block, "wrapper_24", "w-full")} style={{background:style.cardBg,borderRadius:style.cardRadius,padding:style.cardPadding}}>
   <EditableText value={form.title||''} className={sparkTw(block, "text_2", "block font-bold")} style={{color:style.heading}} onSave={title=>onUpdate({form:{...form,title}})}/>
   <div className={sparkTw(block, "wrapper_25", `mt-5 grid gap-3 ${columns===2?'sm:grid-cols-2':'grid-cols-1'}`)}>{fields.map(renderField)}</div>
   <div className={sparkTw(block, "wrapper_26", `mt-4 flex flex-wrap items-center gap-4 ${align}`)}>{(form.button_label||block.primary_label)?<EditableButton label={form.button_label||block.primary_label} url={block.primary_url||'#'} className={sparkTw(block, "button", "px-6 py-3 text-sm font-bold")} style={{background:style.accent,color:'#222',borderRadius:style.buttonRadius}} onSave={(label,url)=>onUpdate({form:{...form,button_label:label},primary_label:label,primary_url:url})}/>:null}{form.note&&!noteBelow?<EditableText value={form.note} className={sparkTw(block, "text_3", "text-sm")} style={{color:style.body}} onSave={note=>onUpdate({form:{...form,note}})}/>:null}</div>
   {form.note&&noteBelow?<EditableText value={form.note} className={sparkTw(block, "text_4", "mt-3 block text-sm")} style={{color:style.body}} onSave={note=>onUpdate({form:{...form,note}})}/>:null}
 </div>
}

function ElementAsk({onAsk,label}){if(!onAsk)return null;return <button type="button" onClick={(e)=>{e.stopPropagation();onAsk()}} className="absolute -right-9 top-1/2 z-30 hidden h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full border border-emerald-300/30 bg-slate-950/90 text-[11px] text-emerald-200 shadow-lg group-hover/ai-element:inline-flex" title={`Ask Cosmic about ${label}`}>✦</button>}

export function LunaCustomSectionBlock({block,blockIndex,onUpdate,onOpenCosmicAI,onSaveCustomSpark,savingCustomSpark}){
 const v=block.visual_style||{},o=block.style_overrides||{};
 const [runtimeValues,setRuntimeValues]=useState({});
 const s={heading:color(v.heading_color,'#27272a'),body:color(v.body_color,'#3f3f46'),accent:color(v.accent_color,'#f5b68c'),bg:color(v.background_color,'#f3f7f4'),cardBg:color(v.card_background,'#ffffff'),headingSize:num(o.heading_size,num(v.heading_size,52)),bodySize:num(o.body_size,num(v.body_size,16)),eyebrowSize:num(v.eyebrow_size,14),padX:num(v.section_padding_x,48),padY:num(o.section_padding_y,num(v.section_padding_y,64)),gap:num(o.content_gap,num(v.content_gap,20)),cardPadding:num(o.card_padding,num(v.card_padding,28)),cardRadius:num(v.card_radius,18),buttonRadius:num(v.button_radius,999),minHeight:num(v.section_min_height,560),aspectRatio:Math.max(0,Math.min(6,num(v.reference_aspect_ratio,0))),maxWidth:num(v.content_max_width||v.content_width,1280),copyWidth:Math.min(100,Math.max(30,num(v.copy_width_percent,52))),headingLineHeight:num(v.heading_line_height,1.02),bodyLineHeight:num(v.body_line_height,1.45),backgroundPosition:v.background_position||'center center',backgroundSize:v.background_size||'cover',reviewBg:color(v.review_background,'#ffffffcc'),reviewRadius:num(v.review_radius,999),reviewShadow:v.review_shadow==='none'?'none':'0 1px 3px rgba(0,0,0,.12)',reviewStars:color(v.review_star_color,color(v.accent_color,'#f5b68c')),reviewText:color(v.review_text_color,color(v.body_color,'#3f3f46')),reviewSize:num(v.review_text_size,14),itemColumns:Math.max(1,Math.min(6,num(v.item_columns,3))),itemGap:num(v.item_gap,16),itemImageRatio:v.item_image_ratio||'4/3',itemCardStyle:v.item_card_style||'card',itemAlign:v.item_align||'left',copyX:Math.max(0,Math.min(100,num(v.copy_position_x,0))),copyY:Math.max(0,Math.min(100,num(v.copy_position_y,50))),mediaWidth:Math.max(20,Math.min(80,num(v.media_width_percent,50))),mediaHeight:num(v.media_height_px,0)};
 const responsive={
   headingTablet:num(o.heading_size_tablet,num(v.heading_size_tablet,Math.round(s.headingSize*.82))),
   headingMobile:num(o.heading_size_mobile,num(v.heading_size_mobile,Math.round(s.headingSize*.64))),
   bodyTablet:num(o.body_size_tablet,num(v.body_size_tablet,s.bodySize)),
   bodyMobile:num(o.body_size_mobile,num(v.body_size_mobile,Math.max(14,s.bodySize-1))),
   padTablet:num(o.section_padding_y_tablet,num(v.section_padding_y_tablet,Math.round(s.padY*.75))),
   padMobile:num(o.section_padding_y_mobile,num(v.section_padding_y_mobile,Math.max(36,Math.round(s.padY*.52)))),
   gapTablet:num(o.content_gap_tablet,num(v.content_gap_tablet,s.gap)),
   gapMobile:num(o.content_gap_mobile,num(v.content_gap_mobile,Math.max(10,Math.round(s.gap*.75)))),
 };
 const background=block.media_position==='background'&&block.image_url;
 const copy=<div className={sparkTw(block, "wrapper_27", "cosmic-custom-copy relative z-10 flex flex-col items-start text-left")} style={{gap:s.gap,maxWidth:`${s.copyWidth}%`}}>
   {block.eyebrow?<div className={sparkTw(block, "wrapper_28", "group/ai-element relative w-full")}><EditableText value={block.eyebrow} className={sparkTw(block, "text_5", "block font-bold")} style={{fontSize:s.eyebrowSize,color:s.heading}} onSave={eyebrow=>onUpdate({eyebrow})}/><ElementAsk label="eyebrow" onAsk={onOpenCosmicAI?()=>onOpenCosmicAI({scope:'element',key:'eyebrow'}):null}/></div>:null}
   <div className={sparkTw(block, "wrapper_29", "group/ai-element relative w-full")}><AccentHeading block={block} style={{fontSize:`clamp(${Math.max(30,Math.round(s.headingSize*.7))}px,5vw,${s.headingSize}px)`,color:s.heading,lineHeight:s.headingLineHeight}} onUpdate={onUpdate}/><ElementAsk label="heading" onAsk={onOpenCosmicAI?()=>onOpenCosmicAI({scope:'element',key:'heading'}):null}/></div>
   {block.text?<div className={sparkTw(block, "wrapper_30", "group/ai-element relative w-full")}><EditableText value={block.text} isTextArea className={sparkTw(block, "text_6", "cosmic-custom-body block")} style={{fontSize:s.bodySize,color:s.body,lineHeight:s.bodyLineHeight}} onSave={text=>onUpdate({text})}/><ElementAsk label="body text" onAsk={onOpenCosmicAI?()=>onOpenCosmicAI({scope:'element',key:'text'}):null}/></div>:null}
   <Review block={block} review={block.review} style={s}/>
   {!block.form&&(block.primary_label||block.secondary_label)?<div className={sparkTw(block, "wrapper_31", "group/ai-element relative flex flex-wrap gap-3")}>{block.primary_label?<EditableButton label={block.primary_label} url={block.primary_url||'#'} className={sparkTw(block, "button_2", "px-6 py-3 text-sm font-bold")} style={{background:s.accent,color:s.heading,borderRadius:s.buttonRadius}} onSave={(primary_label,primary_url)=>onUpdate({primary_label,primary_url})}/>:null}{block.secondary_label?<EditableButton label={block.secondary_label} url={block.secondary_url||'#'} className={sparkTw(block, "button_3", "border px-6 py-3 text-sm font-bold")} style={{color:s.heading,borderRadius:s.buttonRadius}} onSave={(secondary_label,secondary_url)=>onUpdate({secondary_label,secondary_url})}/>:null}<ElementAsk label="buttons" onAsk={onOpenCosmicAI?()=>onOpenCosmicAI({scope:'element',key:'buttons'}):null}/></div>:null}
   <RuntimeCard block={block} runtime={block.runtime} form={block.form} onRuntimeValues={setRuntimeValues} style={s}/>
   <FormCard form={block.form} block={block} onUpdate={onUpdate} style={s}/>
 </div>;

 const media=block.image_url&&block.media_position!=='none'&&block.media_position!=='background'?<div className={sparkTw(block, "wrapper_32", "group/ai-element relative z-10 min-h-[280px] overflow-hidden")} style={{borderRadius:s.cardRadius}}><EditableImage websiteId={null} blockIndex={blockIndex} src={block.image_url} showOverlay={false} blockType={block.type} className={sparkTw(block, "image", "h-full min-h-[280px] w-full object-cover")} style={{objectPosition:s.backgroundPosition}} onSave={image_url=>onUpdate({image_url})}/><ElementAsk label="image" onAsk={onOpenCosmicAI?()=>onOpenCosmicAI({scope:'element',key:'image'}):null}/></div>:null;
 const split=['left','right'].includes(block.media_position)||['split','showcase','feature_row'].includes(block.layout);
 const splitCols=block.media_position==='left'?`${s.mediaWidth}% ${100-s.mediaWidth}%`:`${100-s.mediaWidth}% ${s.mediaWidth}%`;
 const mainBody=split&&media?<div className={sparkTw(block, "wrapper_33", "relative mx-auto grid min-h-[inherit] items-center")} style={{maxWidth:s.maxWidth,gap:s.gap*2,gridTemplateColumns:splitCols}}>{block.media_position==='left'?<>{media}{copy}</>:<>{copy}{media}</>}</div>:<div className={sparkTw(block, "wrapper_34", "relative mx-auto flex min-h-[inherit] flex-col justify-center")} style={{maxWidth:s.maxWidth}}>{block.media_position==='top'&&media?<div className={sparkTw(block, "wrapper_35", "mb-10")}>{media}</div>:null}{copy}</div>;
 const items=Array.isArray(block.items)?block.items:[];
 const trustStrip=block.layout==='trust_strip'||block.layout==='process_strip';
 const bareItems=s.itemCardStyle==='bare'||block.layout==='rail'||block.layout==='process_strip';
 const itemGrid=items.length?<div className={sparkTw(block, "wrapper_36", "relative z-10 mx-auto mt-10 grid grid-cols-1")} style={{maxWidth:s.maxWidth,gap:trustStrip?0:s.itemGap,gridTemplateColumns:`repeat(${s.itemColumns}, minmax(0, 1fr))`}}>{items.slice(0,8).map((item,i)=><article key={i} className={trustStrip?'flex items-start gap-4 border-r last:border-r-0':'flex items-start gap-3'} style={trustStrip?{padding:`${Math.min(s.cardPadding,22)}px`,borderColor:'rgba(127,127,127,.25)'}:bareItems?{padding:0}:{background:s.cardBg,borderRadius:s.cardRadius,padding:s.cardPadding,border:'1px solid rgba(0,0,0,.08)'}}>{item.icon?<div className={sparkTw(block, "wrapper_37", "shrink-0")}><CosmicIcon name={item.icon} color={s.accent} size={32}/></div>:null}<div className={sparkTw(block, "wrapper_38", "min-w-0 flex-1")} style={{textAlign:s.itemAlign}}>{item.image_url?<div className={sparkTw(block, "wrapper_39", "mb-4 overflow-hidden")} style={{borderRadius:bareItems?Math.max(6,s.cardRadius-6):Math.max(8,s.cardRadius-4),aspectRatio:s.itemImageRatio}}><img src={item.image_url} alt="" className={sparkTw(block, "image_2", "h-full w-full object-cover")}/></div>:null}{item.label||item.value?<div className={sparkTw(block, "wrapper_40", "mb-2 flex items-center justify-between gap-3 text-[11px] font-bold uppercase tracking-wider")} style={{color:s.body}}><span>{item.label}</span><span>{item.value}</span></div>:null}{item.title?<h3 className={sparkTw(block, "subheading", "font-bold")} style={{color:s.heading,fontSize:num(v.card_title_size,20)}}>{item.title}</h3>:null}{item.text?<p className={sparkTw(block, "body_5", "mt-2")} style={{color:s.body,fontSize:s.bodySize,lineHeight:s.bodyLineHeight}}>{item.text}</p>:null}</div></article>)}</div>:null;
 return <section data-custom-spark-key={block.custom_spark_key||undefined} className={sparkTw(block, "section", `cosmic-custom-section ${s.aspectRatio>0?'cosmic-custom-ratio-section':''} relative isolate overflow-hidden`)} style={{minHeight:s.aspectRatio>0?0:s.minHeight,aspectRatio:s.aspectRatio>0?`${s.aspectRatio} / 1`:undefined,background:s.bg,padding:`${s.padY}px ${s.padX}px`,'--cc-h-tablet':`${responsive.headingTablet}px`,'--cc-h-mobile':`${responsive.headingMobile}px`,'--cc-b-tablet':`${responsive.bodyTablet}px`,'--cc-b-mobile':`${responsive.bodyMobile}px`,'--cc-p-tablet':`${responsive.padTablet}px`,'--cc-p-mobile':`${responsive.padMobile}px`,'--cc-g-tablet':`${responsive.gapTablet}px`,'--cc-g-mobile':`${responsive.gapMobile}px`}}><div className={sparkTw(block, "wrapper_41", "absolute bottom-4 right-4 z-40 flex items-center gap-2")}>{onSaveCustomSpark?<span className={sparkTw(block, "label_5", "rounded-full border border-amber-300/30 bg-amber-50/90 px-2.5 py-1.5 text-[10px] font-bold text-amber-800 shadow-sm")}>Draft</span>:null}{onSaveCustomSpark?<button type="button" onClick={onSaveCustomSpark} disabled={savingCustomSpark} className={sparkTw(block, "button_4", "rounded-full bg-emerald-600 px-3 py-2 text-[11px] font-bold text-white shadow-xl hover:bg-emerald-500 disabled:opacity-50")}>{savingCustomSpark?'Saving…':'Save This Spark · 50 credits'}</button>:null}{onOpenCosmicAI?<button type="button" onClick={onOpenCosmicAI} className={sparkTw(block, "button_5", "rounded-full border border-emerald-300/30 bg-slate-950/90 px-3 py-2 text-[11px] font-bold text-emerald-200 shadow-xl backdrop-blur hover:bg-slate-900")}>✨ Cosmic AI</button>:null}</div>{background?<div className={sparkTw(block, "wrapper_42", "absolute inset-0")}><EditableImage websiteId={null} blockIndex={blockIndex} src={block.image_url} showOverlay={false} isBackground blockType={block.type} className={sparkTw(block, "image_3", "h-full w-full object-cover")} style={{objectPosition:s.backgroundPosition,objectFit:s.backgroundSize==='contain'?'contain':'cover'}} onSave={image_url=>onUpdate({image_url})}/><div className={sparkTw(block, "wrapper_43", "absolute inset-0")} style={v.overlay_gradient_enabled?{background:`linear-gradient(${num(v.overlay_gradient_angle,90)}deg, ${color(v.overlay_gradient_from,color(v.overlay_color,'#ffffff'))} ${num(v.overlay_gradient_from_stop,0)}%, ${color(v.overlay_gradient_to,'#ffffff00')} ${num(v.overlay_gradient_to_stop,72)}%)`,opacity:Math.max(0,Math.min(1,Number(v.overlay_opacity)||0))}:{background:color(v.overlay_color,'#ffffff'),opacity:Math.max(0,Math.min(1,Number(v.overlay_opacity)||0))}}/></div>:null}{mainBody}{itemGrid}<style>{`@media(max-width:900px){.cosmic-custom-section .relative.z-10.mx-auto.mt-10.grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}.cosmic-custom-ratio-section{aspect-ratio:auto!important;min-height:auto!important}.cosmic-custom-section{padding-top:var(--cc-p-tablet)!important;padding-bottom:var(--cc-p-tablet)!important;padding-left:clamp(24px,5vw,56px)!important;padding-right:clamp(24px,5vw,56px)!important}.cosmic-custom-section .cosmic-custom-copy{max-width:min(78%,720px)!important;gap:var(--cc-g-tablet)!important}.cosmic-custom-section .cosmic-custom-heading{font-size:var(--cc-h-tablet)!important}.cosmic-custom-section .cosmic-custom-body{font-size:var(--cc-b-tablet)!important}}@media(max-width:640px){.cosmic-custom-section .relative.z-10.mx-auto.mt-10.grid{grid-template-columns:1fr!important}.cosmic-custom-section{min-height:auto!important;padding:var(--cc-p-mobile) 22px!important}.cosmic-custom-section .cosmic-custom-copy{max-width:100%!important;gap:var(--cc-g-mobile)!important}.cosmic-custom-section .cosmic-custom-heading{font-size:var(--cc-h-mobile)!important}.cosmic-custom-section .cosmic-custom-body{font-size:var(--cc-b-mobile)!important}.cosmic-custom-section form{width:100%}}`}</style></section>;
}
