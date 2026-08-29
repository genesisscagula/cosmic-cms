import { EditableButton } from "../Shared/EditableButton";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";
import { sparkTw, sparkTwItem } from "../Shared/sparkTailwindRuntime";

const baseDefaults = {
    eyebrow: "SERVICES BUILT AROUND WHAT MATTERS",
    heading: "Focused expertise for every stage of the journey.",
    text: "A considered mix of specialist services, shaped around clear priorities and practical outcomes.",
    primary_label: "Explore our services",
    primary_url: "#",
    image_url: "/storage/cms-images/background/background-1.avif",
    featured_image_url: "",
    service_two_image_url: "",
    service_three_image_url: "",
    service_four_image_url: "",
    service_five_image_url: "",
    service_six_image_url: "",
    service_seven_image_url: "",
    featured_number: "01",
    featured_title: "Strategy & diagnostics",
    featured_text: "Start with a clear understanding of the challenge, priorities, and best next step.",
    featured_meta: "Assessment · Planning · Direction",
    service_two_number: "02", service_two_title: "Core service", service_two_text: "Deliver the essential work with a focused, dependable approach.",
    service_three_number: "03", service_three_title: "Specialist support", service_three_text: "Bring in focused expertise where the project needs added depth.",
    service_four_number: "04", service_four_title: "Enhancement", service_four_text: "Refine the experience with detail-led improvements that add lasting value.",
    service_five_number: "05", service_five_title: "Ongoing care", service_five_text: "Keep performance and quality moving forward with practical follow-through.",
    service_six_number: "06", service_six_title: "Extended service", service_six_text: "Add another relevant service when the offer needs more range.",
    service_seven_number: "07", service_seven_title: "Tailored support", service_seven_text: "Add a final option when it genuinely strengthens the customer journey.",
    service_count: 5,
    proof_value: "Focused expertise",
    proof_label: "One connected service experience",
};

function schema(type, title, purpose, tags=[]) {
    return {
        type, title, category: "Services", purpose,
        description: `${title} is a premium Services layout with up to seven editable service items.`,
        tags: ["services","premium",...tags],
        defaults: { ...baseDefaults, type },
    };
}

export const ServicesEditorialPremiumSchema = schema(
    "services_editorial_premium","Services Editorial Premium",
    "Present services through an asymmetric magazine-style editorial composition.",
    ["editorial","asymmetric","magazine"]
);
export const ServicesShowcasePremiumSchema = schema(
    "services_showcase_premium","Services Showcase Premium",
    "Lead with large service imagery and a refined supporting service grid.",
    ["showcase","image-led","visual"]
);
export const ServicesMinimalLuxurySchema = schema(
    "services_minimal_luxury","Services Minimal Luxury",
    "Use generous whitespace and restrained typography for a quiet luxury service presentation.",
    ["minimal","luxury","whitespace"]
);
export const ServicesContrastPremiumSchema = schema(
    "services_contrast_premium","Services Contrast Premium",
    "Present services with stronger contrast and emphasis while staying fully aligned to the active website theme.",
    ["contrast","bold","theme-aware"]
);
export const ServicesSplitPremiumSchema = schema(
    "services_split_premium","Services Split Premium",
    "Use alternating split service rows for a paced editorial service story.",
    ["split","alternating","story"]
);
export const ServicesGridPremiumSchema = schema(
    "services_grid_premium","Services Grid Premium",
    "Present services in a refined three-column premium grid.",
    ["grid","three-column","clean"]
);
export const ServicesFeaturePremiumSchema = schema(
    "services_feature_premium","Services Feature Premium",
    "Feature one primary service with supporting services arranged around it.",
    ["featured","hierarchy","spotlight"]
);

const imageSlots = [
    "featured_image_url",
    "service_two_image_url",
    "service_three_image_url",
    "service_four_image_url",
    "service_five_image_url",
    "service_six_image_url",
    "service_seven_image_url",
];

const slots = [
    ["featured_number","featured_title","featured_text"],
    ["service_two_number","service_two_title","service_two_text"],
    ["service_three_number","service_three_title","service_three_text"],
    ["service_four_number","service_four_title","service_four_text"],
    ["service_five_number","service_five_title","service_five_text"],
    ["service_six_number","service_six_title","service_six_text"],
    ["service_seven_number","service_seven_title","service_seven_text"],
];

function useData(schemaDef, block, globalTheme) {
    const data={...schemaDef.defaults,...block};
    const theme=getEffectiveTheme(block.theme && block.theme!=="auto" ? block.theme : block.resolvedTheme, globalTheme);
    const family=colorFamilies[globalTheme?.primary] || colorFamilies.emerald;
    const count=Math.max(1,Math.min(7,Number(data.service_count)||5));
    return {data,theme,family,count,items:slots.slice(0,count)};
}

function Intro({block,data,theme,onUpdate,center=false,inverse=false}) {
    const textClass=inverse?"text-white":"";
    const muted=inverse?"text-white/65":theme.sub;
    return <div className={sparkTw(block, "intro", center?"mx-auto max-w-4xl text-center":"")}>
        <EditableText value={data.eyebrow} className={sparkTw(block, "text", `text-xs font-bold uppercase tracking-[.28em] ${muted}`)} onSave={(eyebrow)=>onUpdate({eyebrow})}/>
        <EditableText value={data.heading} cosmicType="h2" className={sparkTw(block, "text_2", `mt-5 block text-4xl font-semibold leading-[1.02] tracking-[-.045em] sm:text-5xl lg:text-6xl ${textClass||theme.text}`)} onSave={(heading)=>onUpdate({heading})}/>
        <EditableText value={data.text} isTextArea className={sparkTw(block, "text_3", `mt-6 block max-w-3xl text-base leading-7 ${center?"mx-auto":""} ${muted}`)} onSave={(text)=>onUpdate({text})}/>
    </div>
}

function ServiceCopy({block,data,keys,onUpdate,itemIndex=null,numberClass="",titleClass="",textClass=""}) {
    const [nk,tk,dk]=keys;
    return <>
        <EditableText value={data[nk]} className={itemIndex===null?sparkTw(block, "text_4", `text-xs font-bold tracking-[.2em] ${numberClass}`):sparkTwItem(block, "services", itemIndex, "number", `text-xs font-bold tracking-[.2em] ${numberClass}`)} onSave={(v)=>onUpdate({[nk]:v})}/>
        <EditableText value={data[tk]} className={itemIndex===null?sparkTw(block, "text_5", `mt-5 block text-2xl font-semibold tracking-[-.025em] ${titleClass}`):sparkTwItem(block, "services", itemIndex, "title", `mt-5 block text-2xl font-semibold tracking-[-.025em] ${titleClass}`)} onSave={(v)=>onUpdate({[tk]:v})}/>
        <EditableText value={data[dk]} isTextArea className={itemIndex===null?sparkTw(block, "text_6", `mt-3 block text-sm leading-6 ${textClass}`):sparkTwItem(block, "services", itemIndex, "desc", `mt-3 block text-sm leading-6 ${textClass}`)} onSave={(v)=>onUpdate({[dk]:v})}/>
    </>;
}

export function ServicesEditorialPremiumBlock({block,onUpdate,globalTheme}) {
    const {data,theme,family,items}=useData(ServicesEditorialPremiumSchema,block,globalTheme);
    return <section data-cosmic-services-editorial-premium="true" className={sparkTw(block, "section", `px-6 py-20 sm:px-10 lg:px-14 lg:py-28 ${theme.bg}`)}>
        <div className={sparkTw(block, "wrapper", "mx-auto max-w-7xl")}>
            <div className={sparkTw(block, "wrapper_2", "grid gap-12 lg:grid-cols-[.8fr_1.2fr] lg:gap-20")}>
                <div className={sparkTw(block, "wrapper_3", "lg:sticky lg:top-28 lg:self-start")}><Intro block={block} data={data} theme={theme} onUpdate={onUpdate}/><EditableButton label={data.primary_label} url={data.primary_url} className={sparkTw(block, "button", `mt-8 inline-flex rounded-full px-7 py-4 font-bold ${family.bg} ${family.text}`)} onSave={(primary_label,primary_url)=>onUpdate({primary_label,primary_url})}/></div>
                <div className={sparkTw(block, "wrapper_4", "border-t")}>
                    {items.map((keys,i)=><article key={keys[0]} className={sparkTwItem(block, "services", i, "card", `grid gap-5 border-b py-8 sm:grid-cols-[90px_1fr] ${theme.border}`)}>
                        <div className={sparkTwItem(block, "services", i, "number", theme.sub)}>{data[keys[0]]}</div>
                        <div><EditableText value={data[keys[1]]} className={sparkTwItem(block, "services", i, "title", `block text-2xl font-semibold tracking-[-.03em] ${theme.text}`)} onSave={(v)=>onUpdate({[keys[1]]:v})}/><EditableText value={data[keys[2]]} isTextArea className={sparkTwItem(block, "services", i, "desc", `mt-3 block max-w-2xl leading-7 ${theme.sub}`)} onSave={(v)=>onUpdate({[keys[2]]:v})}/></div>
                    </article>)}
                </div>
            </div>
        </div>
    </section>;
}

export function ServicesShowcasePremiumBlock({block,onUpdate,globalTheme}) {
    const {data,theme,family,items}=useData(ServicesShowcasePremiumSchema,block,globalTheme);
    return <section data-cosmic-services-showcase-premium="true" className={sparkTw(block, "section_2", `px-6 py-20 sm:px-10 lg:px-14 lg:py-28 ${theme.bg}`)}>
        <div className={sparkTw(block, "wrapper_5", "mx-auto max-w-7xl")}><Intro block={block} data={data} theme={theme} onUpdate={onUpdate}/>
            <div className={sparkTw(block, "wrapper_6", "mt-12 overflow-hidden rounded-[2rem] border")}>
                <div className={sparkTw(block, "wrapper_7", "min-h-[420px] bg-cover bg-center")} style={{backgroundImage:`linear-gradient(90deg,rgba(2,6,23,.76),rgba(2,6,23,.12)),url(${data.image_url})`}}>
                    <div className={sparkTw(block, "wrapper_8", "flex min-h-[420px] max-w-xl flex-col justify-end p-8 text-white sm:p-12")}>
                        <ServiceCopy block={block} data={data} keys={items[0]} onUpdate={onUpdate} itemIndex={0} numberClass="text-white/65" textClass="text-white/75"/>
                    </div>
                </div>
            </div>
            <div className={sparkTw(block, "wrapper_9", "mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3")}>{items.slice(1).map((keys,i)=><article key={keys[0]} className={sparkTwItem(block, "services", i+1, "card", `rounded-3xl border p-7 ${theme.border} ${theme.surface}`)}><ServiceCopy block={block} data={data} keys={keys} onUpdate={onUpdate} itemIndex={i+1} numberClass={theme.sub} titleClass={theme.text} textClass={theme.sub}/></article>)}</div>
        </div>
    </section>;
}

export function ServicesMinimalLuxuryBlock({block,onUpdate,globalTheme}) {
    const {data,theme,items}=useData(ServicesMinimalLuxurySchema,block,globalTheme);
    return <section data-cosmic-services-minimal-luxury="true" className={sparkTw(block, "section_3", `px-6 py-24 sm:px-10 lg:px-14 lg:py-36 ${theme.bg}`)}>
        <div className={sparkTw(block, "wrapper_10", "mx-auto max-w-6xl")}><Intro block={block} data={data} theme={theme} onUpdate={onUpdate} center/>
            <div className={sparkTw(block, "wrapper_11", "mt-20 divide-y")}>{items.map((keys,i)=><article key={keys[0]} className={sparkTwItem(block, "services", i, "card", `grid gap-6 py-10 md:grid-cols-[120px_1fr_1fr] ${theme.border}`)}>
                <span className={sparkTwItem(block, "services", i, "number", `text-xs tracking-[.25em] ${theme.sub}`)}>{data[keys[0]]}</span>
                <EditableText value={data[keys[1]]} className={sparkTwItem(block, "services", i, "title", `block text-2xl font-medium ${theme.text}`)} onSave={(v)=>onUpdate({[keys[1]]:v})}/>
                <EditableText value={data[keys[2]]} isTextArea className={sparkTwItem(block, "services", i, "desc", `block leading-7 ${theme.sub}`)} onSave={(v)=>onUpdate({[keys[2]]:v})}/>
            </article>)}</div>
        </div>
    </section>;
}

export function ServicesContrastPremiumBlock({block,onUpdate,globalTheme}) {
    const {data,theme,family,items}=useData(ServicesContrastPremiumSchema,block,globalTheme);
    const familySurface=family.card || theme.surface;
    const familyText=family.text || theme.text;
    const familySub=family.sub || theme.sub;
    return <section data-cosmic-services-contrast-premium="true" className={sparkTw(block, "section_4", `px-6 py-20 sm:px-10 lg:px-14 lg:py-28 ${theme.bg}`)}>
        <div className={sparkTw(block, "wrapper_12", "mx-auto max-w-7xl")}><Intro block={block} data={data} theme={theme} onUpdate={onUpdate}/>
            <div className={sparkTw(block, "wrapper_13", `mt-14 grid gap-px overflow-hidden rounded-[2rem] border ${theme.border} ${theme.border}`)}>
                {items.map((keys,i)=><article key={keys[0]} className={sparkTwItem(block, "services", i, "card", `min-h-[260px] p-8 ${i===0?`lg:col-span-2 ${familySurface}`:theme.surface}`)}>
                    <ServiceCopy
                        block={block}
                        itemIndex={i}
                        data={data}
                        keys={keys}
                        onUpdate={onUpdate}
                        numberClass={i===0?familySub:theme.sub}
                        titleClass={i===0?familyText:theme.text}
                        textClass={i===0?familySub:theme.sub}
                    />
                </article>)}
            </div>
        </div>
    </section>;
}

export function ServicesSplitPremiumBlock({block,onUpdate,globalTheme}) {
    const {data,theme,items}=useData(ServicesSplitPremiumSchema,block,globalTheme);
    return <section data-cosmic-services-split-premium="true" className={sparkTw(block, "section_5", `px-6 py-20 sm:px-10 lg:px-14 lg:py-28 ${theme.bg}`)}>
        <div className={sparkTw(block, "wrapper_14", "mx-auto max-w-7xl")}><Intro block={block} data={data} theme={theme} onUpdate={onUpdate}/>
            <div className={sparkTw(block, "wrapper_15", "mt-14 space-y-5")}>{items.map((keys,i)=>{
                const imageKey=imageSlots[i];
                const imageUrl=data[imageKey] || data.image_url;
                return <article key={keys[0]} className={sparkTwItem(block, "services", i, "card", `grid overflow-hidden rounded-[2rem] border lg:grid-cols-2 ${theme.border} ${theme.surface}`)}>
                    <div className={sparkTwItem(block, "services", i, "content", `p-8 sm:p-10 lg:p-12 ${i%2?"lg:order-2":""}`)}>
                        <ServiceCopy block={block} data={data} keys={keys} onUpdate={onUpdate} itemIndex={i} numberClass={theme.sub} titleClass={theme.text} textClass={theme.sub}/>
                    </div>
                    <div className={sparkTwItem(block, "services", i, "media", `relative min-h-[260px] overflow-hidden lg:min-h-[340px] ${i%2?"lg:order-1":""}`)}>
                        {imageUrl ? <img src={imageUrl} alt="" className={sparkTwItem(block, "services", i, "image", "absolute inset-0 h-full w-full object-cover")}/> : <div className={sparkTwItem(block, "services", i, "media_fallback", `absolute inset-0 ${theme.surface}`)}/>}
                    </div>
                </article>;
            })}</div>
        </div>
    </section>;
}

export function ServicesGridPremiumBlock({block,onUpdate,globalTheme}) {
    const {data,theme,family,items}=useData(ServicesGridPremiumSchema,block,globalTheme);
    return <section data-cosmic-services-grid-premium="true" className={sparkTw(block, "section_6", `px-6 py-20 sm:px-10 lg:px-14 lg:py-28 ${theme.bg}`)}>
        <div className={sparkTw(block, "wrapper_19", "mx-auto max-w-7xl")}><Intro block={block} data={data} theme={theme} onUpdate={onUpdate} center/>
            <div className={sparkTw(block, "wrapper_20", "mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-3")}>{items.map((keys,i)=><article key={keys[0]} className={sparkTwItem(block, "services", i, "card", `group min-h-[270px] rounded-[1.75rem] border p-8 ${theme.border} ${theme.surface}`)}>
                <div className={sparkTwItem(block, "services", i, "number", `mb-12 inline-flex h-10 w-10 items-center justify-center rounded-full text-xs font-bold ${family.bg} ${family.text}`)}>{data[keys[0]]}</div>
                <EditableText value={data[keys[1]]} className={sparkTwItem(block, "services", i, "title", `block text-2xl font-semibold tracking-[-.03em] ${theme.text}`)} onSave={(v)=>onUpdate({[keys[1]]:v})}/>
                <EditableText value={data[keys[2]]} isTextArea className={sparkTwItem(block, "services", i, "desc", `mt-4 block leading-7 ${theme.sub}`)} onSave={(v)=>onUpdate({[keys[2]]:v})}/>
            </article>)}</div>
        </div>
    </section>;
}

export function ServicesFeaturePremiumBlock({block,onUpdate,globalTheme}) {
    const {data,theme,family,items}=useData(ServicesFeaturePremiumSchema,block,globalTheme);
    const featured=items[0], supporting=items.slice(1);
    const featuredImage=data.featured_image_url || data.image_url;
    return <section data-cosmic-services-feature-premium="true" className={sparkTw(block, "section_7", `px-6 py-20 sm:px-10 lg:px-14 lg:py-28 ${theme.bg}`)}>
        <div className={sparkTw(block, "wrapper_22", "mx-auto max-w-7xl")}><Intro block={block} data={data} theme={theme} onUpdate={onUpdate}/>
            <div className={sparkTw(block, "wrapper_23", "mt-14 grid gap-5 lg:grid-cols-[1.15fr_.85fr]")}>
                <article className={sparkTwItem(block, "services", 0, "card", `overflow-hidden rounded-[2rem] border ${theme.border} ${theme.surface}`)}>
                    <div className={sparkTwItem(block, "services", 0, "media", "relative min-h-[340px] overflow-hidden")}>
                        {featuredImage ? <img src={featuredImage} alt="" className={sparkTwItem(block, "services", 0, "image", "absolute inset-0 h-full w-full object-cover")}/> : null}
                    </div>
                    <div className={sparkTwItem(block, "services", 0, "content", "p-8 sm:p-10")}>
                        <ServiceCopy block={block} data={data} keys={featured} onUpdate={onUpdate} itemIndex={0} numberClass={theme.sub} titleClass={theme.text} textClass={theme.sub}/>
                        <EditableText value={data.featured_meta} className={sparkTwItem(block, "services", 0, "meta", `mt-10 block border-t pt-6 text-sm ${theme.sub}`)} onSave={(featured_meta)=>onUpdate({featured_meta})}/>
                    </div>
                </article>
                <div className={sparkTw(block, "wrapper_26", "grid gap-4 sm:grid-cols-2")}>{supporting.map((keys,i)=><article key={keys[0]} className={sparkTwItem(block, "services", i+1, "card", `overflow-hidden rounded-3xl border ${theme.border} ${theme.surface}`)}>
                    {(data[imageSlots[i+1]] || "") ? <div className={sparkTwItem(block, "services", i+1, "media", "relative h-36 overflow-hidden")}><img src={data[imageSlots[i+1]]} alt="" className={sparkTwItem(block, "services", i+1, "image", "absolute inset-0 h-full w-full object-cover")}/></div> : null}
                    <div className={sparkTwItem(block, "services", i+1, "content", "p-7")}><ServiceCopy block={block} data={data} keys={keys} onUpdate={onUpdate} itemIndex={i+1} numberClass={theme.sub} titleClass={theme.text} textClass={theme.sub}/></div>
                </article>)}</div>
            </div>
        </div>
    </section>;
}
