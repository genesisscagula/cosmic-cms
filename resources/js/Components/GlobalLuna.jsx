import axios from 'axios';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useCreditBalance } from '@/Hooks/useCreditBalance';
import { handoffToBuilder, normalizeBuilderUrl } from '@/Support/builderHandoff';
import { useAppearance } from '@/Appearance/AppearanceContext';
import CosmicLoadingIcon from './CosmicLoadingIcon';
import LunaProcessCard from './Luna/LunaProcessCard';

const lunaText = (value, fallback = '') => {
    if (typeof value === 'string') return value;
    if (typeof value === 'number' || typeof value === 'boolean') return String(value);
    if (value && typeof value === 'object') {
        for (const key of ['reply','message','text','content','label','title']) {
            if (typeof value[key] === 'string' && value[key].trim()) return value[key];
        }
    }
    return fallback;
};

const STORAGE_KEY='cosmic-global-luna-chat-v2';

const publicComponents = [
    'Welcome','Pricing','Start',
    'Auth/Login','Auth/Register','Auth/ForgotPassword','Auth/ResetPassword','Auth/VerifyEmail','Auth/ConfirmPassword'
];

function inferAuthenticated(page, explicit=false){
    if (explicit || Boolean(page?.props?.auth?.user)) return true;
    const component=String(page?.component||'');
    if (!component) return false;
    if (publicComponents.includes(component)) return false;
    // Any other Inertia screen in the CMS is an authenticated workspace screen.
    return true;
}

function readSession(){
    try{
        const value=JSON.parse(sessionStorage.getItem(STORAGE_KEY)||'{}');
        return {
            open:Boolean(value.open),
            messages:Array.isArray(value.messages)?value.messages.slice(-20):[],
        };
    }catch{
        return {open:false,messages:[]};
    }
}

const starterTerminalStatuses=new Set(['ready','partial','failed']);
const starterBuildingStatuses=new Set(['queued','building']);
const starterOptions=(site)=>[
    ...(site?.preview_url?[{label:'Preview website ↗',url:site.preview_url}]:[]),
    ...(Array.isArray(site?.pages)?site.pages:[])
        .filter(page=>page?.builder_url&&['ready','preserved'].includes(page?.build_status))
        .slice(0,4)
        .map(page=>({label:`Open ${page.title}`,url:page.builder_url})),
];

export default function GlobalLuna({initialPage=null,authenticated=false}){
    const {balance,setBalance}=useCreditBalance();
    const {resolvedTheme: appAppearanceTheme}=useAppearance();
    const appDark=appAppearanceTheme==='dark';
    const initialAuthenticated=useMemo(()=>inferAuthenticated(initialPage,authenticated),[]);
    const initial=useMemo(()=>readSession(),[]);
    const [open,setOpen]=useState(initial.open);
    const [messages,setMessages]=useState(initial.messages);
    const [input,setInput]=useState('');
    const [busy,setBusy]=useState(false);
    const [status,setStatus]=useState('Ready');
    const [processMode,setProcessMode]=useState(false);
    const [processSteps,setProcessSteps]=useState([]);
    const [starterSiteContext,setStarterSiteContext]=useState(null);
    const endRef=useRef(null);
    const inputRef=useRef(null);
    const completedStarterBuild=useRef(null);

    const [routeContext,setRouteContext]=useState(()=>({
        component:String(initialPage?.component||''),
        url:String(initialPage?.url||window.location.pathname),
        authenticated:initialAuthenticated,
    }));
    const component=routeContext.component;
    const currentUrl=routeContext.url;
    const effectiveAuthenticated=Boolean(routeContext.authenticated);
    const welcomeMode=component==='Welcome'&&!effectiveAuthenticated&&!starterSiteContext;
    const areaLabel=component.split('/').filter(Boolean).pop()?.replace(/([a-z])([A-Z])/g,'$1 $2')||'Cosmic CMS';

    const builderOwned=/Websites\/Builder$/i.test(component);

    useEffect(()=>{
        const remove=router.on('success',(event)=>{
            const next=event?.detail?.page||{};
            const nextAuthenticated=inferAuthenticated(next,false);
            setRouteContext({
                component:String(next.component||''),
                url:String(next.url||window.location.pathname),
                authenticated:nextAuthenticated,
            });
        });
        return remove;
    },[]);

    useEffect(()=>{
        const openBuild=()=>{
            setStarterSiteContext(null);
            setOpen(true);
            setInput('');
            setStatus('Ready');
            setMessages(current=>{
                const last=current[current.length-1];
                const text='Tell me what you want to build — the business, style, and anything the website should include.';
                if(last?.role==='assistant'&&last?.text===text)return current;
                return [...current,{role:'assistant',text}];
            });
            window.requestAnimationFrame(()=>inputRef.current?.focus?.());
        };
        window.addEventListener('cosmic:luna-open-build',openBuild);
        return ()=>window.removeEventListener('cosmic:luna-open-build',openBuild);
    },[]);

    useEffect(()=>{
        const openStarterSite=(event)=>{
            const detail=event?.detail||{};
            const websiteId=Number(detail.websiteId||0);
            if(!websiteId)return;
            const websiteName=String(detail.websiteName||'this website');
            const starterSite=detail.starterSite&&typeof detail.starterSite==='object'
                ? detail.starterSite
                : {installed:false,status:'idle',pages:[]};
            const building=starterBuildingStatuses.has(starterSite.status);
            const installed=Boolean(starterSite.installed);
            setStarterSiteContext({websiteId,websiteName,starterSite});
            setOpen(true);
            setInput('');
            setStatus(starterBuildingStatuses.has(starterSite.status)?`Building… ${starterSite.progress||0}%`:'Ready');
            setMessages(current=>{
                const text=building
                    ? `I’m building the ${starterSite.bundle_name||'starter site'} now. I’ll keep the page progress here and share the staging preview when it’s ready.`
                    : installed
                        ? `Your ${starterSite.bundle_name||'starter bundle'} is installed. You can open any page in Builder.`
                        : `Starter Pages uses the industry and business details already saved for ${websiteName}. I’m selecting the best matching bundle now — no extra prompt needed.`;
                const last=current[current.length-1];
                if(last?.role==='assistant'&&last?.text===text)return current;
                return [...current,{
                    role:'assistant',
                    text,
                    starterSiteStatus:Boolean(installed||building),
                    options:installed&&starterTerminalStatuses.has(starterSite.status)?starterOptions(starterSite):[],
                }];
            });
            if(!installed&&!building){
                window.setTimeout(()=>planStarterSite('',websiteId),80);
            }
        };
        window.addEventListener('cosmic:luna-open-starter-site',openStarterSite);
        return ()=>window.removeEventListener('cosmic:luna-open-starter-site',openStarterSite);
    },[]);

    const starterWebsiteId=Number(starterSiteContext?.websiteId||0);
    const starterSiteStatus=String(starterSiteContext?.starterSite?.status||'');
    const starterBuildId=String(starterSiteContext?.starterSite?.build_id||'');

    useEffect(()=>{
        if(!starterWebsiteId||!starterBuildingStatuses.has(starterSiteStatus))return undefined;
        let cancelled=false;
        const poll=async()=>{
            try{
                const {data}=await axios.get(route('starter-sites.status',starterWebsiteId));
                if(cancelled)return;
                setStarterSiteContext(current=>current?{...current,starterSite:data}:current);
                if(Number.isFinite(Number(data.credit_balance)))setBalance(Number(data.credit_balance));
                if(starterBuildingStatuses.has(data.status)){
                    setStatus(`Building… ${Number(data.progress||0)}%`);
                    return;
                }
                setStatus('Ready');
                if(starterTerminalStatuses.has(data.status)&&completedStarterBuild.current!==data.build_id){
                    completedStarterBuild.current=data.build_id;
                    const failed=(data.pages||[]).filter(page=>page.build_status==='failed').length;
                    setMessages(current=>[...current,{
                        role:'assistant',
                        text:data.status==='ready'
                            ? `Your ${data.bundle_name||'starter website'} is ready. Every generated page is published and the staging preview has been refreshed.`
                            : data.status==='partial'
                                ? `The starter website is ready to review, but ${failed} page${failed===1?'':'s'} could not be completed. Failed page credits were refunded.`
                                : 'I could not complete this starter build. Any failed page charges were refunded.',
                        starterSiteStatus:true,
                        options:starterOptions(data),
                    }]);
                    router.reload({only:['website','pages','globalHeaderBlock','starterSite']});
                }
            }catch{
                if(!cancelled)setStatus('Could not refresh build status');
            }
        };
        poll();
        const timer=window.setInterval(poll,2500);
        return ()=>{cancelled=true;window.clearInterval(timer);};
    },[starterWebsiteId,starterSiteStatus,starterBuildId,setBalance]);
useEffect(()=>{
        try{
            sessionStorage.setItem(STORAGE_KEY,JSON.stringify({open,messages:messages.slice(-20)}));
        }catch{}
    },[open,messages]);

    useEffect(()=>{
        if(!open||component!=='Welcome'||effectiveAuthenticated||starterSiteContext||messages.length)return;
        setMessages([{role:'assistant',text:'Hey! What would you like to build? Tell me about your business or ask me anything about Cosmic CMS.'}]);
    },[open,component,effectiveAuthenticated,starterSiteContext,messages.length]);

    useEffect(()=>{
        if(!open)return;
        const frame=requestAnimationFrame(()=>endRef.current?.scrollIntoView({behavior:'smooth',block:'end'}));
        return ()=>cancelAnimationFrame(frame);
    },[open,messages,busy,status]);

    useEffect(()=>{
        if(!open)return undefined;
        const focusFrame=window.requestAnimationFrame(()=>inputRef.current?.focus?.({preventScroll:true}));
        const onKeyDown=(event)=>{
            if(event.key==='Escape'&&!busy)setOpen(false);
        };
        window.addEventListener('keydown',onKeyDown);
        return ()=>{
            window.cancelAnimationFrame(focusFrame);
            window.removeEventListener('keydown',onKeyDown);
        };
    },[open,busy]);

    const generatePublicTrial=async(message)=>{
        const stages=[
            'Understanding your request…',
            'Planning the website…',
            'Designing the page…',
            'Building the website…',
            'Checking everything…',
        ];
        let stageIndex=0;
        setStatus(stages[0]);
        const stageTimer=window.setInterval(()=>{
            stageIndex=Math.min(stageIndex+1,stages.length-1);
            setStatus(stages[stageIndex]);
        },2600);

        try{
            const response=await axios.post('/start',{prompt:message},{
                headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},
            });
            const rawPayload=response?.data ?? response ?? {};
            const payload=rawPayload?.data ?? rawPayload;
            const pageId=payload?.page_id ?? payload?.page?.id;
            const trialToken=payload?.trial_token ?? payload?.token ?? payload?.trial?.token;
            const responseHeaderUrl=response?.headers?.['x-cosmic-builder-url'] || response?.headers?.get?.('x-cosmic-builder-url') || null;
            const builderUrl=payload?.builder_url
                ?? payload?.redirect_url
                ?? payload?.url
                ?? responseHeaderUrl
                ?? (pageId&&trialToken?`/pages/${pageId}/builder?token=${encodeURIComponent(trialToken)}`:null);
            if(!builderUrl){
                throw new Error(pageId&&trialToken
                    ? 'The trial was created but the Builder route could not be resolved.'
                    : 'The Builder URL was not returned.');
            }

            window.clearInterval(stageTimer);
            let navigationUrl=normalizeBuilderUrl(builderUrl);
            if(!navigationUrl.startsWith('/')){
                navigationUrl=pageId&&trialToken
                    ? `/pages/${pageId}/builder?token=${encodeURIComponent(trialToken)}`
                    : navigationUrl;
            }

            setStatus('Website ready — opening Builder…');
            setMessages(current=>[...current,{
                role:'assistant',
                text:'Your website is ready — opening the Builder now.',
                options:[{label:'Open Builder',url:navigationUrl}],
            }]);
            handoffToBuilder(navigationUrl,{router,delay:180});
            return true;
        }catch(error){
            window.clearInterval(stageTimer);
            const messageText=String(
                error?.response?.data?.errors?.prompt?.[0]
                || error?.response?.data?.message
                || error?.message
                || 'I could not finish generating the website. Please try again.'
            );
            setMessages(current=>[...current,{role:'assistant',text:messageText}]);
            setStatus('Ready');
            return false;
        }
    };

    const planStarterSite=async(message='',websiteIdOverride=null)=>{
        try{
            const targetWebsiteId=Number(websiteIdOverride||starterWebsiteId||0);
            if(!targetWebsiteId)return;
            setStatus('Selecting starter bundle…');
            const {data}=await axios.post(route('starter-sites.plan',targetWebsiteId),{prompt:String(message||'').trim()||null});
            if(Number.isFinite(Number(data.credit_balance)))setBalance(Number(data.credit_balance));
            const plan=data.plan||{};
            const balanceNow=Number(data.credit_balance??balance??0);
            const cost=Number(plan.credit_cost||0);
            setMessages(current=>[...current,{
                role:'assistant',
                text:`Starter Pages Ready — I selected ${plan.bundle_name||'the best matching registered bundle'} from this website’s industry and business details. ${plan.page_count||0} matching pages are prepared; ${plan.preserved_page_count||0} existing populated pages will stay untouched.`,
                starterPlan:plan,
                confirmation:{
                    kind:'starter_site_install',
                    originalMessage:'',
                    bundleKey:plan.bundle_key,
                    confirmLabel:Number(plan.build_page_count||0)>0?'Install Starter Pages':'Keep Starter Pages',
                    cancelLabel:'Not now',
                    disabled:cost>balanceNow,
                },
            }]);
            setStatus('Ready');
        }catch(error){
            const raw=String(error?.response?.data?.message||error?.response?.data?.errors?.prompt?.[0]||'I could not prepare the starter-page plan.');
            setMessages(current=>[...current,{role:'assistant',text:raw}]);
            setStatus('Ready');
        }
    };

    const send=async(directMessage=null,confirmationToken=null)=>{
        const safeDirectMessage=typeof directMessage==='string' ? directMessage : null;
        const safeConfirmationToken=typeof confirmationToken==='string' ? confirmationToken : null;
        const message=String(safeDirectMessage ?? input).trim();
        if(!message||busy)return;
        if(safeDirectMessage===null)setInput('');
        setBusy(true);
        setStatus('Understanding your request…');
        if(!safeConfirmationToken)setMessages(current=>[...current,{role:'user',text:message}]);

        if(starterWebsiteId&&!safeConfirmationToken){
            setProcessMode(true);
            setProcessSteps(['Understanding your request','Selecting the starter bundle','Checking page compatibility','Verifying the plan']);
            await planStarterSite(message);
            setProcessMode(false);
            setProcessSteps([]);
            setBusy(false);
            return;
        }

        const buildIntent=/\b(build|rebuild|create|recreate|generate|regenerate|design|redesign|make|start|launch)\b.{0,100}\b(website|site|homepage|home page|landing page|page|experience)\b/i.test(message);
        const updateIntent=/\b(change|update|edit|rewrite|replace|redesign|rebrand|adjust|increase|decrease|add|remove)\b/i.test(message);
        const publishIntent=/\b(publish|go live|make .* live)\b/i.test(message);
        const navigateIntent=/\b(open|go to|take me to|navigate to|show me|view)\b/i.test(message);
        const actionIntent=buildIntent||updateIntent||publishIntent||navigateIntent;
        const phases=buildIntent
            ? ['Understanding your request…','Planning the changes…','Applying the design…','Building the update…','Verifying the result…']
            : updateIntent
                ? ['Understanding your request…','Planning the changes…','Applying the design…','Building the update…','Verifying the result…']
                : publishIntent
                    ? ['Understanding your request…','Planning the changes…','Applying the update…','Verifying the result…']
                    : navigateIntent
                        ? ['Understanding your request…','Verifying the result…']
                        : ['Understanding your request…'];
        setProcessMode(actionIntent);
        setProcessSteps(actionIntent ? phases.map((phase)=>String(phase).replace(/…+$/,'').trim()) : []);
        let phaseIndex=0;
        const timer=actionIntent ? window.setInterval(()=>{
            phaseIndex=Math.min(phaseIndex+1,phases.length-1);
            setStatus(phases[phaseIndex]);
        },1800) : null;

        try{
            const endpoint = effectiveAuthenticated ? route('luna.global.chat') : route('luna.public.chat');
            const conversation = messages.slice(-12).map(item=>({
                role:item.role==='user'?'user':'assistant',
                content:String(item.text||'').slice(0,1600),
            }));
            const payload = {
                message,
                context:{component,url:currentUrl,conversation},
                ...(effectiveAuthenticated && safeConfirmationToken ? {confirmation_token:safeConfirmationToken} : {}),
            };
            const {data}=await axios.post(endpoint,payload);
            if(Number.isFinite(Number(data.credit_balance)))setBalance(Number(data.credit_balance));
            const cost=Number(data.credit_cost||0);
            const baseReply=lunaText(data.reply,'I’m working on that now.');
            const startingTrial=(data.mode==='start_trial' || data.start_trial===true);
            const welcomeDemoHandoff=startingTrial && component==='Welcome' && !effectiveAuthenticated;
            const trialNotice=startingTrial
                ? (welcomeDemoHandoff
                    ? ' I opened the free demo details for you. Add your website name, industry, and email — I kept your request as the additional instructions.'
                    : ' This may take a moment while I build and QA your website. Please keep this tab open — I’ll take you to the Builder as soon as it’s ready.')
                : '';
            const reply=`${baseReply}${trialNotice}${cost>0?` · ${cost} credit${cost===1?'':'s'}`:''}`;
            setMessages(current=>[...current,{
                role:'assistant',
                text:reply,
                options:Array.isArray(data.options)?data.options:[],
                confirmation:data.mode==='confirm'?{
                    token:data.confirmation_token,
                    confirmLabel:data.confirm_label||'Confirm',
                    cancelLabel:data.cancel_label||'Cancel',
                    originalMessage:'',
                }:null,
            }]);
            setStatus(data.status_label||'Ready');

            if(startingTrial){
                if (timer) window.clearInterval(timer);
                const generationPrompt=String(data.generation_prompt || message).trim();
                if(welcomeDemoHandoff){
                    setStatus('Complete your free demo details');
                    window.dispatchEvent(new CustomEvent('cosmic:open-free-demo', {
                        detail:{
                            source:'luna_welcome',
                            prompt:generationPrompt,
                        },
                    }));
                }else{
                    // Preserve the existing prompt-only public trial path outside Welcome
                    // until those surfaces are intentionally migrated to the guided demo flow.
                    await new Promise(resolve=>window.setTimeout(resolve,420));
                    await generatePublicTrial(generationPrompt);
                }
            } else if(data.mode==='navigate'&&data.navigate_action==='back'){
                window.setTimeout(()=>window.history.back(),500);
            } else if(data.mode==='navigate'&&data.navigate_url){
                window.setTimeout(()=>router.visit(data.navigate_url),650);
            }
        }catch(error){
            const raw=String(error?.response?.data?.message||error?.response?.data?.errors?.message?.[0]||error?.message||'Request failed');
            let explanation='';
            if(effectiveAuthenticated){
                try{
                    const conversation=messages.slice(-12).map(item=>({role:item.role==='user'?'user':'assistant',content:String(item.text||'').slice(0,1600)}));
                    const {data}=await axios.post(route('luna.global.explain-error'),{
                        message,
                        error:raw.slice(0,2000),
                        context:{component,url:currentUrl,conversation},
                    });
                    explanation=String(data?.reply||'');
                }catch{}
            }
            if(!explanation){
                explanation=/curl|network|timeout|timed out|resolve|connection/i.test(raw)
                    ? 'Luna could not reach the service needed for that request.'
                    : raw;
            }
            setMessages(current=>[...current,{role:'assistant',text:explanation}]);
        }finally{
            if (timer) window.clearInterval(timer);
            setProcessMode(false);
            setProcessSteps([]);
            setBusy(false);
        }
    };

    const installStarterSite=async(confirmation,messageRecord)=>{
        if(!confirmation||busy||confirmation.disabled)return;
        setBusy(true);
        setProcessMode(true);
        setProcessSteps(['Confirming the starter plan','Preparing the pages','Starting page generation','Verifying the build']);
        setStatus('Starting starter-page build…');
        setMessages(current=>current.map(item=>item===messageRecord?{...item,confirmation:null}:item));
        try{
            const {data}=await axios.post(route('starter-sites.install',starterWebsiteId),{
                bundle_key:confirmation.bundleKey,
                confirmed:true,
            });
            const site=data.starter_site||{};
            setStarterSiteContext(current=>current?{...current,starterSite:site}:current);
            if(Number.isFinite(Number(site.credit_balance)))setBalance(Number(site.credit_balance));
            setMessages(current=>[...current,{
                role:'assistant',
                text:site.status==='ready'
                    ? 'Your matching starter pages are ready.'
                    : 'I’ve started the build. Existing populated pages stay untouched; new pages are generated, verified, published, and added to the staging preview.',
                starterSiteStatus:true,
                options:site.status==='ready'?starterOptions(site):[],
            }]);
            setStatus(starterBuildingStatuses.has(site.status)?`Building… ${site.progress||0}%`:'Ready');
            router.reload({only:['website','pages','globalHeaderBlock','starterSite']});
        }catch(error){
            const raw=String(error?.response?.data?.message||Object.values(error?.response?.data?.errors||{})?.flat?.()?.[0]||'I could not start the starter-page build.');
            setMessages(current=>[...current,{role:'assistant',text:raw}]);
            setStatus('Ready');
        }finally{
            setProcessMode(false);
            setProcessSteps([]);
            setBusy(false);
        }
    };

    const clearConversation=()=>{
        setMessages([]);
        setInput('');
        setStatus('Ready');
        setProcessMode(false);
        setProcessSteps([]);
        try{sessionStorage.removeItem(STORAGE_KEY);}catch{}
    };

    const visibleMessages=messages.slice(-20);
    const contextLabel=starterSiteContext?'Starter Site':welcomeMode?'Welcome':areaLabel;
    const panelTitle=starterSiteContext?'Starter Pages':welcomeMode?'Website Assistant':effectiveAuthenticated?'Workspace Assistant':'Cosmic CMS Assistant';
    const panelMeta=starterSiteContext
        ? `Website context · ${Number(balance||0).toLocaleString()} credits`
        : effectiveAuthenticated
            ? `Help & navigation · ${Number(balance||0).toLocaleString()} credits`
            : welcomeMode
                ? 'Build, explore, or ask a question'
                : 'Explore Cosmic CMS with Luna';

    if(builderOwned)return null;

    return <>
        {!open&&<button
            type="button"
            onClick={()=>{setStarterSiteContext(null);setOpen(true);}}
            className="cosmic-global-luna-launcher cosmic-native-text-layer"
            title="Ask Luna"
            aria-label="Open Luna chat"
        >Ask Luna</button>}

        {open&&<section
            className={`cosmic-native-text-layer cosmic-luna-premium cosmic-global-luna-premium ${welcomeMode?'is-welcome':''}`}
            data-appearance={appDark?'dark':'light'}
            role="dialog"
            aria-label="Luna chat"
            aria-modal="false"
        >
            <header className="cosmic-luna-premium__header">
                <div className="min-w-0">
                    <p className="cosmic-luna-premium__eyebrow">Luna · {contextLabel}</p>
                    <h3 className="font-semibold cosmic-luna-premium__title">{panelTitle}</h3>
                    <p className="cosmic-global-luna-premium__meta">{panelMeta}</p>
                </div>
                <div className="cosmic-luna-premium__header-actions">
                    <button
                        type="button"
                        onClick={clearConversation}
                        disabled={busy||messages.length===0}
                        title="Clear conversation"
                        className="cosmic-luna-premium__header-action"
                    >Clear</button>
                    <button type="button" onClick={()=>setOpen(false)} className="cosmic-luna-premium__close" aria-label="Close Luna">×</button>
                </div>
            </header>

            <div className="cosmic-luna-premium__context">
                <div className="cosmic-luna-premium__context-label"><span aria-hidden="true"/>Context · {contextLabel}</div>
                <div className="cosmic-luna-premium__active"><span aria-hidden="true"/>AI Active</div>
            </div>

            <div className="cosmic-luna-premium__chat">
                {visibleMessages.length?visibleMessages.map((message,index)=><div key={`${index}-${lunaText(message.text,'')}`} className={`cosmic-luna-premium__message-row ${message.role==='user'?'cosmic-luna-premium__message-row--user':'cosmic-luna-premium__message-row--assistant'}`}>
                    <div className="cosmic-luna-premium__message-wrap">
                        <div className={`cosmic-luna-premium__bubble ${message.role==='user'?'cosmic-luna-premium__bubble--user':'cosmic-luna-premium__bubble--assistant'}`}>{lunaText(message.text,'')}</div>

                        {message.role==='assistant'&&message.starterPlan?<div className="cosmic-global-luna-plan-card">
                            <div className="cosmic-global-luna-plan-card__head">
                                <div className="min-w-0">
                                    <p className="cosmic-global-luna-plan-card__eyebrow">Registered bundle</p>
                                    <p className="cosmic-global-luna-plan-card__title">{message.starterPlan.bundle_name}</p>
                                </div>
                                <p className="cosmic-global-luna-plan-card__cost">{Number(message.starterPlan.credit_cost||0).toLocaleString()} credits</p>
                            </div>
                            <div className="cosmic-global-luna-plan-card__pages">{(message.starterPlan.pages||[]).map(page=><div key={`${page.slug}-${page.template_key}`} className="cosmic-global-luna-plan-card__page"><p>{page.title}</p><span>{page.install_action}</span></div>)}</div>
                            <p className="cosmic-global-luna-plan-card__note">Only empty or new pages are charged. Existing populated pages are preserved, and failed page builds are refunded.</p>
                            {message.confirmation?.disabled?<p className="cosmic-global-luna-plan-card__error">Not enough credits for this plan.</p>:null}
                        </div>:null}

                        {message.role==='assistant'&&message.starterSiteStatus&&index===visibleMessages.length-1&&starterSiteContext?.starterSite?<div className="cosmic-global-luna-status-card">
                            <div className="cosmic-global-luna-status-card__head">
                                <p>{starterSiteContext.starterSite.bundle_name||'Starter website'}</p>
                                <span data-status={starterSiteContext.starterSite.status}>{starterSiteContext.starterSite.status}</span>
                            </div>
                            {starterBuildingStatuses.has(starterSiteContext.starterSite.status)?<div className="cosmic-global-luna-status-card__track"><div className="cosmic-global-luna-status-card__fill" style={{width:`${Math.max(4,Number(starterSiteContext.starterSite.progress||0))}%`}}/></div>:null}
                            <div className="cosmic-global-luna-status-card__pages">{(starterSiteContext.starterSite.pages||[]).map(page=><div key={page.page_id||page.slug}><span>{page.title}</span><strong data-status={page.build_status}>{page.build_status}</strong></div>)}</div>
                        </div>:null}

                        {message.role==='assistant'&&Array.isArray(message.options)&&message.options.length>0?<div className="cosmic-luna-premium__message-actions">{message.options.map((option,optionIndex)=><button key={`${optionIndex}-${option.label}`} type="button" disabled={busy} onClick={()=>option.url?router.visit(option.url):(option.send_message?send(option.send_message):null)} className="cosmic-luna-premium__action">{option.label}</button>)}</div>:null}

                        {message.role==='assistant'&&message.confirmation?<div className="cosmic-luna-premium__message-actions">
                            <button type="button" disabled={busy||message.confirmation.disabled} onClick={()=>message.confirmation.kind==='starter_site_install'?installStarterSite(message.confirmation,message):send(message.confirmation.originalMessage,message.confirmation.token)} className={`cosmic-luna-premium__action cosmic-luna-premium__action--primary ${message.confirmation.kind==='starter_site_install'?'cosmic-global-luna-action--install':'cosmic-global-luna-action--confirm'}`}>{message.confirmation.confirmLabel}</button>
                            <button type="button" disabled={busy} onClick={()=>setMessages(current=>current.map(item=>item===message?{...item,confirmation:null}:item))} className="cosmic-luna-premium__action">{message.confirmation.cancelLabel}</button>
                        </div>:null}
                    </div>
                </div>):<div className="cosmic-luna-premium__empty">
                    <strong>{effectiveAuthenticated?'What would you like to do?':'Hey! What would you like to build?'}</strong>
                    <span>{effectiveAuthenticated?'Ask Luna a question, open a workspace area, or request a safe action.':'Ask about Cosmic CMS, compare plans, or describe the website you want.'}</span>
                </div>}

                {busy&&(processMode
                    ? <div className="cosmic-luna-premium__message-row cosmic-luna-premium__message-row--assistant"><LunaProcessCard status={status} steps={processSteps} dark={appDark}/></div>
                    : <div className="cosmic-luna-premium__thinking" role="status" aria-live="polite"><span aria-hidden="true"/>{status}</div>)}
                <div ref={endRef} className="h-px" aria-hidden="true"/>
            </div>

            {!starterSiteContext&&welcomeMode?<div className="cosmic-luna-premium__quick-actions" aria-label="Luna suggestions">
                {[
                    ['Create a free demo','Build me a website'],
                    ['Show me the plans','Show me the plans'],
                    ['What can Luna do?','What can Luna do?'],
                ].map(([label,message])=><button
                    key={label}
                    type="button"
                    disabled={busy}
                    onClick={()=>{
                        if(label==='Create a free demo'){
                            setStatus('Complete your free demo details');
                            window.dispatchEvent(new CustomEvent('cosmic:open-free-demo',{
                                detail:{source:'luna_welcome_quick_action',prompt:''},
                            }));
                            return;
                        }
                        send(message);
                    }}
                >{label}</button>)}
            </div>:null}

            {starterSiteContext
                ? <div className="cosmic-global-luna-premium__starter-note">Bundle selection uses your saved website context. AI page generation starts only after you choose <strong>Install Starter Pages</strong>.</div>
                : <div className="cosmic-luna-premium__composer">
                    <textarea
                        ref={inputRef}
                        value={input}
                        onChange={event=>setInput(event.target.value)}
                        onKeyDown={event=>{if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();send();}}}
                        rows={3}
                        placeholder={welcomeMode?'Ask Luna anything…':'Ask Luna…'}
                        className="cosmic-luna-premium__textarea"
                        aria-label="Message Luna"
                    />
                    <div className="cosmic-luna-premium__footer">
                        <span className="cosmic-global-luna-premium__composer-note">{effectiveAuthenticated?'Luna requests use credits':welcomeMode?'No credit card required to create a demo':'Sign in or start a build for full Luna access'}</span>
                        <button type="button" onClick={()=>send()} disabled={busy||!input.trim()} className="cosmic-luna-premium__send">{busy?<><CosmicLoadingIcon className="h-4 w-4"/>Working…</>:<>Send <span aria-hidden="true">→</span></>}</button>
                    </div>
                </div>}
        </section>}
    </>;
}
