import { useEffect, useRef, useState } from "react";
import { usePage } from "@inertiajs/react";
import { EditableText } from "../Shared/EditableText";
import { EditableButton } from "../Shared/EditableButton";
import { EditableImageGallery } from "../Shared/EditableImageGallery";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { getSectionBackgroundClass } from "../../../../theme/Theme";
import { getHeroThemeState } from "../../../../theme/heroTheme";

import { sparkTw, sparkTwPath } from "../Shared/sparkTailwindRuntime";
const base={eyebrow:"DESIGNED TO MOVE",heading:"Move the story forward.",text:"Lightweight animation adds depth while keeping the message and conversion path clear.",primary_label:"Get started",primary_url:"/start",secondary_label:"Explore more",secondary_url:"/features",image_url:"/storage/cms-images/background/background-1.avif",image_url_2:"/storage/cms-images/background/background-2.avif",image_url_3:"/storage/cms-images/background/background-3.avif"};
const schema=(type,title,purpose,aliases,extraDefaults={})=>({type,title,category:"Hero",purpose,description:`${title} is a Pro animated hero with reduced-motion safeguards.`,access:"pro",isPremium:true,badge:"PRO",credits:175,tags:["hero","premium","animated",...aliases],aliases:[title.toLowerCase(),...aliases],defaults:{...base,...extraDefaults},fields:[{type:"text",name:"eyebrow",label:"Eyebrow"},{type:"textarea",name:"heading",label:"Heading"},{type:"textarea",name:"text",label:"Description"},{type:"button",name:"primary",label:"Primary Button"},{type:"button",name:"secondary",label:"Secondary Button"},{type:"image",name:"image_url",label:"Primary Image"},{type:"image",name:"image_url_2",label:"Secondary Image"},{type:"image",name:"image_url_3",label:"Third Image"}]});
export const HeroGridPulseTechPremiumSchema=schema("hero_grid_pulse_tech_premium","Grid Pulse Tech Hero","Animated technical grid with a restrained moving pulse.",["grid pulse","tech grid","ai hero","cybersecurity","developer"]);
export const HeroLightTrailsPremiumSchema=schema("hero_light_trails_premium","Beam / Light Trails Hero","Slow luminous trails for premium technology and launch pages.",["light trails","beam hero","glow lines","technology","launch"]);
export const HeroDeviceShowcasePremiumSchema=schema("hero_device_showcase_premium","Device Showcase Hero","Floating laptop and phone frames for product-led websites.",["device showcase","phone laptop","app hero","saas product","device mockup"]);
export const HeroAppScreensCarouselPremiumSchema=schema("hero_app_screens_carousel_premium","App Screens Carousel Hero","Cycles layered app screens with lightweight motion.",["app screens","screens carousel","mobile app","product carousel","ui showcase"],{heading:"Show your best screens."});
export const HeroEditorialImageSequencePremiumSchema=schema("hero_editorial_image_sequence_premium","Editorial Image Sequence Hero","Editorial image sequence for fashion, hospitality and creative brands.",["editorial images","image sequence","fashion hero","hotel hero","creative"]);
export const HeroInteractiveBentoPremiumSchema=schema("hero_interactive_bento_premium","Interactive Bento Hero","Responsive bento tiles subtly expand on interaction.",["interactive bento","bento hero","hover tiles","agency hero","product grid"]);

function heroVisualState(block, globalTheme){
  const state=getHeroThemeState(block,globalTheme);
  const primaryKey=typeof globalTheme==="string"?globalTheme:(globalTheme?.primary||"midnight");
  const primaryTheme=colorFamilies[primaryKey]||colorFamilies.midnight;
  const gradient=primaryTheme.gradient||colorFamilies.midnight.gradient;
  const sectionClass=state.isLight?`${state.theme.bg} ${state.theme.text}`:(state.isPrimary?`${getSectionBackgroundClass(primaryKey, "deep")} ${primaryTheme.text}`:"bg-slate-950 text-white");
  return {...state,primaryTheme,gradient,sectionClass};
}

function Copy({d,onUpdate,visual,block}){
  const primary=visual.isLight?`${visual.primaryTheme.bg} ${visual.primaryTheme.text}`:"bg-white text-slate-950 hover:bg-white/90";
  const secondary=visual.isLight?`border-slate-300 bg-white/75 ${visual.theme.text}`:"border-white/25 bg-white/5 text-white";
  return <div className={sparkTw(block, "content", "relative z-20 max-w-2xl")}>
    <EditableText value={d.eyebrow} className={sparkTw(block, "eyebrow", `text-xs font-bold uppercase tracking-[.3em] ${visual.isLight?visual.theme.sub:"text-white/65"}`)} onSave={v=>onUpdate({eyebrow:v})}/>
    <EditableText value={d.heading} className={sparkTw(block, "heading", `mt-5 block text-4xl font-semibold leading-[.98] tracking-[-.045em] sm:text-5xl lg:text-6xl xl:text-7xl ${visual.isLight?visual.theme.text:"text-white"}`)} onSave={v=>onUpdate({heading:v})}/>
    <EditableText value={d.text} className={sparkTw(block, "description", `mt-6 block max-w-xl text-base leading-8 ${visual.isLight?visual.theme.sub:"text-white/70"}`)} onSave={v=>onUpdate({text:v})}/>
    <div className={sparkTw(block, "actions", "mt-8 flex flex-wrap gap-3")}>
      <EditableButton label={d.primary_label} url={d.primary_url} onSave={(label,url)=>onUpdate({primary_label:label,primary_url:url})} className={sparkTw(block, "primary_button", `rounded-full px-6 py-3 text-sm font-bold ${primary}`)}/>
      <EditableButton label={d.secondary_label} url={d.secondary_url} onSave={(label,url)=>onUpdate({secondary_label:label,secondary_url:url})} className={sparkTw(block, "secondary_button", `rounded-full border px-6 py-3 text-sm font-bold ${secondary}`)}/>
    </div>
  </div>;
}

function Shell({children,visual,className="",style,block}){
  // Patch 1 QA: this premium family was inheriting a full viewport fold, which
  // made image-led heroes (especially Editorial Image Sequence) feel excessively tall.
  // Keep the cinematic scale, but cap the desktop fold while still allowing content to grow.
  const compactHeroStyle = {
    "--cosmic-hero-fold-height": "clamp(620px, 78svh, 760px)",
    ...style,
  };
  return <section className={sparkTw(block, "section", `relative isolate min-h-[var(--cosmic-hero-fold-height)] overflow-hidden ${visual.sectionClass} ${className}`)} data-cosmic-hero-theme={visual.requestedTheme} style={compactHeroStyle}>
    <style>{`@media(prefers-reduced-motion:reduce){.cosmic-p5-motion{animation:none!important;transform:none!important;transition:none!important}}`}</style>
    <div className={sparkTw(block, "wrapper", "mx-auto grid min-h-[var(--cosmic-hero-fold-height)] max-w-7xl items-center gap-12 px-6 py-0 lg:grid-cols-[.9fr_1.1fr] lg:px-14")}>{children}</div>
  </section>;
}

export function HeroGridPulseTechPremiumBlock({block,onUpdate,globalTheme}){
  const d={...HeroGridPulseTechPremiumSchema.defaults,...block};const visual=heroVisualState(block,globalTheme);const g=visual.gradient;
  const gridColor=visual.isLight?"rgba(100,116,139,.16)":"rgba(255,255,255,.10)";
  return <Shell block={block} visual={visual}><Copy block={block} d={d} onUpdate={onUpdate} visual={visual}/><div className={sparkTw(block, "effect_stage", `relative h-[430px] overflow-hidden rounded-[32px] border ${visual.isLight?"border-slate-200 bg-white/70":"border-white/10 bg-black/10"}`)} style={{backgroundImage:`radial-gradient(circle at 70% 25%, ${g.glowSoft}, transparent 38%)`}}><div className={sparkTw(block, "grid_overlay", "absolute inset-0 opacity-60")} style={{backgroundImage:`linear-gradient(${gridColor} 1px,transparent 1px),linear-gradient(90deg,${gridColor} 1px,transparent 1px)`,backgroundSize:"42px 42px"}}/><div className={sparkTw(block, "scan_line", "cosmic-p5-motion absolute left-0 top-1/2 h-px w-1/2")} style={{background:`linear-gradient(90deg,transparent,${g.glow},transparent)`,animation:"p5scan 3.5s ease-in-out infinite"}}/><style>{`@keyframes p5scan{50%{transform:translateX(100%) translateY(-140px)}}`}</style></div></Shell>;
}

export function HeroLightTrailsPremiumBlock({block,onUpdate,globalTheme}){
  const d={...HeroLightTrailsPremiumSchema.defaults,...block};const visual=heroVisualState(block,globalTheme);const g=visual.gradient;
  const background=visual.isLight?"rgba(255,255,255,.62)":`linear-gradient(${g.angle||120}deg,${g.from},${g.via},${g.to})`;
  return <Shell block={block} visual={visual}><Copy block={block} d={d} onUpdate={onUpdate} visual={visual}/><div className={sparkTw(block, "effect_stage", `relative h-[430px] overflow-hidden rounded-[32px] border ${visual.isLight?"border-slate-200":"border-white/10"}`)} style={{background}}>{[0,1,2,3].map(i=><i key={i} className={`${sparkTwPath(block, ["trails", i], "beam", "absolute left-[-20%] h-px w-[140%]")} cosmic-p5-motion`.trim()} style={{top:`${20+i*20}%`,background:`linear-gradient(90deg,transparent,${visual.isLight?g.glow:g.glow},transparent)`,opacity:visual.isLight?.55:.85,transform:`rotate(${-12+i*7}deg)`,animation:`p5beam ${4+i*.7}s ease-in-out ${i*.3}s infinite`}}/>)}<style>{`@keyframes p5beam{50%{transform:translateX(12%) rotate(-4deg);opacity:.35}}`}</style></div></Shell>;
}

export function HeroDeviceShowcasePremiumBlock({block,blockIndex,onUpdate,globalTheme}){
  const d={...HeroDeviceShowcasePremiumSchema.defaults,...block};const visual=heroVisualState(block,globalTheme);const frame=visual.isLight?"border-slate-300 bg-slate-100":"border-slate-800 bg-slate-900";const galleryRef=useRef(null);const {props}=usePage();const websiteId=props.page?.website_id||props.website?.id;const imgs=[d.image_url,d.image_url_2].filter(Boolean);const saveImages=(next)=>onUpdate({image_url:next[0]||'',image_url_2:next[1]||next[0]||''});
  return <Shell block={block} visual={visual}><Copy block={block} d={d} onUpdate={onUpdate} visual={visual}/><div data-cosmic-editable-hero-media="true" title="Click to edit hero images" onClick={()=>galleryRef.current?.openEditor()} className={sparkTw(block, "media_stage", "relative h-[480px] cursor-pointer")}><div className={`${sparkTwPath(block, ["images", 0], "frame", `absolute inset-x-[5%] top-[12%] overflow-hidden rounded-[26px] border-[8px] shadow-2xl ${frame}`)} cosmic-p5-motion`.trim()} style={{animation:"p5float 5s ease-in-out infinite"}}><img src={d.image_url} alt="" decoding="async" className={sparkTwPath(block, ["images", 0], "image", "aspect-[16/10] w-full object-cover")}/></div><div className={`${sparkTwPath(block, ["images", 1], "frame", `absolute bottom-[5%] right-[4%] w-[28%] overflow-hidden rounded-[28px] border-[7px] shadow-2xl ${frame}`)} cosmic-p5-motion`.trim()} style={{animation:"p5float 4.4s ease-in-out .7s infinite"}}><img src={d.image_url_2} alt="" loading="lazy" decoding="async" className={sparkTwPath(block, ["images", 1], "image", "aspect-[9/16] w-full object-cover")}/></div><style>{`@keyframes p5float{50%{transform:translateY(-12px)}}`}</style></div><EditableImageGallery ref={galleryRef} websiteId={websiteId} images={imgs} minItems={2} maxItems={2} title="Device Showcase images" onSave={saveImages}/></Shell>;
}

export function HeroAppScreensCarouselPremiumBlock({block,blockIndex,onUpdate,globalTheme}){
  const d={...HeroAppScreensCarouselPremiumSchema.defaults,...block};const visual=heroVisualState(block,globalTheme);const [a,setA]=useState(0);const root=useRef(null);const galleryRef=useRef(null);const {props}=usePage();const websiteId=props.page?.website_id||props.website?.id;
  useEffect(()=>{if(matchMedia('(prefers-reduced-motion: reduce)').matches)return;const el=root.current;if(!el)return;let id=0;const stop=()=>{if(id){clearInterval(id);id=0;}};const start=()=>{if(!id)id=setInterval(()=>setA(v=>(v+1)%3),3000);};const io=new IntersectionObserver(([entry])=>entry?.isIntersecting?start():stop(),{rootMargin:'120px'});io.observe(el);return()=>{stop();io.disconnect();};},[]);
  const imgs=[d.image_url,d.image_url_2,d.image_url_3].filter(Boolean);const saveImages=(next)=>onUpdate({image_url:next[0]||'',image_url_2:next[1]||'',image_url_3:next[2]||''});return <div ref={root}><Shell block={block} visual={visual}><Copy block={block} d={d} onUpdate={onUpdate} visual={visual}/><div data-cosmic-editable-hero-media="true" title="Click to edit hero images" onClick={()=>galleryRef.current?.openEditor()} className={sparkTw(block, "media_stage", "relative h-[470px] cursor-pointer")}>{imgs.map((src,i)=>{let x=(i-a+imgs.length)%imgs.length;if(x===imgs.length-1)x=-1;return <img key={i} src={src} alt="" loading={i?'lazy':'eager'} decoding="async" className={sparkTwPath(block, ["images", i], "image", `absolute left-1/2 top-1/2 aspect-[9/16] w-[42%] rounded-[28px] border object-cover shadow-2xl transition-all duration-700 ${visual.isLight?"border-slate-200":"border-white/15"}`)} style={{transform:`translate(-50%,-50%) translateX(${x*58}%) scale(${x===0?1:.84})`,opacity:x===0?1:.5,zIndex:x===0?3:1}}/>})}</div><EditableImageGallery ref={galleryRef} websiteId={websiteId} images={imgs} maxItems={3} title="App Screens images" onSave={saveImages}/></Shell></div>;
}

export function HeroEditorialImageSequencePremiumBlock({block,blockIndex,onUpdate,globalTheme}){
  const d={...HeroEditorialImageSequencePremiumSchema.defaults,...block};const visual=heroVisualState(block,globalTheme);const imgs=[d.image_url,d.image_url_2,d.image_url_3].filter(Boolean);const galleryRef=useRef(null);const {props}=usePage();const websiteId=props.page?.website_id||props.website?.id;const saveImages=(next)=>onUpdate({image_url:next[0]||'',image_url_2:next[1]||'',image_url_3:next[2]||''});
  return <Shell block={block} visual={visual}><Copy block={block} d={d} onUpdate={onUpdate} visual={visual}/><div data-cosmic-editable-hero-media="true" title="Click to edit hero images" onClick={()=>galleryRef.current?.openEditor()} className={sparkTw(block, "media_stage", "grid cursor-pointer grid-cols-2 gap-4")}>{imgs.map((src,i)=><img key={i} src={src} alt="" loading={i?'lazy':'eager'} decoding="async" className={`${sparkTwPath(block, ["images", i], "image", `w-full rounded-[28px] object-cover shadow-xl ${i===0?'col-span-2 aspect-[16/7]':'aspect-square'}`)} cosmic-p5-motion`.trim()} style={{animation:`p5editorial ${5+i}s ease-in-out ${i*.5}s infinite`}}/>)}<style>{`@keyframes p5editorial{50%{transform:translateY(-8px) scale(1.015)}}`}</style></div><EditableImageGallery ref={galleryRef} websiteId={websiteId} images={imgs} maxItems={3} title="Editorial sequence images" onSave={saveImages}/></Shell>;
}

export function HeroInteractiveBentoPremiumBlock({block,blockIndex,onUpdate,globalTheme}){
  const d={...HeroInteractiveBentoPremiumSchema.defaults,...block};const visual=heroVisualState(block,globalTheme);const imgs=[d.image_url,d.image_url_2,d.image_url_3].filter(Boolean);const galleryRef=useRef(null);const {props}=usePage();const websiteId=props.page?.website_id||props.website?.id;const saveImages=(next)=>onUpdate({image_url:next[0]||'',image_url_2:next[1]||'',image_url_3:next[2]||''});
  return <Shell block={block} visual={visual}><Copy block={block} d={d} onUpdate={onUpdate} visual={visual}/><div data-cosmic-editable-hero-media="true" title="Click to edit hero images" onClick={()=>galleryRef.current?.openEditor()} className={sparkTw(block, "media_stage", "grid h-[470px] cursor-pointer grid-cols-2 grid-rows-2 gap-3")}>{imgs.map((src,i)=><div key={i} className={sparkTwPath(block, ["images", i], "card", `group overflow-hidden rounded-[28px] border transition-transform duration-300 hover:z-10 hover:scale-[1.025] ${visual.isLight?"border-slate-200 bg-white":"border-white/10 bg-white/5"} ${i===0?'row-span-2':''}`)}><img src={src} alt="" loading={i?'lazy':'eager'} decoding="async" className={sparkTwPath(block, ["images", i], "image", "h-full w-full object-cover transition-transform duration-700 group-hover:scale-105")}/></div>)}</div><EditableImageGallery ref={galleryRef} websiteId={websiteId} images={imgs} maxItems={3} title="Interactive Bento images" onSave={saveImages}/></Shell>;
}
