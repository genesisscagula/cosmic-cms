import { EditableButton } from "../Shared/EditableButton";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
const F=(key,type='text',label=null)=>({key,type,label:label||key.replaceAll('_',' ')});
const T=({value,className='',fieldPath,cosmicType,area=false,style})=><EditableText value={value} className={className} fieldPath={fieldPath} cosmicType={cosmicType} isTextArea={area} style={style}/>;
const B=({label,url='#',className='',fieldPath,style})=><EditableButton label={label} url={url} className={className} fieldPath={fieldPath} style={style}/>;
const I=({src,className='',fieldPath,style})=><EditableImage src={src} className={className} fieldPath={fieldPath} style={style}/>;
const P={navy:'#123047',ocean:'#2e6f78',sage:'#a9b9a8',sand:'#eee7dc',paper:'#fbfaf7',white:'#fff',ink:'#18252d',muted:'#68777f',line:'#d7ddd9',gold:'#b58a4b'};
const listings=[
 {title:'Harbour House',meta:'3 Bed · 2 Bath · 2 Car',price:'Guide $2.4M',location:'Balmoral',image_url:'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=88'},
 {title:'The Esplanade',meta:'2 Bed · 2 Bath · 1 Car',price:'Guide $1.65M',location:'Manly',image_url:'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=1200&q=88'},
 {title:'Garden Terrace',meta:'4 Bed · 3 Bath · 2 Car',price:'Auction',location:'Mosman',image_url:'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=88'}
];
const paths=[{title:'Buy with clarity',text:'Shortlist well, inspect with purpose, and negotiate with local context.'},{title:'Sell with a plan',text:'Position the property, launch to market, and manage every decision with confidence.'},{title:'Know your value',text:'Get a practical appraisal grounded in current buyer demand and recent results.'}];
const harborBenefits=[
 {icon:'diamond',title:'Premium Properties',text:'Handpicked, high-quality listings you can trust.'},
 {icon:'key',title:'Expert Guidance',text:'Local expertise and dedicated support every step of the way.'},
 {icon:'shield',title:'Trusted & Transparent',text:'Honest service and clear communication always.'},
 {icon:'home',title:'Invest in Your Future',text:'Smart real estate choices for long-term value.'},
];
const HarborIcon=({name,className='h-5 w-5'})=>{
 const common={viewBox:'0 0 24 24',fill:'none',stroke:'currentColor',strokeWidth:1.7,strokeLinecap:'round',strokeLinejoin:'round',className,'aria-hidden':true};
 if(name==='search')return <svg {...common}><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg>;
 if(name==='pin')return <svg {...common}><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.4"/></svg>;
 if(name==='building')return <svg {...common}><path d="M4 21V7l8-4 8 4v14"/><path d="M8 9h2M14 9h2M8 13h2M14 13h2M9 21v-4h6v4"/></svg>;
 if(name==='tag')return <svg {...common}><path d="M20 13 13 20 4 11V4h7l9 9Z"/><circle cx="8.5" cy="8.5" r="1"/></svg>;
 if(name==='bed')return <svg {...common}><path d="M3 18v-7M21 18v-5a2 2 0 0 0-2-2H9a3 3 0 0 0-3 3v1M3 15h18M6 11V8h5v3"/></svg>;
 if(name==='bath')return <svg {...common}><path d="M4 13h16M6 13v2a5 5 0 0 0 5 5h2a5 5 0 0 0 5-5v-2M8 10V6a2 2 0 0 1 4 0"/></svg>;
 if(name==='area')return <svg {...common}><rect x="4" y="4" width="16" height="16" rx="1"/><path d="M8 16V8h8"/></svg>;
 if(name==='heart')return <svg {...common}><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/></svg>;
 if(name==='diamond')return <svg {...common}><path d="m3 9 4-5h10l4 5-9 11L3 9Z"/><path d="m7 4 5 16 5-16M3 9h18"/></svg>;
 if(name==='key')return <svg {...common}><circle cx="8" cy="15" r="4"/><path d="m11 12 8-8m-3 3 2 2m-5 1 2 2"/></svg>;
 if(name==='shield')return <svg {...common}><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>;
 if(name==='sell')return <svg {...common}><path d="M4 20V8l8-5 8 5v12"/><path d="M8 20v-5h8v5M7 10h10"/><path d="M15 7h5v5"/></svg>;
 if(name==='rent')return <svg {...common}><rect x="5" y="4" width="14" height="16" rx="1"/><path d="M9 8h2M13 8h2M9 12h2M13 12h2M10 20v-4h4v4"/></svg>;
 if(name==='buy')return <svg {...common}><path d="M3 11 12 3l9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/><circle cx="17.5" cy="6.5" r="2.2"/></svg>;
 return <svg {...common}><path d="M3 11 12 3l9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/></svg>;
};
export const MarketplaceHarborHeroSchema={type:'marketplace_harbor_hero',title:'Harbor & Key Luxury Waterfront Hero',category:'Marketplace',defaults:{eyebrow:'PREMIUM REAL ESTATE, PERSONALIZED FOR YOU',heading:'Find Your Dream Home.',accent_heading:'Key to Your New Harbor.',text:'Harbor & Key Realty connects you to exceptional properties and experiences. Let us help you find a place to live, invest, and thrive.',primary_label:'BROWSE PROPERTIES',primary_url:'/listings',secondary_label:'WATCH VIDEO',secondary_url:'#video',image_url:'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=2200&q=90',location_label:'LOCATION',location_placeholder:'City, Neighborhood, or ZIP',property_type_label:'PROPERTY TYPE',property_type_value:'All Types',price_range_label:'PRICE RANGE',price_range_value:'Any Price',beds_label:'BEDS',beds_value:'Any',baths_label:'BATHS',baths_value:'Any',search_label:'SEARCH PROPERTIES',benefits:harborBenefits},fields:[F('eyebrow'),F('heading'),F('accent_heading'),F('text','textarea'),F('primary_label'),F('primary_url'),F('secondary_label'),F('secondary_url'),F('image_url','image'),F('location_label'),F('location_placeholder'),F('property_type_label'),F('property_type_value'),F('price_range_label'),F('price_range_value'),F('beds_label'),F('beds_value'),F('baths_label'),F('baths_value'),F('search_label'),F('benefits','repeater')]};
export function MarketplaceHarborHeroBlock({block}){
 const d={...MarketplaceHarborHeroSchema.defaults,...block};
 const benefits=Array.isArray(d.benefits)&&d.benefits.length?d.benefits:harborBenefits;
 const filters=[
  {icon:'pin',label:d.location_label,value:d.location_placeholder},
  {icon:'building',label:d.property_type_label,value:d.property_type_value},
  {icon:'tag',label:d.price_range_label,value:d.price_range_value},
  {icon:'bed',label:d.beds_label,value:d.beds_value},
  {icon:'bath',label:d.baths_label,value:d.baths_value},
 ];
 return <section data-marketplace-harbor-home-hero="true" className="cosmic-tw-own-section-x cosmic-tw-own-section-y w-full overflow-visible p-0" style={{background:P.white,color:'#0b2039',padding:0}}>
  <div className="relative min-h-[420px] overflow-hidden lg:min-h-[420px]">
   <I src={d.image_url} fieldPath="image_url" className="absolute inset-0 h-full w-full" style={{borderRadius:0,position:'absolute',inset:0}}/>
   <div className="absolute inset-0" style={{background:'linear-gradient(90deg,rgba(3,22,43,.96) 0%,rgba(4,24,45,.86) 29%,rgba(4,24,45,.52) 48%,rgba(4,24,45,.16) 72%,rgba(4,24,45,.03) 100%)'}}/>
   <div className="relative mx-auto flex min-h-[420px] max-w-[1536px] items-center px-6 py-10 sm:px-10 lg:min-h-[420px] lg:px-[92px] lg:py-10">
    <div className="max-w-[650px]">
     <T value={d.eyebrow} fieldPath="eyebrow" className="block text-[11px] font-bold uppercase tracking-[.13em]" style={{color:'#d1aa59'}}/>
     <T value={d.heading} fieldPath="heading" cosmicType="h1" className="mt-4 block font-serif text-[clamp(3.4rem,5.2vw,5.1rem)] leading-[1.01] tracking-[-.035em] text-white"/>
     <T value={d.accent_heading} fieldPath="accent_heading" className="mt-1 block font-serif text-[clamp(2.9rem,4.7vw,4.7rem)] leading-[1.02] tracking-[-.035em]" style={{color:'#d2ae68'}}/>
     <T value={d.text} fieldPath="text" area className="mt-5 block max-w-[500px] text-[15px] leading-7 text-white/90"/>
     <div className="mt-7 flex flex-wrap items-center gap-4">
      <B label={d.primary_label} url={d.primary_url} fieldPath="primary_label" className="inline-flex min-h-[46px] items-center gap-3 px-6 text-[12px] font-black text-white" style={{background:'#c6a052',borderRadius:3}}/>
      <B label={d.secondary_label} url={d.secondary_url} fieldPath="secondary_label" className="inline-flex min-h-[46px] items-center border border-white/15 bg-[#102946]/70 px-6 text-[12px] font-black text-white backdrop-blur-sm" style={{borderRadius:3}}/>
     </div>
    </div>
   </div>
  </div>
  <div className="relative z-20 mx-auto -mt-[46px] max-w-[1360px] px-5 sm:px-8 lg:px-10">
   <div className="grid overflow-hidden border bg-white shadow-[0_12px_35px_rgba(15,31,48,.13)] lg:grid-cols-[1.25fr_1fr_.95fr_.62fr_.62fr_auto]" style={{borderColor:'#e6e7e8',borderRadius:8}}>
    {filters.map((item,i)=><div key={i} className="flex min-h-[92px] items-center gap-3 border-b px-5 py-4 lg:border-b-0 lg:border-r" style={{borderColor:'#e4e7ea'}}>
      <div className="shrink-0 text-[#526174]"><HarborIcon name={item.icon} className="h-[18px] w-[18px]"/></div>
      <div className="min-w-0"><div className="text-[9px] font-black uppercase tracking-[.08em] text-[#23334a]">{item.label}</div><div className="mt-3 truncate text-[12px] text-[#79828c]">{item.value}</div></div>
    </div>)}
    <a href="/listings" className="m-4 flex min-h-[52px] items-center justify-center gap-3 whitespace-nowrap px-6 text-[11px] font-black text-white" style={{background:'#071f3d',borderRadius:4}}><HarborIcon name="search" className="h-5 w-5"/>{d.search_label}</a>
   </div>
  </div>
  <div className="mx-auto grid max-w-[1360px] gap-0 px-5 py-8 sm:px-8 md:grid-cols-2 lg:grid-cols-4 lg:px-10 lg:py-9">
   {benefits.slice(0,4).map((it,i)=><div key={i} className={`flex min-h-[92px] items-center gap-4 px-4 py-4 ${i>0?'lg:border-l':''}`} style={{borderColor:'#e1e4e6'}}>
    <div className="flex h-[58px] w-[58px] shrink-0 items-center justify-center rounded-full" style={{background:'#071f3d',color:'#caa24f'}}><HarborIcon name={it.icon||['diamond','key','shield','home'][i]} className="h-7 w-7"/></div>
    <div><T value={it.title} fieldPath={`benefits.${i}.title`} cosmicType="h3" className="block text-[13px] font-extrabold text-[#0d2340]"/><T value={it.text} fieldPath={`benefits.${i}.text`} area className="mt-2 block text-[11px] leading-5 text-[#6b7480]"/></div>
   </div>)}
  </div>
 </section>;
}
const harborHomeProperties=[
 {status:'FOR SALE',title:'Modern Waterfront Villa',location:'Lapu-Lapu City, Cebu',beds:'4',baths:'4',area:'320 m²',price:'₱28,500,000',image_url:'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=90'},
 {status:'FOR SALE',title:'Contemporary Family Home',location:'Talamban, Cebu City',beds:'5',baths:'4',area:'280 m²',price:'₱18,750,000',image_url:'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=90'},
 {status:'FOR SALE',title:'Luxury Condo with Ocean View',location:'IT Park, Cebu City',beds:'2',baths:'2',area:'120 m²',price:'₱12,900,000',image_url:'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=1200&q=90'},
 {status:'VIEW RENT',title:'Elegant House for Rent',location:'Banilad, Cebu City',beds:'4',baths:'3',area:'250 m²',price:'₱85,000 /month',image_url:'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1200&q=90'},
];
const harborAboutPoints=[
 'In-depth local market knowledge',
 'Personalized service tailored to your goals',
 'Commitment to transparency & results',
];
export const MarketplaceHarborHomeListingsSchema={type:'marketplace_harbor_home_listings',title:'Harbor & Key Home Featured Properties',category:'Marketplace',defaults:{eyebrow:'FEATURED PROPERTIES',heading:'Exceptional Homes.',accent_heading:'Extraordinary Living.',view_all_label:'VIEW ALL PROPERTIES',view_all_url:'/listings',items:harborHomeProperties},fields:[F('eyebrow'),F('heading'),F('accent_heading'),F('view_all_label'),F('view_all_url'),F('items','repeater')]};
export function MarketplaceHarborHomeListingsBlock({block}){
 const d={...MarketplaceHarborHomeListingsSchema.defaults,...block};
 const items=Array.isArray(d.items)&&d.items.length?d.items:harborHomeProperties;
 return <section data-marketplace-harbor-home-listings="true" className="w-full border-t px-5 py-12 sm:px-8 lg:px-10 lg:py-14" style={{background:'#fff',borderColor:'#eef0f1',color:'#0d2340'}}>
  <div className="mx-auto max-w-[1360px]">
   <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
    <div>
     <T value={d.eyebrow} fieldPath="eyebrow" className="block text-[10px] font-black uppercase tracking-[.13em]" style={{color:'#b58a4b'}}/>
     <h2 className="mt-3 font-serif text-[clamp(2rem,3vw,3.05rem)] leading-[1.04] tracking-[-.025em]"><T value={d.heading} fieldPath="heading"/><span> </span><T value={d.accent_heading} fieldPath="accent_heading" style={{color:'#b58a4b'}}/></h2>
    </div>
    <B label={`${d.view_all_label}  →`} url={d.view_all_url} fieldPath="view_all_label" className="inline-flex items-center text-[10px] font-black uppercase tracking-[.08em]" style={{color:'#a57a35',background:'transparent',padding:0}}/>
   </div>
   <div className="mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    {items.slice(0,4).map((it,i)=><article key={i} className="group overflow-hidden border bg-white shadow-[0_3px_12px_rgba(13,35,64,.06)]" style={{borderColor:'#e4e7ea',borderRadius:5}}>
     <div className="relative h-[168px] overflow-hidden sm:h-[190px] xl:h-[164px]">
      <I src={it.image_url} fieldPath={`items.${i}.image_url`} className="h-full w-full transition duration-500 group-hover:scale-[1.025]" style={{borderRadius:0}}/>
      <div className="absolute left-3 top-3 rounded-[2px] px-3 py-2 text-[9px] font-black uppercase tracking-[.05em] text-white" style={{background:'#071f3d'}}><T value={it.status} fieldPath={`items.${i}.status`}/></div>
      <span className="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full border border-white/75 bg-black/10 text-white backdrop-blur-sm"><HarborIcon name="heart" className="h-[19px] w-[19px]"/></span>
     </div>
     <div className="p-4">
      <T value={it.title} fieldPath={`items.${i}.title`} cosmicType="h3" className="block text-[14px] font-extrabold leading-5 text-[#102642]"/>
      <T value={it.location} fieldPath={`items.${i}.location`} className="mt-1 block text-[11px] text-[#8a929a]"/>
      <div className="mt-4 flex items-center gap-4 border-t pt-3 text-[10px] text-[#66717c]" style={{borderColor:'#eef0f2'}}>
       <span className="inline-flex items-center gap-1.5"><HarborIcon name="bed" className="h-[14px] w-[14px]"/><T value={it.beds} fieldPath={`items.${i}.beds`}/></span>
       <span className="inline-flex items-center gap-1.5"><HarborIcon name="bath" className="h-[14px] w-[14px]"/><T value={it.baths} fieldPath={`items.${i}.baths`}/></span>
       <span className="inline-flex items-center gap-1.5"><HarborIcon name="area" className="h-[14px] w-[14px]"/><T value={it.area} fieldPath={`items.${i}.area`}/></span>
       <T value={it.price} fieldPath={`items.${i}.price`} className="ml-auto whitespace-nowrap text-[12px] font-black text-[#0b2442]"/>
      </div>
     </div>
    </article>)}
   </div>
  </div>
 </section>;
}
export const MarketplaceHarborHomeAboutSchema={type:'marketplace_harbor_home_about',title:'Harbor & Key Home About',category:'Marketplace',defaults:{eyebrow:'ABOUT HARBOR & KEY REALTY',heading:'Trusted Local Experts.',accent_heading:'Dedicated to You.',text:'With deep roots in Cebu’s most desirable communities, we deliver personalized real estate solutions backed by integrity, expertise, and a passion for people.',image_url:'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=1500&q=90',points:harborAboutPoints,button_label:'LEARN MORE ABOUT US',button_url:'/about'},fields:[F('eyebrow'),F('heading'),F('accent_heading'),F('text','textarea'),F('image_url','image'),F('points','repeater'),F('button_label'),F('button_url')]};
export function MarketplaceHarborHomeAboutBlock({block}){
 const d={...MarketplaceHarborHomeAboutSchema.defaults,...block};
 const points=Array.isArray(d.points)&&d.points.length?d.points:harborAboutPoints;
 return <section data-marketplace-harbor-home-about="true" className="w-full border-t px-5 py-14 sm:px-8 lg:px-10 lg:py-16" style={{background:'#fbfaf8',borderColor:'#ebe9e5',color:'#0d2340'}}>
  <div className="mx-auto grid max-w-[1360px] overflow-hidden border bg-white lg:grid-cols-[1.06fr_.94fr]" style={{borderColor:'#e5e5e2',borderRadius:5}}>
   <div className="min-h-[390px] lg:min-h-[430px]"><I src={d.image_url} fieldPath="image_url" className="h-full w-full" style={{borderRadius:0}}/></div>
   <div className="flex items-center p-7 sm:p-10 lg:p-12 xl:p-14">
    <div className="max-w-[520px]">
     <T value={d.eyebrow} fieldPath="eyebrow" className="block text-[10px] font-black uppercase tracking-[.14em]" style={{color:'#b58a4b'}}/>
     <T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-4 block font-serif text-[clamp(2.35rem,3.3vw,3.45rem)] leading-[1.02] tracking-[-.025em] text-[#102642]"/>
     <T value={d.accent_heading} fieldPath="accent_heading" className="block font-serif text-[clamp(2.35rem,3.3vw,3.45rem)] leading-[1.02] tracking-[-.025em] text-[#102642]"/>
     <T value={d.text} fieldPath="text" area className="mt-5 block text-[13px] leading-6 text-[#69737e]"/>
     <div className="mt-5 grid gap-2.5">
      {points.slice(0,5).map((point,i)=><div key={i} className="flex items-start gap-2.5 text-[12px] font-semibold text-[#5f6872]"><span className="mt-[1px] flex h-[17px] w-[17px] shrink-0 items-center justify-center rounded-full text-[10px]" style={{border:'1px solid #c7a35b',color:'#b58a4b'}}>✓</span><T value={typeof point==='string'?point:point?.text} fieldPath={`points.${i}${typeof point==='string'?'':'.text'}`}/></div>)}
     </div>
     <B label={`${d.button_label}  →`} url={d.button_url} fieldPath="button_label" className="mt-7 inline-flex min-h-[42px] items-center px-5 text-[10px] font-black text-white" style={{background:'#071f3d',borderRadius:3}}/>
    </div>
   </div>
  </div>
 </section>;
}

const harborHomeCommunities=[
 {title:'Mactan Island',subtitle:'Resort living by the sea',url:'/neighborhoods',image_url:'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1100&q=88'},
 {title:'Cebu Business Park',subtitle:"The city’s premier lifestyle hub",url:'/neighborhoods',image_url:'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1100&q=88'},
 {title:'Talisay City',subtitle:'Peaceful living, close to everything',url:'/neighborhoods',image_url:'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1100&q=88'},
 {title:'Bantayan Island',subtitle:'Island life, unspoiled beauty',url:'/neighborhoods',image_url:'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1100&q=88'},
];
export const MarketplaceHarborHomeCommunitiesSchema={type:'marketplace_harbor_home_communities',title:'Harbor & Key Home Prime Locations',category:'Marketplace',defaults:{eyebrow:'EXPLORE PRIME LOCATIONS',heading:'Featured Communities',view_all_label:'VIEW ALL COMMUNITIES',view_all_url:'/neighborhoods',items:harborHomeCommunities},fields:[F('eyebrow'),F('heading'),F('view_all_label'),F('view_all_url'),F('items','repeater')]};
export function MarketplaceHarborHomeCommunitiesBlock({block}){
 const d={...MarketplaceHarborHomeCommunitiesSchema.defaults,...block};
 const items=Array.isArray(d.items)&&d.items.length?d.items:harborHomeCommunities;
 return <section data-marketplace-harbor-home-communities="true" className="w-full border-t px-5 py-12 sm:px-8 lg:px-10 lg:py-14" style={{background:'#fff',borderColor:'#eeece8',color:'#0d2340'}}>
  <div className="mx-auto max-w-[1360px]">
   <div className="text-center"><T value={d.eyebrow} fieldPath="eyebrow" className="block text-[10px] font-black uppercase tracking-[.16em]" style={{color:'#b58a4b'}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-2 block font-serif text-[clamp(2rem,2.7vw,2.65rem)] font-bold leading-[1.05] tracking-[-.025em] text-[#0d2340]"/></div>
   <div className="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{items.slice(0,4).map((it,i)=><a key={i} href={it.url||d.view_all_url} className="group relative block h-[190px] overflow-hidden border" style={{borderColor:'#e5e7e8',borderRadius:5}}><I src={it.image_url} fieldPath={`items.${i}.image_url`} className="absolute inset-0 h-full w-full transition-transform duration-500 group-hover:scale-[1.035]" style={{borderRadius:0}}/><div className="absolute inset-0" style={{background:'linear-gradient(180deg,rgba(6,28,52,.02) 30%,rgba(6,28,52,.78) 100%)'}}/><div className="absolute inset-x-0 bottom-0 p-5 text-center text-white"><T value={it.title} fieldPath={`items.${i}.title`} cosmicType="h3" className="block font-serif text-[18px] font-bold"/><T value={it.subtitle} fieldPath={`items.${i}.subtitle`} className="mt-1 block text-[10px] text-white/80"/></div></a>)}</div>
   <div className="mt-5 text-center"><B label={`${d.view_all_label}  →`} url={d.view_all_url} fieldPath="view_all_label" className="inline-flex min-h-[34px] items-center px-3 text-[9px] font-black uppercase tracking-[.08em]" style={{color:'#a57a35',background:'transparent'}}/></div>
  </div>
 </section>;
}

const harborHomeSolutions=[
 {icon:'buy',title:'Buy a Home',text:'Find your dream home with confidence. We’ll guide you every step of the way.',button_label:'Explore Homes',button_url:'/buyers'},
 {icon:'sell',title:'Sell Your Property',text:'Get top value for your property with our proven marketing and local expertise.',button_label:'List Your Property',button_url:'/sellers'},
 {icon:'rent',title:'Rent with Ease',text:'Discover quality rentals that fit your lifestyle and budget.',button_label:'View Rentals',button_url:'/listings'},
];
export const MarketplaceHarborHomeSolutionsSchema={type:'marketplace_harbor_home_solutions',title:'Harbor & Key Home Buy Sell Rent',category:'Marketplace',defaults:{eyebrow:'BUY. SELL. RENT.',heading:'Solutions for Every Move',items:harborHomeSolutions},fields:[F('eyebrow'),F('heading'),F('items','repeater')]};
export function MarketplaceHarborHomeSolutionsBlock({block}){
 const d={...MarketplaceHarborHomeSolutionsSchema.defaults,...block};
 const items=Array.isArray(d.items)&&d.items.length?d.items:harborHomeSolutions;
 return <section data-marketplace-harbor-home-solutions="true" className="w-full border-t px-5 py-12 sm:px-8 lg:px-10 lg:py-14" style={{background:'#fbfaf8',borderColor:'#eeece8',color:'#0d2340'}}>
  <div className="mx-auto max-w-[1240px]">
   <div className="text-center"><T value={d.eyebrow} fieldPath="eyebrow" className="block text-[10px] font-black uppercase tracking-[.18em]" style={{color:'#b58a4b'}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-2 block font-serif text-[clamp(2rem,2.7vw,2.65rem)] font-bold leading-[1.05] tracking-[-.025em] text-[#0d2340]"/></div>
   <div className="mt-7 grid gap-4 md:grid-cols-3">{items.slice(0,3).map((it,i)=><article key={i} className="flex min-h-[185px] gap-5 border bg-white p-7" style={{borderColor:'#e5e4e1',borderRadius:5,boxShadow:'0 4px 18px rgba(13,35,64,.035)'}}><div className="flex h-12 w-12 shrink-0 items-center justify-center" style={{color:'#c39442'}}><HarborIcon name={it.icon||['buy','sell','rent'][i]} className="h-9 w-9"/></div><div className="min-w-0"><T value={it.title} fieldPath={`items.${i}.title`} cosmicType="h3" className="block font-serif text-[18px] font-bold text-[#102642]"/><T value={it.text} fieldPath={`items.${i}.text`} area className="mt-2 block text-[11px] leading-5 text-[#6e7781]"/><B label={`${it.button_label||'Learn More'}  →`} url={it.button_url||'#'} fieldPath={`items.${i}.button_label`} className="mt-4 inline-flex min-h-[28px] items-center text-[9px] font-black" style={{color:'#0d3157',background:'transparent'}}/></div></article>)}</div>
  </div>
 </section>;
}

const harborHomeProcess=[
 {number:'01',title:'Discover',text:'Tell us what you’re looking for and your must-haves.'},
 {number:'02',title:'Explore',text:'We’ll curate the best options that match your needs.'},
 {number:'03',title:'Decide',text:'Tour, compare, and choose the one that feels right.'},
 {number:'04',title:'Own',text:'We handle the details from offer to closing.'},
];
export const MarketplaceHarborHomeProcessSchema={type:'marketplace_harbor_home_process',title:'Harbor & Key Home Buying Process',category:'Marketplace',defaults:{eyebrow:'HOW WE HELP YOU',heading:'A Seamless Path to Your Perfect Home',items:harborHomeProcess},fields:[F('eyebrow'),F('heading'),F('items','repeater')]};
export function MarketplaceHarborHomeProcessBlock({block}){
 const d={...MarketplaceHarborHomeProcessSchema.defaults,...block};
 const items=Array.isArray(d.items)&&d.items.length?d.items:harborHomeProcess;
 return <section data-marketplace-harbor-home-process="true" className="w-full border-t px-5 py-12 sm:px-8 lg:px-10 lg:py-14" style={{background:'#fff',borderColor:'#eeece8',color:'#0d2340'}}>
  <div className="mx-auto max-w-[1240px]">
   <div className="text-center"><T value={d.eyebrow} fieldPath="eyebrow" className="block text-[10px] font-black uppercase tracking-[.18em]" style={{color:'#b58a4b'}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-2 block font-serif text-[clamp(2rem,2.7vw,2.65rem)] font-bold leading-[1.05] tracking-[-.025em] text-[#0d2340]"/></div>
   <div className="relative mt-8 grid gap-8 md:grid-cols-4 md:gap-0"><div className="absolute left-[12.5%] right-[12.5%] top-[18px] hidden h-px md:block" style={{background:'#d9c49c'}}/>{items.slice(0,4).map((it,i)=><article key={i} className="relative z-10 px-4 text-center"><div className="mx-auto flex h-9 w-9 items-center justify-center rounded-full text-[10px] font-black text-white" style={{background:'#c99c4c',boxShadow:'0 0 0 8px #fff'}}><T value={it.number||String(i+1).padStart(2,'0')} fieldPath={`items.${i}.number`}/></div><T value={it.title} fieldPath={`items.${i}.title`} cosmicType="h3" className="mt-4 block font-serif text-[16px] font-bold text-[#102642]"/><T value={it.text} fieldPath={`items.${i}.text`} area className="mx-auto mt-2 block max-w-[210px] text-[10px] leading-5 text-[#727b85]"/></article>)}</div>
  </div>
 </section>;
}


const harborHomeTestimonials=[
 {quote:'Harbor & Key Realty made our home buying journey smooth and stress-free. Their team is professional, responsive, and truly cares.',name:'Jasmine Leith',location:'Lapu-Lapu City, Cebu',avatar_url:'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=240&q=86'},
 {quote:'We sold our property above market value in just three weeks. Their marketing and local network are unmatched.',name:'Ronald S.',location:'Cebu City',avatar_url:'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=240&q=86'},
 {quote:'Exceptional service from start to finish. I now enjoy my dream home overlooking the ocean.',name:'Michael A.',location:'Talisay City',avatar_url:'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=240&q=86'},
];
export const MarketplaceHarborHomeTestimonialsSchema={type:'marketplace_harbor_home_testimonials',title:'Harbor & Key Home Client Testimonials',category:'Marketplace',defaults:{eyebrow:'CLIENT LOVE',heading:'What Our Clients Say',items:harborHomeTestimonials},fields:[F('eyebrow'),F('heading'),F('items','repeater')]};
export function MarketplaceHarborHomeTestimonialsBlock({block}){
 const d={...MarketplaceHarborHomeTestimonialsSchema.defaults,...block};
 const items=Array.isArray(d.items)&&d.items.length?d.items:harborHomeTestimonials;
 return <section data-marketplace-harbor-home-testimonials="true" className="w-full border-t px-5 py-12 sm:px-8 lg:px-10 lg:py-14" style={{background:'#fbfaf8',borderColor:'#eeece8',color:'#0d2340'}}>
  <div className="mx-auto max-w-[1240px]">
   <div className="text-center"><T value={d.eyebrow} fieldPath="eyebrow" className="block text-[10px] font-black uppercase tracking-[.18em]" style={{color:'#b58a4b'}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-2 block font-serif text-[clamp(2rem,2.7vw,2.65rem)] font-bold leading-[1.05] tracking-[-.025em] text-[#0d2340]"/></div>
   <div className="mt-7 grid gap-4 md:grid-cols-3">{items.slice(0,3).map((it,i)=><article key={i} className="flex min-h-[220px] flex-col border bg-white p-6 shadow-[0_8px_24px_rgba(15,31,48,.045)]" style={{borderColor:'#e8e7e3',borderRadius:5}}><div className="font-serif text-[34px] leading-none" style={{color:'#c99c4c'}}>“</div><T value={it.quote} fieldPath={`items.${i}.quote`} area className="mt-1 block flex-1 text-[12px] leading-6 text-[#596674]"/><div className="mt-5 flex items-center gap-3"><I src={it.avatar_url} fieldPath={`items.${i}.avatar_url`} className="h-10 w-10 shrink-0 rounded-full" style={{borderRadius:999}}/><div><T value={it.name} fieldPath={`items.${i}.name`} className="block text-[11px] font-black text-[#102642]"/><T value={it.location} fieldPath={`items.${i}.location`} className="mt-1 block text-[9px] text-[#7a838b]"/></div></div></article>)}</div>
  </div>
 </section>;
}

const harborHomeTeam=[
 {name:'Kaye Rivera',role:'Real Estate Broker',bio:'Specializes in luxury homes & waterfront properties.',image_url:'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=600&q=88'},
 {name:'James Mendoza',role:'Senior Property Advisor',bio:'Expert in investments and high-value properties.',image_url:'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=600&q=88'},
 {name:'Angela Torres',role:'Client Relations Manager',bio:'Dedicated to providing a seamless client experience.',image_url:'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=600&q=88'},
 {name:'Mark Lim',role:'Leasing Specialist',bio:'Helps clients find the perfect rental homes and spaces.',image_url:'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=600&q=88'},
];
export const MarketplaceHarborHomeTeamSchema={type:'marketplace_harbor_home_team',title:'Harbor & Key Home Team',category:'Marketplace',defaults:{eyebrow:'MEET OUR TEAM',heading:'The People Behind Your Next Move',items:harborHomeTeam},fields:[F('eyebrow'),F('heading'),F('items','repeater')]};
export function MarketplaceHarborHomeTeamBlock({block}){
 const d={...MarketplaceHarborHomeTeamSchema.defaults,...block};
 const items=Array.isArray(d.items)&&d.items.length?d.items:harborHomeTeam;
 return <section data-marketplace-harbor-home-team="true" className="w-full border-t px-5 py-12 sm:px-8 lg:px-10 lg:py-14" style={{background:'#fff',borderColor:'#eeece8',color:'#0d2340'}}>
  <div className="mx-auto max-w-[1240px]">
   <div className="text-center"><T value={d.eyebrow} fieldPath="eyebrow" className="block text-[10px] font-black uppercase tracking-[.18em]" style={{color:'#b58a4b'}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-2 block font-serif text-[clamp(2rem,2.7vw,2.65rem)] font-bold leading-[1.05] tracking-[-.025em] text-[#0d2340]"/></div>
   <div className="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{items.slice(0,4).map((it,i)=><article key={i} className="grid min-h-[185px] grid-cols-[42%_58%] overflow-hidden border bg-white" style={{borderColor:'#e8e7e3',borderRadius:5}}><div className="min-h-[185px]"><I src={it.image_url} fieldPath={`items.${i}.image_url`} className="h-full w-full" style={{borderRadius:0}}/></div><div className="flex flex-col justify-center p-4"><T value={it.name} fieldPath={`items.${i}.name`} cosmicType="h3" className="block font-serif text-[15px] font-bold text-[#102642]"/><T value={it.role} fieldPath={`items.${i}.role`} className="mt-1 block text-[9px] font-bold" style={{color:'#a77a32'}}/><T value={it.bio} fieldPath={`items.${i}.bio`} area className="mt-2 block text-[9px] leading-[1.65] text-[#68737d]"/><div className="mt-3 flex gap-3 text-[10px] font-black text-[#0d2340]"><span>in</span><span>f</span><span>◎</span></div></div></article>)}</div>
  </div>
 </section>;
}

const harborHomeInsights=[
 {date:'May 10, 2024',title:'Why Waterfront Homes in Cebu Are a Smart Investment',url:'/blog',image_url:'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1000&q=88'},
 {date:'Apr 24, 2024',title:'Top 5 Family-Friendly Communities in Cebu to Consider',url:'/blog',image_url:'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1000&q=88'},
 {date:'Apr 05, 2024',title:'Renting vs. Buying: What’s Best for You in 2024?',url:'/blog',image_url:'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=1000&q=88'},
];
export const MarketplaceHarborHomeInsightsSchema={type:'marketplace_harbor_home_insights',title:'Harbor & Key Home Latest Insights',category:'Marketplace',defaults:{eyebrow:'LATEST INSIGHTS',heading:'Real Estate Tips & Updates',view_all_label:'VIEW ALL ARTICLES',view_all_url:'/blog',read_more_label:'Read More',items:harborHomeInsights},fields:[F('eyebrow'),F('heading'),F('view_all_label'),F('view_all_url'),F('read_more_label'),F('items','repeater')]};
export function MarketplaceHarborHomeInsightsBlock({block}){
 const d={...MarketplaceHarborHomeInsightsSchema.defaults,...block};
 const items=Array.isArray(d.items)&&d.items.length?d.items:harborHomeInsights;
 return <section data-marketplace-harbor-home-insights="true" className="w-full border-t px-5 py-12 sm:px-8 lg:px-10 lg:py-14" style={{background:'#fbfaf8',borderColor:'#eeece8',color:'#0d2340'}}>
  <div className="mx-auto max-w-[1240px]">
   <div className="text-center"><T value={d.eyebrow} fieldPath="eyebrow" className="block text-[10px] font-black uppercase tracking-[.18em]" style={{color:'#b58a4b'}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-2 block font-serif text-[clamp(2rem,2.7vw,2.65rem)] font-bold leading-[1.05] tracking-[-.025em] text-[#0d2340]"/></div>
   <div className="mt-7 grid gap-5 md:grid-cols-3">{items.slice(0,3).map((it,i)=><article key={i} className="overflow-hidden border bg-white" style={{borderColor:'#e8e7e3',borderRadius:5}}><a href={it.url||d.view_all_url} className="block h-[190px] overflow-hidden"><I src={it.image_url} fieldPath={`items.${i}.image_url`} className="h-full w-full" style={{borderRadius:0}}/></a><div className="p-5"><T value={it.date} fieldPath={`items.${i}.date`} className="block text-[8px] font-bold uppercase tracking-[.08em] text-[#7d858d]"/><T value={it.title} fieldPath={`items.${i}.title`} cosmicType="h3" className="mt-2 block font-serif text-[18px] font-bold leading-[1.25] text-[#102642]"/><a href={it.url||d.view_all_url} className="mt-4 inline-flex text-[9px] font-black" style={{color:'#0d2340'}}>{d.read_more_label} &nbsp; →</a></div></article>)}</div>
   <div className="mt-5 text-right"><B label={`${d.view_all_label}  →`} url={d.view_all_url} fieldPath="view_all_label" className="inline-flex min-h-[34px] items-center px-3 text-[9px] font-black uppercase tracking-[.08em]" style={{color:'#a57a35',background:'transparent'}}/></div>
  </div>
 </section>;
}

export const MarketplaceHarborHomeCtaSchema={type:'marketplace_harbor_home_cta',title:'Harbor & Key Home Waterfront CTA',category:'Marketplace',defaults:{eyebrow:'YOUR NEXT MOVE STARTS HERE',heading:'Ready to Find Your Place by the Sea?',text:'Let’s work together to find a home that matches your lifestyle and goals.',primary_label:'BOOK A CONSULTATION',primary_url:'/contact',secondary_label:'EXPLORE PROPERTIES',secondary_url:'/listings',image_url:'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=2200&q=90'},fields:[F('eyebrow'),F('heading'),F('text','textarea'),F('primary_label'),F('primary_url'),F('secondary_label'),F('secondary_url'),F('image_url','image')]};
export function MarketplaceHarborHomeCtaBlock({block}){
 const d={...MarketplaceHarborHomeCtaSchema.defaults,...block};
 return <section data-marketplace-harbor-home-cta="true" className="relative w-full overflow-hidden" style={{background:'#071f3d',color:'#fff'}}>
  <I src={d.image_url} fieldPath="image_url" className="absolute inset-0 h-full w-full" style={{borderRadius:0}}/>
  <div className="absolute inset-0" style={{background:'linear-gradient(90deg,rgba(4,27,54,.94),rgba(4,27,54,.78) 58%,rgba(4,27,54,.68))'}}/>
  <div className="relative mx-auto grid min-h-[190px] max-w-[1536px] items-center gap-7 px-6 py-10 sm:px-10 lg:grid-cols-[1fr_auto] lg:px-[78px]">
   <div className="max-w-[760px]"><T value={d.eyebrow} fieldPath="eyebrow" className="block text-[9px] font-black uppercase tracking-[.18em]" style={{color:'#d0aa5b'}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-2 block font-serif text-[clamp(2rem,3.2vw,3.2rem)] font-bold leading-[1.02] tracking-[-.025em] text-white"/><T value={d.text} fieldPath="text" area className="mt-3 block max-w-[640px] text-[12px] leading-6 text-white/75"/></div>
   <div className="flex flex-wrap gap-3 lg:justify-end"><B label={d.primary_label} url={d.primary_url} fieldPath="primary_label" className="inline-flex min-h-[44px] items-center justify-center px-6 text-[10px] font-black text-white" style={{background:'#c6a052',borderRadius:3}}/><B label={d.secondary_label} url={d.secondary_url} fieldPath="secondary_label" className="inline-flex min-h-[44px] items-center justify-center border px-6 text-[10px] font-black" style={{background:'#fff',color:'#071f3d',borderColor:'#fff',borderRadius:3}}/></div>
  </div>
 </section>;
}

export const MarketplaceHarborListingsSchema={type:'marketplace_harbor_listings',title:'Harbor & Key Featured Listings',category:'Marketplace',defaults:{eyebrow:'FEATURED HOMES',heading:'Properties worth a closer look.',text:'A considered selection of homes currently on the market.',items:listings},fields:[F('eyebrow'),F('heading'),F('text','textarea'),F('items','repeater')]};
export function MarketplaceHarborListingsBlock({block}){const d={...MarketplaceHarborListingsSchema.defaults,...block};const items=Array.isArray(d.items)&&d.items.length?d.items:listings;return <section className="px-6 py-24 lg:px-8 lg:py-32" style={{background:P.white,color:P.ink}}><div className="mx-auto max-w-[1420px]"><T value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.22em]" style={{color:P.ocean}}/><div className="mt-5 grid gap-8 lg:grid-cols-[1fr_.6fr] lg:items-end"><T value={d.heading} fieldPath="heading" cosmicType="h2" className="block text-4xl font-black leading-[.98] tracking-[-.045em] sm:text-6xl"/><T value={d.text} fieldPath="text" area className="block leading-8" style={{color:P.muted}}/></div><div className="mt-12 grid gap-5 lg:grid-cols-3">{items.slice(0,6).map((it,i)=><article key={i} className="border" style={{borderColor:P.line}}><div className="h-[360px]"><I src={it.image_url} fieldPath={`items.${i}.image_url`} className="h-full w-full"/></div><div className="p-6"><div className="flex justify-between gap-4 text-[10px] font-black uppercase tracking-[.12em]" style={{color:P.ocean}}><T value={it.location} fieldPath={`items.${i}.location`}/><T value={it.price} fieldPath={`items.${i}.price`}/></div><T value={it.title} fieldPath={`items.${i}.title`} cosmicType="h3" className="mt-4 block text-2xl font-black"/><T value={it.meta} fieldPath={`items.${i}.meta`} className="mt-2 block text-sm" style={{color:P.muted}}/></div></article>)}</div></div></section>}
export const MarketplaceHarborMarketSchema={type:'marketplace_harbor_market',title:'Harbor & Key Local Market Story',category:'Marketplace',defaults:{eyebrow:'LOCAL KNOWLEDGE',heading:'Property decisions are easier with context.',text:'We combine current buyer behaviour, recent comparable sales, and street-level knowledge to help clients move with more certainty.',image_url:'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=1500&q=88',quote:'Good advice is not about pushing a transaction. It is about knowing when, why, and how to move.'},fields:[F('eyebrow'),F('heading'),F('text','textarea'),F('image_url','image'),F('quote','textarea')]};
export function MarketplaceHarborMarketBlock({block}){const d={...MarketplaceHarborMarketSchema.defaults,...block};return <section className="px-6 py-24 lg:px-8 lg:py-32" style={{background:P.sand,color:P.ink}}><div className="mx-auto grid max-w-[1420px] gap-12 lg:grid-cols-[1.05fr_.95fr] lg:items-center"><div className="min-h-[520px]"><I src={d.image_url} fieldPath="image_url" className="h-full w-full"/></div><div><T value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.22em]" style={{color:P.ocean}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-5 block text-4xl font-black leading-[.98] tracking-[-.045em] sm:text-6xl"/><T value={d.text} fieldPath="text" area className="mt-6 block leading-8" style={{color:P.muted}}/><T value={d.quote} fieldPath="quote" area className="mt-8 block border-l-2 pl-5 font-serif text-2xl italic leading-9" style={{borderColor:P.gold}}/></div></div></section>}
export const MarketplaceHarborPathsSchema={type:'marketplace_harbor_paths',title:'Harbor & Key Buyer Seller Paths',category:'Marketplace',defaults:{eyebrow:'HOW WE HELP',heading:'A clear path whether you are buying, selling, or planning ahead.',items:paths},fields:[F('eyebrow'),F('heading'),F('items','repeater')]};
export function MarketplaceHarborPathsBlock({block}){const d={...MarketplaceHarborPathsSchema.defaults,...block};const items=Array.isArray(d.items)&&d.items.length?d.items:paths;return <section className="px-6 py-24 lg:px-8 lg:py-32" style={{background:P.navy,color:P.white}}><div className="mx-auto max-w-[1420px]"><T value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.22em]" style={{color:P.sage}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-5 block max-w-5xl text-4xl font-black leading-[.98] tracking-[-.045em] sm:text-6xl"/><div className="mt-12 grid gap-px bg-white/15 md:grid-cols-3">{items.slice(0,4).map((it,i)=><article key={i} className="min-h-[310px] p-8" style={{background:P.navy}}><span className="text-xs font-black" style={{color:P.gold}}>{String(i+1).padStart(2,'0')}</span><T value={it.title} fieldPath={`items.${i}.title`} cosmicType="h3" className="mt-20 block text-2xl font-black"/><T value={it.text} fieldPath={`items.${i}.text`} area className="mt-4 block text-sm leading-7 text-white/65"/></article>)}</div></div></section>}
export const MarketplaceHarborNeighborhoodSchema={type:'marketplace_harbor_neighborhood',title:'Harbor & Key Neighborhood Guide',category:'Marketplace',defaults:{eyebrow:'NEIGHBORHOOD GUIDE',heading:'Know the streets, not just the suburb.',text:'From commute and schools to weekend rhythm and buyer demand, local context changes how a property feels and how it performs.',items:[{title:'Harbour Villages',text:'Walkable pockets, ferry access, established homes, and tightly held streets.'},{title:'Beachside Living',text:'Coastal routines, apartment demand, lifestyle buyers, and seasonal competition.'},{title:'Family Enclaves',text:'Schools, parks, larger blocks, and long-term owner occupier demand.'}]},fields:[F('eyebrow'),F('heading'),F('text','textarea'),F('items','repeater')]};
export function MarketplaceHarborNeighborhoodBlock({block}){const d={...MarketplaceHarborNeighborhoodSchema.defaults,...block};const items=Array.isArray(d.items)?d.items:[];return <section className="px-6 py-24 lg:px-8 lg:py-32" style={{background:P.paper,color:P.ink}}><div className="mx-auto max-w-[1420px]"><T value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.22em]" style={{color:P.ocean}}/><div className="mt-5 grid gap-8 lg:grid-cols-[.8fr_1.2fr]"><div><T value={d.heading} fieldPath="heading" cosmicType="h2" className="block text-4xl font-black leading-[.98] tracking-[-.045em] sm:text-6xl"/><T value={d.text} fieldPath="text" area className="mt-6 block leading-8" style={{color:P.muted}}/></div><div className="border-t" style={{borderColor:P.line}}>{items.slice(0,5).map((it,i)=><article key={i} className="grid gap-4 border-b py-7 md:grid-cols-[80px_.8fr_1.2fr]" style={{borderColor:P.line}}><span className="text-xs font-black" style={{color:P.gold}}>{String(i+1).padStart(2,'0')}</span><T value={it.title} fieldPath={`items.${i}.title`} cosmicType="h3" className="text-xl font-black"/><T value={it.text} fieldPath={`items.${i}.text`} area className="text-sm leading-7" style={{color:P.muted}}/></article>)}</div></div></div></section>}
export const MarketplaceHarborProofSchema={type:'marketplace_harbor_proof',title:'Harbor & Key Results & Reviews',category:'Marketplace',defaults:{eyebrow:'CLIENT RESULTS',heading:'Calm advice when the stakes feel high.',quote:'Harbor & Key understood the market, explained every decision, and negotiated a result we felt genuinely good about.',name:'Recent seller',role:'Lower North Shore',stats:[{value:'92%',label:'referral-led business'},{value:'18 days',label:'median campaign to offer'},{value:'4.9/5',label:'client review average'}]},fields:[F('eyebrow'),F('heading'),F('quote','textarea'),F('name'),F('role'),F('stats','repeater')]};
export function MarketplaceHarborProofBlock({block}){const d={...MarketplaceHarborProofSchema.defaults,...block};const stats=Array.isArray(d.stats)?d.stats:[];return <section className="px-6 py-24 lg:px-8 lg:py-32" style={{background:P.white,color:P.ink}}><div className="mx-auto grid max-w-[1420px] gap-5 lg:grid-cols-[1.2fr_.8fr]"><figure className="m-0 border p-8 sm:p-12" style={{borderColor:P.line}}><T value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.22em]" style={{color:P.ocean}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-5 block text-4xl font-black leading-[.98] tracking-[-.045em] sm:text-6xl"/><T value={d.quote} fieldPath="quote" area className="mt-8 block font-serif text-2xl italic leading-9"/><figcaption className="mt-8 text-sm"><T value={d.name} fieldPath="name" className="font-black"/> · <T value={d.role} fieldPath="role" style={{color:P.muted}}/></figcaption></figure><div className="grid gap-px" style={{background:P.line}}>{stats.slice(0,4).map((s,i)=><div key={i} className="p-8" style={{background:P.sand}}><T value={s.value} fieldPath={`stats.${i}.value`} className="block text-5xl font-black" style={{color:P.navy}}/><T value={s.label} fieldPath={`stats.${i}.label`} className="mt-3 block text-xs font-black uppercase tracking-[.12em]" style={{color:P.muted}}/></div>)}</div></div></section>}
export const MarketplaceHarborPropertySchema={type:'marketplace_harbor_property',title:'Harbor & Key Property Detail',category:'Marketplace',defaults:{eyebrow:'FEATURED PROPERTY',heading:'A harbour-side home designed around light and outlook.',text:'A refined family residence with generous living spaces, landscaped entertaining, and an easy connection to the water.',image_url:'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=1800&q=88',price:'Guide $2.4M',meta:'4 Bed · 3 Bath · 2 Car · 612 sqm',button_label:'Arrange an Inspection',button_url:'/contact'},fields:[F('eyebrow'),F('heading'),F('text','textarea'),F('image_url','image'),F('price'),F('meta'),F('button_label'),F('button_url')]};
export function MarketplaceHarborPropertyBlock({block}){const d={...MarketplaceHarborPropertySchema.defaults,...block};return <section className="px-6 py-20 lg:px-8 lg:py-28" style={{background:P.paper,color:P.ink}}><div className="mx-auto max-w-[1480px]"><div className="h-[55vw] max-h-[720px] min-h-[420px]"><I src={d.image_url} fieldPath="image_url" className="h-full w-full"/></div><div className="grid border-x border-b p-7 sm:p-10 lg:grid-cols-[1.2fr_.8fr] lg:gap-16" style={{borderColor:P.line,background:P.white}}><div><T value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.22em]" style={{color:P.ocean}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-4 block text-4xl font-black leading-[.98] tracking-[-.045em] sm:text-6xl"/><T value={d.text} fieldPath="text" area className="mt-6 block leading-8" style={{color:P.muted}}/></div><div className="mt-8 lg:mt-0"><T value={d.price} fieldPath="price" className="block text-3xl font-black" style={{color:P.navy}}/><T value={d.meta} fieldPath="meta" className="mt-3 block text-sm" style={{color:P.muted}}/><B label={d.button_label} url={d.button_url} fieldPath="button_label" className="mt-7 inline-flex min-h-12 items-center px-7 text-sm font-black text-white" style={{background:P.navy}}/></div></div></div></section>}
export const MarketplaceHarborFaqSchema={type:'marketplace_harbor_faq',title:'Harbor & Key Property FAQ',category:'Marketplace',defaults:{eyebrow:'PROPERTY FAQ',heading:'Useful answers before the next move.',items:[{q:'How do you price a property for market?',a:'We combine recent comparable sales, current competing stock, buyer demand, and the property’s specific strengths.'},{q:'When should I arrange finance as a buyer?',a:'Ideally before serious inspections so you understand your range and can move quickly when the right property appears.'},{q:'What happens after an offer is accepted?',a:'We guide the communication around contracts, conditions, timing, and the handover to your legal and finance advisers.'}]},fields:[F('eyebrow'),F('heading'),F('items','repeater')]};
export function MarketplaceHarborFaqBlock({block}){const d={...MarketplaceHarborFaqSchema.defaults,...block};const items=Array.isArray(d.items)?d.items:[];return <section className="px-6 py-24 lg:px-8 lg:py-32" style={{background:P.sand,color:P.ink}}><div className="mx-auto max-w-[1200px]"><T value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.22em]" style={{color:P.ocean}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-5 block text-4xl font-black leading-[.98] tracking-[-.045em] sm:text-6xl"/><div className="mt-10 border-t" style={{borderColor:P.line}}>{items.slice(0,8).map((it,i)=><div key={i} className="grid gap-5 border-b py-7 md:grid-cols-[.8fr_1.2fr]" style={{borderColor:P.line}}><T value={it.q} fieldPath={`items.${i}.q`} className="font-black"/><T value={it.a} fieldPath={`items.${i}.a`} area className="text-sm leading-7" style={{color:P.muted}}/></div>)}</div></div></section>}
export const MarketplaceHarborContactSchema={type:'marketplace_harbor_contact',title:'Harbor & Key Appraisal & Viewing Contact',category:'Marketplace',defaults:{eyebrow:'START A PROPERTY CONVERSATION',heading:'Buying, selling, or simply planning ahead?',text:'Tell us what you are considering and we will point you toward the most useful next step.',phone:'(02) 5550 0472',email:'hello@harborandkey.example',address:'8 Marina Walk · Neutral Bay NSW',button_label:'Request an Appraisal',button_url:'mailto:hello@harborandkey.example',image_url:'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=1500&q=88'},fields:[F('eyebrow'),F('heading'),F('text','textarea'),F('phone'),F('email'),F('address'),F('button_label'),F('button_url'),F('image_url','image')]};
export function MarketplaceHarborContactBlock({block}){const d={...MarketplaceHarborContactSchema.defaults,...block};return <section className="px-6 py-24 lg:px-8 lg:py-32" style={{background:P.white,color:P.ink}}><div className="mx-auto grid max-w-[1420px] overflow-hidden lg:grid-cols-2" style={{background:P.navy,color:P.white}}><div className="p-8 sm:p-12 lg:p-16"><T value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.22em]" style={{color:P.sage}}/><T value={d.heading} fieldPath="heading" cosmicType="h2" className="mt-5 block text-4xl font-black leading-[.98] tracking-[-.045em] sm:text-6xl"/><T value={d.text} fieldPath="text" area className="mt-6 block leading-8 text-white/70"/><div className="mt-7 text-sm leading-7"><T value={d.phone} fieldPath="phone" className="font-black"/><br/><T value={d.email} fieldPath="email"/><br/><T value={d.address} fieldPath="address"/></div><B label={d.button_label} url={d.button_url} fieldPath="button_label" className="mt-8 inline-flex min-h-12 items-center px-7 text-sm font-black" style={{background:P.sage,color:P.navy}}/></div><div className="min-h-[460px]"><I src={d.image_url} fieldPath="image_url" className="h-full w-full"/></div></div></section>}
export const MarketplaceHarborPageHeroSchema={type:'marketplace_harbor_page_hero',title:'Harbor & Key Inner Page Hero',category:'Marketplace',defaults:{eyebrow:'HARBOR & KEY',heading:'Property advice shaped by local context.',text:'Clear guidance, standout presentation, and a thoughtful next step.'},fields:[F('eyebrow'),F('heading'),F('text','textarea')]};
export function MarketplaceHarborPageHeroBlock({block}){const d={...MarketplaceHarborPageHeroSchema.defaults,...block};return <section className="px-6 py-24 lg:px-8 lg:py-32" style={{background:P.navy,color:P.white}}><div className="mx-auto max-w-[1420px]"><T value={d.eyebrow} fieldPath="eyebrow" className="text-[10px] font-black uppercase tracking-[.22em]" style={{color:P.sage}}/><T value={d.heading} fieldPath="heading" cosmicType="h1" className="mt-6 block max-w-6xl text-[clamp(3.6rem,7vw,7.6rem)] font-black leading-[.88] tracking-[-.06em]"/><T value={d.text} fieldPath="text" area className="mt-7 block max-w-2xl text-lg leading-8 text-white/65"/></div></section>}
