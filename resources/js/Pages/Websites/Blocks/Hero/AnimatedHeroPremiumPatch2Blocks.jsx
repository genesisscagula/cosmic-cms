import { useEffect, useRef, useState } from "react";
import { usePage } from "@inertiajs/react";
import { EditableText } from "../Shared/EditableText";
import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableImageGallery } from "../Shared/EditableImageGallery";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { getHeroThemeState, resolveHeroThemeRequest } from "../../../../theme/heroTheme";
import { resolveMediaOverlay, effectiveMediaOverlayOpacity } from "../../../../theme/mediaOverlay";

import { sparkTw, sparkTwPath } from "../Shared/sparkTailwindRuntime";
const defaults = {
    eyebrow: "MOTION, WITH PURPOSE",
    heading: "Turn the first screen into an experience.",
    text: "Premium motion adds depth and momentum while keeping the message clear, accessible, and fast.",
    primary_label: "Start a project",
    primary_url: "/contact",
    secondary_label: "Explore more",
    secondary_url: "/about",
    image_url: "/storage/cms-images/background/background-1.avif",
    image_url_2: "/storage/cms-images/background/background-2.avif",
    image_url_3: "/storage/cms-images/background/background-3.avif",
    video_url: "/storage/cms-videos/hero-placeholder.mp4",
    poster_image_url: "/storage/cms-images/background/background-1.avif",
    overlayOpacity: 56,
    motionStrength: 28,
};

function schema(type, title, purpose, tags, extraDefaults = {}, extraFields = []) {
    return {
        type, title, category: "Hero", purpose,
        description: `${title} is a Pro animated hero designed for premium motion with performance safeguards.`,
        access: "pro", isPremium: true, badge: "PRO", credits: 165,
        tags: ["hero", "premium", "animated", "motion", ...tags],
        aliases: [title.toLowerCase(), ...tags],
        defaults: { ...defaults, ...extraDefaults },
        fields: [
            { type: "text", name: "eyebrow", label: "Eyebrow" },
            { type: "textarea", name: "heading", label: "Heading" },
            { type: "textarea", name: "text", label: "Description" },
            { type: "button", name: "primary", label: "Primary Button" },
            { type: "button", name: "secondary", label: "Secondary Button" },
            { type: "image", name: "image_url", label: "Primary Image" },
            { type: "image", name: "image_url_2", label: "Secondary Image" },
            { type: "image", name: "image_url_3", label: "Third Image" },
            { type: "range", name: "overlayOpacity", label: "Overlay Opacity", min: 20, max: 85, step: 5 },
            ...extraFields,
        ],
    };
}

export const HeroRevealParallaxPremiumSchema = schema("hero_reveal_parallax_premium", "Reveal Parallax Hero", "Reveals and lifts a cinematic image as the section enters the viewport.", ["reveal parallax", "scroll reveal", "image reveal", "cinematic", "luxury"], { motionStrength: 28 }, [{ type:"range", name:"motionStrength", label:"Motion Strength", min:12, max:48, step:2 }]);
export const HeroZoomScrollPremiumSchema = schema("hero_zoom_scroll_premium", "Zoom Scroll Hero", "Adds a restrained scroll-driven zoom to full-bleed imagery.", ["zoom scroll", "scroll zoom", "immersive hero", "cinematic zoom", "editorial"], { motionStrength: 24 }, [{ type:"range", name:"motionStrength", label:"Zoom Strength", min:10, max:40, step:2 }]);
export const HeroPinnedStoryPremiumSchema = schema("hero_pinned_story_premium", "Pinned Story Hero", "Pins the opening stage while visual chapters progress with scroll.", ["pinned story", "sticky hero", "scroll story", "scrollytelling", "apple style"], { motionStrength: 24, heading: "Tell the story as you scroll." });
export const HeroVideoCinematicPremiumSchema = schema("hero_video_cinematic_premium", "Video Cinematic Hero", "Uses a muted looping full-screen video behind conversion-focused copy.", ["video hero", "cinematic video", "background video", "brand film", "fullscreen video"], {}, [{ type:"text", name:"video_url", label:"Video URL" }, { type:"image", name:"poster_image_url", label:"Video Poster" }]);
export const HeroVideoSplitPremiumSchema = schema("hero_video_split_premium", "Video Split Hero", "Pairs anchored copy with a cinematic autoplay video panel.", ["split video", "video split", "product video", "showreel", "agency video"], { heading: "Put the story beside the motion." }, [{ type:"text", name:"video_url", label:"Video URL" }, { type:"image", name:"poster_image_url", label:"Video Poster" }]);
export const HeroAuroraMotionPremiumSchema = schema("hero_aurora_motion_premium", "Aurora Motion Hero", "Creates a slow luminous aurora background without heavy media.", ["aurora", "gradient motion", "glow hero", "ai hero", "saas gradient"], { overlayOpacity: 32 });
export const HeroMeshGradientMotionPremiumSchema = schema("hero_mesh_gradient_motion_premium", "Mesh Gradient Motion Hero", "Uses layered animated mesh gradients for a modern product launch aesthetic.", ["mesh gradient", "animated gradient", "modern hero", "startup", "product launch"], { overlayOpacity: 24 });

const schemas = {
    hero_reveal_parallax_premium: HeroRevealParallaxPremiumSchema,
    hero_zoom_scroll_premium: HeroZoomScrollPremiumSchema,
    hero_pinned_story_premium: HeroPinnedStoryPremiumSchema,
    hero_video_cinematic_premium: HeroVideoCinematicPremiumSchema,
    hero_video_split_premium: HeroVideoSplitPremiumSchema,
    hero_aurora_motion_premium: HeroAuroraMotionPremiumSchema,
    hero_mesh_gradient_motion_premium: HeroMeshGradientMotionPremiumSchema,
};

function heroVisualState(block, globalTheme, configuredOpacity = 56) {
    const heroState = getHeroThemeState(block, globalTheme);
    const primaryKey = typeof globalTheme === "string" ? globalTheme : (globalTheme?.primary || "midnight");
    const primaryTheme = colorFamilies[primaryKey] || colorFamilies.midnight;
    const mediaOverlay = resolveMediaOverlay(globalTheme, resolveHeroThemeRequest(block, globalTheme));
    return {
        ...heroState,
        primaryTheme,
        mediaOverlay,
        overlay: effectiveMediaOverlayOpacity(configuredOpacity, { isLight: heroState.isLight, lightMinimum: 90 }) / 100,
    };
}

function Copy({ data, onUpdate, visual, block }) {
    const { theme, isPrimary, isLight, primaryTheme } = visual;
    const primaryButton = isPrimary ? "bg-white text-slate-950 hover:bg-white/90" : `${primaryTheme.bg} ${primaryTheme.text}`;
    const secondaryButton = isLight ? `border-slate-300 bg-white/70 ${theme.text} hover:bg-white` : "border-white/30 bg-white/10 text-white hover:bg-white/20";
    return <div className={sparkTw(block, "content", "max-w-3xl")}>
        <EditableText value={data.eyebrow} className={sparkTw(block, "eyebrow", `text-xs font-bold uppercase tracking-[0.32em] ${isLight ? theme.sub : "text-white/70"}`)} onSave={(value)=>onUpdate({eyebrow:value})}/>
        <EditableText value={data.heading} cosmicType="h1" className={sparkTw(block, "heading", `mt-6 block text-4xl font-semibold leading-[0.98] tracking-[-0.045em] sm:text-5xl lg:text-6xl xl:text-7xl ${isLight ? theme.text : "text-white"}`)} onSave={(value)=>onUpdate({heading:value})}/>
        <EditableText value={data.text} className={sparkTw(block, "description", `mt-7 block max-w-2xl text-base leading-8 sm:text-lg ${isLight ? theme.sub : "text-white/75"}`)} onSave={(value)=>onUpdate({text:value})}/>
        <div className={sparkTw(block, "actions", "mt-9 flex flex-wrap gap-3")}>
            <EditableButton label={data.primary_label} url={data.primary_url} onSave={(label,url)=>onUpdate({primary_label:label,primary_url:url})} className={sparkTw(block, "primary_button", `rounded-full px-6 py-3.5 text-sm font-bold transition ${primaryButton}`)}/>
            <EditableButton label={data.secondary_label} url={data.secondary_url} onSave={(label,url)=>onUpdate({secondary_label:label,secondary_url:url})} className={sparkTw(block, "secondary_button", `rounded-full border px-6 py-3.5 text-sm font-bold backdrop-blur transition ${secondaryButton}`)}/>
        </div>
    </div>;
}

function MediaWash({ visual, darkStops = [75, 20, 0], block }) {
    const color = visual.mediaOverlay.overlayColor || visual.primaryTheme?.palette?.background || "#243447";
    const gradient = visual.isLight
        ? "linear-gradient(90deg,rgba(255,255,255,.78),rgba(255,255,255,.32),rgba(255,255,255,.12))"
        : `linear-gradient(90deg,color-mix(in srgb,${color} ${darkStops[0]}%,transparent),color-mix(in srgb,${color} ${darkStops[1]}%,transparent),color-mix(in srgb,${color} ${darkStops[2]}%,transparent))`;
    return <><div className={sparkTw(block, "overlay_color", "absolute inset-0")} style={{backgroundColor:color,opacity:visual.overlay}}/><div className={sparkTw(block, "overlay", "absolute inset-0")} style={{background:gradient}}/></>;
}

function ScrollMediaHero({ block, blockIndex, onUpdate, globalTheme, mode }) {
    const schemaDef = schemas[block.type];
    const data = { ...schemaDef.defaults, ...block };
    const visual = heroVisualState(block, globalTheme, data.overlayOpacity);
    const { props } = usePage();
    const websiteId = props.page?.website_id || props.website?.id;
    const rootRef = useRef(null);
    const mediaRef = useRef(null);
    const imageRef = useRef(null);
    useEffect(()=>{
        const root=rootRef.current, media=mediaRef.current;
        if(!root||!media) return undefined;
        const reduced=window.matchMedia("(prefers-reduced-motion: reduce)");
        let raf=null;
        const update=()=>{
            if(reduced.matches) return;
            const r=root.getBoundingClientRect(), vh=window.innerHeight||1;
            if(r.bottom<0||r.top>vh) return;
            const p=Math.max(0,Math.min(1,(vh-r.top)/(vh+r.height)));
            const strength=Number(data.motionStrength||24);
            if(raf) cancelAnimationFrame(raf);
            raf=requestAnimationFrame(()=>{
                if(mode==="reveal") {
                    const inset=Math.max(0,(1-p)*10);
                    media.style.clipPath=`inset(${inset}% ${inset*.55}% ${inset}% ${inset*.55}% round ${Math.max(0,(1-p)*32)}px)`;
                    media.style.transform=`translate3d(0,${(0.5-p)*strength}px,0) scale(${1.06-p*.035})`;
                } else {
                    media.style.transform=`scale(${1.02+p*(strength/500)}) translate3d(0,${(p-.5)*strength*.35}px,0)`;
                }
            });
        };
        document.addEventListener("scroll", update, true); window.addEventListener("resize", update); update();
        return ()=>{document.removeEventListener("scroll", update, true);window.removeEventListener("resize", update);if(raf)cancelAnimationFrame(raf);};
    },[data.motionStrength,mode]);
    const handleImageEdit=(event)=>{if(event.target.closest("button, a, input, textarea, select, label, [contenteditable=\'true\'], [role=\'button\'], [data-cosmic-edit-control]"))return;imageRef.current?.openEditor();};
    const sectionBackground = visual.primaryTheme?.palette?.background || visual.mediaOverlay.overlayColor || "#243447";
    return <section ref={rootRef} onClick={handleImageEdit} data-cosmic-editable-hero-media="true" data-cosmic-media-banner="true" data-cosmic-hero-theme={visual.requestedTheme} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))",...(!visual.isLight?{backgroundColor:sectionBackground}:{})}} className={sparkTw(block, "section", `relative isolate overflow-hidden ${visual.isLight ? `${visual.theme.bg} ${visual.theme.text}` : "text-white"}`)}>
        <div ref={mediaRef} className={sparkTw(block, "media", "absolute inset-0 will-change-transform")} style={{clipPath:mode==="reveal"?"inset(8% 4% 8% 4% round 28px)":undefined}}>
            <EditableImage ref={imageRef} websiteId={websiteId} blockIndex={blockIndex} src={data.image_url} showOverlay={false} isBackground blockType={block.type} className={sparkTw(block, "image", "absolute inset-0 h-full w-full")} onSave={(value)=>onUpdate({image_url:value})}/>
        </div>
        <MediaWash block={block} visual={visual}/>
        <div className={sparkTw(block, "wrapper", "relative z-10 mx-auto flex max-w-7xl items-center px-6 py-0 sm:px-10 lg:px-14")} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}><Copy block={block} data={data} onUpdate={onUpdate} visual={visual}/></div>
    </section>;
}

function PinnedStoryHero({ block, blockIndex, onUpdate, globalTheme }) {
    const data={...HeroPinnedStoryPremiumSchema.defaults,...block}; const visual=heroVisualState(block,globalTheme,data.overlayOpacity); const {props}=usePage(); const websiteId=props.page?.website_id||props.website?.id;
    const rootRef=useRef(null); const galleryRef=useRef(null); const [chapter,setChapter]=useState(0);
    useEffect(()=>{const root=rootRef.current;if(!root)return undefined;const reduced=window.matchMedia("(prefers-reduced-motion: reduce)");let timer=null;const observer=new IntersectionObserver(([entry])=>{if(timer){clearInterval(timer);timer=null;}if(entry?.isIntersecting&&!reduced.matches)timer=setInterval(()=>setChapter(v=>(v+1)%3),4200);},{threshold:.2});observer.observe(root);return()=>{observer.disconnect();if(timer)clearInterval(timer);};},[]);
    const imgs=[data.image_url,data.image_url_2,data.image_url_3];
    const updateGallery=(next)=>onUpdate({image_url:next[0]||"",image_url_2:next[1]||"",image_url_3:next[2]||""});
    const handleGalleryEdit=(event)=>{if(event.target.closest("button, a, input, textarea, select, label, [contenteditable=\'true\'], [role=\'button\'], [data-cosmic-edit-control]"))return;galleryRef.current?.openEditor();};
    const sectionBackground=visual.primaryTheme?.palette?.background||visual.mediaOverlay.overlayColor||"#243447";
    return <section ref={rootRef} onClick={handleGalleryEdit} data-cosmic-editable-hero-media="true" data-cosmic-hero-theme={visual.requestedTheme} style={{minHeight:"var(--cosmic-hero-fold-height, calc(100svh - 80px))",...(!visual.isLight?{backgroundColor:sectionBackground}:{})}} className={sparkTw(block, "section", `relative ${visual.isLight ? `${visual.theme.bg} ${visual.theme.text}` : "text-white"}`)}><div className={sparkTw(block, "media", "relative isolate overflow-hidden")} style={{height:"var(--cosmic-hero-fold-height, calc(100svh - 80px))"}}>
        {imgs.map((src,i)=><div key={i} className={`${sparkTwPath(block, ["images", i], "layer", "absolute inset-0 transition-all duration-700")} ${i===chapter?"scale-100 opacity-100":"scale-[1.04] opacity-0"}`.trim()}><>{i===0?<EditableImage websiteId={websiteId} blockIndex={blockIndex} src={src} showOverlay={false} isBackground blockType={block.type} className={sparkTwPath(block, ["images", i], "image", "absolute inset-0 h-full w-full")} onSave={(value)=>onUpdate({image_url:value})}/>:<img src={src} alt="" className={sparkTwPath(block, ["images", i], "image", "h-full w-full object-cover")} loading="lazy" decoding="async"/>}</></div>)}
        <MediaWash block={block} visual={visual} darkStops={[80,30,0]}/>
        <div className={sparkTw(block, "wrapper", "relative z-10 mx-auto flex h-full max-w-7xl items-center px-6 sm:px-10 lg:px-14")}><Copy block={block} data={data} onUpdate={onUpdate} visual={visual}/></div>
        <div className={sparkTw(block, "progress", "absolute bottom-8 right-8 z-20 flex gap-2")}>{imgs.map((_,i)=><span key={i} className={`${sparkTwPath(block, ["images", i], "dot", "h-1 rounded-full transition-all")} ${i===chapter?(visual.isLight?"w-12 bg-slate-700":"w-12 bg-white"):(visual.isLight?"w-5 bg-slate-400/50":"w-5 bg-white/30")}`.trim()}/>)}</div>
        <EditableImageGallery ref={galleryRef} websiteId={websiteId} images={imgs} maxItems={3} title="Pinned Story images" onSave={updateGallery}/></div></section>;
}

function VideoHero({ block, blockIndex, onUpdate, globalTheme, split=false }) {
    const schemaDef=split?HeroVideoSplitPremiumSchema:HeroVideoCinematicPremiumSchema; const data={...schemaDef.defaults,...block}; const visual=heroVisualState(block,globalTheme,data.overlayOpacity); const {props}=usePage(); const websiteId=props.page?.website_id||props.website?.id;
    const videoRef=useRef(null); const posterRef=useRef(null);
    useEffect(()=>{const video=videoRef.current;if(!video||typeof IntersectionObserver==="undefined")return undefined;const reduced=window.matchMedia("(prefers-reduced-motion: reduce)");const observer=new IntersectionObserver(([entry])=>{if(reduced.matches||!entry.isIntersecting){video.pause();return;}const play=video.play();if(play?.catch)play.catch(()=>{});},{rootMargin:"160px 0px",threshold:.05});observer.observe(video);return()=>{observer.disconnect();video.pause();};},[data.video_url]);
    const openPoster=(event)=>{if(event.target.closest("button, a, input, textarea, select, label, [contenteditable=\'true\'], [role=\'button\'], [data-cosmic-edit-control]"))return;posterRef.current?.openEditor();};
    const sectionBackground=visual.primaryTheme?.palette?.background||visual.mediaOverlay.overlayColor||"#243447";
    const media=<div onClick={openPoster} style={{backgroundColor:sectionBackground}} className={sparkTw(block, "media", `${split?"relative min-h-[520px] lg:min-h-[640px]":"absolute inset-0"} cursor-pointer overflow-hidden`)}><video ref={videoRef} className={sparkTw(block, "video", "absolute inset-0 block h-full w-full object-cover")} src={data.video_url} poster={data.poster_image_url||data.image_url} autoPlay muted loop playsInline preload="metadata"/><MediaWash block={block} visual={visual} darkStops={[65,15,0]}/><EditableImage ref={posterRef} websiteId={websiteId} blockIndex={blockIndex} src={data.poster_image_url||data.image_url} showOverlay={false} blockType={block.type} className={sparkTw(block, "poster", "hidden")} onSave={(value)=>onUpdate({poster_image_url:value})}/></div>;
    const sectionClass=visual.isLight?`${visual.theme.bg} ${visual.theme.text}`:"text-white";
    const sectionStyle=visual.isLight?undefined:{backgroundColor:sectionBackground};
    if(split) return <section data-cosmic-hero-theme={visual.requestedTheme} style={sectionStyle} className={sparkTw(block, "section", `grid min-h-[640px] overflow-hidden lg:grid-cols-[0.9fr_1.1fr] ${sectionClass}`)}><div className={sparkTw(block, "wrapper", "relative z-10 flex items-center px-6 py-0 sm:px-10 lg:px-14")}><Copy block={block} data={data} onUpdate={onUpdate} visual={visual}/></div>{media}</section>;
    return <section data-cosmic-media-banner="true" data-cosmic-hero-theme={visual.requestedTheme} style={sectionStyle} className={sparkTw(block, "section", `relative isolate min-h-[var(--cosmic-hero-fold-height)] overflow-hidden ${sectionClass}`)}>{media}<div className={sparkTw(block, "wrapper", "relative z-10 mx-auto flex min-h-[var(--cosmic-hero-fold-height)] max-w-7xl items-center px-6 py-0 sm:px-10 lg:px-14")}><Copy block={block} data={data} onUpdate={onUpdate} visual={visual}/></div></section>;
}

function GradientHero({ block, onUpdate, globalTheme, mesh=false }) {
    const schemaDef=mesh?HeroMeshGradientMotionPremiumSchema:HeroAuroraMotionPremiumSchema; const data={...schemaDef.defaults,...block};
    const visual=heroVisualState(block,globalTheme,data.overlayOpacity);
    const primaryKey=typeof globalTheme === "string" ? globalTheme : (globalTheme?.primary || "midnight");
    const family=colorFamilies[primaryKey] || colorFamilies.midnight;
    const gradient=family?.gradient || colorFamilies.midnight?.gradient || {from:"#071426",via:"#111936",to:"#28164D",glow:"#7C3AED",glowSoft:"rgba(124,58,237,.20)",glowStrong:"rgba(124,58,237,.38)",angle:120};
    const vars={"--cg-from":gradient.from,"--cg-via":gradient.via,"--cg-to":gradient.to,"--cg-glow":gradient.glow,"--cg-glow-soft":gradient.glowSoft,"--cg-glow-strong":gradient.glowStrong,"--cg-angle":`${gradient.angle||120}deg`};
    const background=visual.isLight?"#ffffff":"linear-gradient(var(--cg-angle),var(--cg-from),var(--cg-via),var(--cg-to))";
    return <section className={sparkTw(block, "section", `relative isolate min-h-[var(--cosmic-hero-fold-height)] overflow-hidden ${visual.isLight?`${visual.theme.bg} ${visual.theme.text}`:"text-white"}`)} style={{...vars,background}} data-cosmic-gradient-theme={primaryKey} data-cosmic-hero-theme={visual.requestedTheme}>
        <style>{`@keyframes cosmicAuroraDrift{0%,100%{transform:translate3d(-8%,-5%,0) rotate(-8deg) scale(1)}50%{transform:translate3d(10%,8%,0) rotate(8deg) scale(1.12)}}@keyframes cosmicMeshDrift{0%,100%{transform:translate3d(-4%,-3%,0) scale(1)}33%{transform:translate3d(7%,-5%,0) scale(1.08)}66%{transform:translate3d(2%,8%,0) scale(1.13)}}@media(prefers-reduced-motion:reduce){.cosmic-aurora-motion,.cosmic-mesh-motion{animation:none!important}}`}</style>
        {!visual.isLight && (mesh?<><div className={sparkTw(block, "mesh", "cosmic-mesh-motion absolute -inset-[25%] opacity-90 blur-3xl")} style={{background:"radial-gradient(circle at 20% 25%,var(--cg-glow-strong),transparent 28%),radial-gradient(circle at 75% 20%,var(--cg-glow-soft),transparent 31%),radial-gradient(circle at 65% 78%,color-mix(in srgb,var(--cg-via) 75%,transparent),transparent 30%),radial-gradient(circle at 25% 80%,color-mix(in srgb,var(--cg-to) 72%,transparent),transparent 28%)",animation:"cosmicMeshDrift 16s ease-in-out infinite"}}/><div className={sparkTw(block, "grid_overlay", "absolute inset-0 bg-[linear-gradient(rgba(255,255,255,.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.035)_1px,transparent_1px)] bg-[size:56px_56px]")}/></>:<><div className={sparkTw(block, "aurora_primary", "cosmic-aurora-motion absolute -left-[15%] top-[-35%] h-[90%] w-[75%] rounded-[50%] blur-[90px]")} style={{background:"var(--cg-glow-strong)",animation:"cosmicAuroraDrift 14s ease-in-out infinite"}}/><div className={sparkTw(block, "aurora_secondary", "cosmic-aurora-motion absolute -right-[15%] bottom-[-30%] h-[85%] w-[70%] rounded-[50%] blur-[100px]")} style={{background:"color-mix(in srgb,var(--cg-via) 72%,transparent)",animation:"cosmicAuroraDrift 18s ease-in-out infinite reverse"}}/><div className={sparkTw(block, "aurora_glow", "absolute left-[40%] top-[25%] h-[45%] w-[45%] rounded-full blur-[100px]")} style={{background:"var(--cg-glow-soft)"}}/></>)}
        <div className={sparkTw(block, "overlay", "absolute inset-0")} style={{background:visual.isLight?"rgba(255,255,255,.92)":"linear-gradient(180deg,rgba(2,6,23,.06),rgba(2,6,23,.68))"}}/><div className={sparkTw(block, "wrapper", "relative z-10 mx-auto flex min-h-[var(--cosmic-hero-fold-height)] max-w-7xl items-center px-6 py-0 sm:px-10 lg:px-14")}><Copy block={block} data={data} onUpdate={onUpdate} visual={visual}/></div>
    </section>;
}

export const HeroRevealParallaxPremiumBlock=(props)=><ScrollMediaHero {...props} mode="reveal"/>;
export const HeroZoomScrollPremiumBlock=(props)=><ScrollMediaHero {...props} mode="zoom"/>;
export const HeroPinnedStoryPremiumBlock=PinnedStoryHero;
export const HeroVideoCinematicPremiumBlock=(props)=><VideoHero {...props} split={false}/>;
export const HeroVideoSplitPremiumBlock=(props)=><VideoHero {...props} split/>;
export const HeroAuroraMotionPremiumBlock=(props)=><GradientHero {...props} mesh={false}/>;
export const HeroMeshGradientMotionPremiumBlock=(props)=><GradientHero {...props} mesh/>;
