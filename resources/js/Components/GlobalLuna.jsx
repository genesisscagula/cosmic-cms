import axios from 'axios';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useCreditBalance } from '@/Hooks/useCreditBalance';

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

export default function GlobalLuna({initialPage=null,authenticated=false}){
    const {balance,setBalance}=useCreditBalance();
    const initialAuthenticated=useMemo(()=>inferAuthenticated(initialPage,authenticated),[]);
    const initial=useMemo(()=>readSession(),[]);
    const [open,setOpen]=useState(initial.open);
    const [messages,setMessages]=useState(initial.messages);
    const [input,setInput]=useState('');
    const [busy,setBusy]=useState(false);
    const [status,setStatus]=useState('Ready');
    const endRef=useRef(null);
    const inputRef=useRef(null);

    const [routeContext,setRouteContext]=useState(()=>({
        component:String(initialPage?.component||''),
        url:String(initialPage?.url||window.location.pathname),
        authenticated:initialAuthenticated,
    }));
    const component=routeContext.component;
    const currentUrl=routeContext.url;
    const effectiveAuthenticated=Boolean(routeContext.authenticated);
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
        try{
            sessionStorage.setItem(STORAGE_KEY,JSON.stringify({open,messages:messages.slice(-20)}));
        }catch{}
    },[open,messages]);

    useEffect(()=>{
        if(!open)return;
        const frame=requestAnimationFrame(()=>endRef.current?.scrollIntoView({behavior:'smooth',block:'end'}));
        return ()=>cancelAnimationFrame(frame);
    },[open,messages,busy,status]);

    const generatePublicTrial=async(message)=>{
        const stages=[
            'Thinking…',
            'Planning…',
            'Designing…',
            'Building…',
            'Checking…',
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
            setStatus('Website ready — opening Builder…');
            setMessages(current=>[...current,{role:'assistant',text:'Your website is ready — opening the Builder now.'}]);

            let navigationUrl=String(builderUrl||'').trim();
            try{
                const parsed=new URL(navigationUrl,window.location.origin);
                navigationUrl=`${parsed.pathname}${parsed.search}${parsed.hash}`;
            }catch{}
            if(!navigationUrl.startsWith('/')){
                navigationUrl=pageId&&trialToken
                    ? `/pages/${pageId}/builder?token=${encodeURIComponent(trialToken)}`
                    : navigationUrl;
            }
            window.setTimeout(()=>{
                window.location.assign(navigationUrl);
            },650);
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

    const send=async(directMessage=null,confirmationToken=null)=>{
        const safeDirectMessage=typeof directMessage==='string' ? directMessage : null;
        const safeConfirmationToken=typeof confirmationToken==='string' ? confirmationToken : null;
        const message=String(safeDirectMessage ?? input).trim();
        if(!message||busy)return;
        if(safeDirectMessage===null)setInput('');
        setBusy(true);
        setStatus('Thinking…');
        if(!safeConfirmationToken)setMessages(current=>[...current,{role:'user',text:message}]);

        const buildIntent=/\b(build|create|generate|design|make)\b.{0,100}\b(website|site|homepage|home page|landing page|page)\b/i.test(message);
        const updateIntent=/\b(change|update|edit|rewrite|replace|redesign|rebrand|adjust|increase|decrease|add|remove)\b/i.test(message);
        const publishIntent=/\b(publish|go live|make .* live)\b/i.test(message);
        const navigateIntent=/\b(open|go to|take me to|navigate to)\b/i.test(message);
        const actionIntent=buildIntent||updateIntent||publishIntent||navigateIntent;
        const phases=buildIntent
            ? ['Thinking…','Planning…','Designing…','Building…','Checking…']
            : updateIntent
                ? ['Thinking…','Planning…','Designing…','Building…','Checking…']
                : publishIntent
                    ? ['Thinking…','Planning…','Building…','Checking…']
                    : navigateIntent
                        ? ['Thinking…','Checking…']
                        : ['Thinking…'];
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
            const trialNotice=(data.mode==='start_trial' || data.start_trial===true) ? ' This may take a moment while I build and QA your website. Please keep this tab open — I’ll take you to the Builder as soon as it’s ready.' : '';
            const reply=`${baseReply}${trialNotice}${cost>0?` · ${cost} credit${cost===1?'':'s'}`:''}`;
            setMessages(current=>[...current,{
                role:'assistant',
                text:reply,
                options:Array.isArray(data.options)?data.options:[],
                confirmation:data.mode==='confirm'?{
                    token:data.confirmation_token,
                    confirmLabel:data.confirm_label||'Confirm',
                    cancelLabel:data.cancel_label||'Cancel',
                    originalMessage:message,
                }:null,
            }]);
            setStatus(data.status_label||'Ready');

            if(data.mode==='start_trial' || data.start_trial===true){
                if (timer) window.clearInterval(timer);
                // The AI acknowledgement above is rendered first. Generation then
                // continues inside this same chat instead of opening the old /start loader.
                await new Promise(resolve=>window.setTimeout(resolve,420));
                await generatePublicTrial(String(data.generation_prompt || message));
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
            setBusy(false);
        }
    };

    const clearConversation=()=>{
        setMessages([]);
        setInput('');
        setStatus('Ready');
        try{sessionStorage.removeItem(STORAGE_KEY);}catch{}
    };

    if(builderOwned)return null;

    return <>
        {!open&&<button
            type="button"
            onClick={()=>setOpen(true)}
            className="fixed bottom-6 right-6 z-[980] inline-flex h-14 items-center gap-2 rounded-full border border-violet-300/30 bg-gradient-to-r from-violet-600 to-indigo-600 px-5 text-sm font-black text-white shadow-2xl shadow-violet-950/30 transition hover:-translate-y-0.5"
            title="Ask Luna"
        ><span className="text-lg">✦</span> Ask Luna</button>}

        {open&&<section className="fixed bottom-6 right-6 z-[990] flex max-h-[72vh] w-[420px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-violet-300/20 bg-[#111318]/95 text-white shadow-2xl backdrop-blur-xl">
            <header className="flex items-start justify-between border-b border-white/10 px-4 py-3">
                <div className="min-w-0">
                    <p className="text-[10px] font-black uppercase tracking-[.18em] text-violet-300">✦ Luna · {areaLabel}</p>
                    <p className="mt-1 truncate text-xs text-slate-400">{effectiveAuthenticated ? <>Help & navigation · ⚡ {Number(balance||0).toLocaleString()}</> : 'Luna preview · Explore Cosmic CMS'}</p>
                </div>
                <div className="flex items-center gap-1">
                    <button type="button" onClick={clearConversation} disabled={busy||messages.length===0} title="Clear conversation" className="rounded-lg px-2 py-1.5 text-[10px] font-semibold text-slate-400 hover:bg-white/10 hover:text-white disabled:cursor-not-allowed disabled:opacity-30">Clear</button>
                    <button type="button" onClick={()=>setOpen(false)} className="h-8 w-8 rounded-lg text-slate-400 hover:bg-white/10 hover:text-white">×</button>
                </div>
            </header>

            <div className="border-b border-white/5 px-4 py-3 text-xs leading-5 text-slate-400">
                {effectiveAuthenticated
                    ? 'Outside the Builder, Luna can answer questions, navigate, and handle safe workspace actions like creating or renaming pages.'
                    : 'Ask Luna about Cosmic CMS, plans, or start building when you are ready.'}
            </div>

            <div className="min-h-28 flex-1 space-y-2 overflow-y-auto px-4 py-3">
                {messages.length?messages.slice(-20).map((message,index)=><div key={`${index}-${lunaText(message.text,'')}`} className={`flex ${message.role==='user'?'justify-end':'justify-start'}`}>
                    <div className="max-w-[86%]">
                        <div className={`rounded-2xl px-3 py-2 text-xs leading-5 ${message.role==='user'?'bg-violet-500 text-white':'border border-white/10 bg-white/[0.05] text-slate-200'}`}>{lunaText(message.text,'')}</div>
                        {message.role==='assistant'&&Array.isArray(message.options)&&message.options.length>0?<div className="mt-2 flex flex-wrap gap-1.5">{message.options.map((option,optionIndex)=><button key={`${optionIndex}-${option.label}`} type="button" onClick={()=>option.url?router.visit(option.url):(option.send_message?send(option.send_message):null)} className="rounded-lg border border-violet-300/20 bg-violet-400/10 px-2.5 py-1.5 text-[11px] font-semibold text-violet-200 transition hover:bg-violet-400/20">{option.label}</button>)}</div>:null}
                        {message.role==='assistant'&&message.confirmation?<div className="mt-2 flex gap-2"><button type="button" disabled={busy} onClick={()=>send(message.confirmation.originalMessage,message.confirmation.token)} className="rounded-lg bg-rose-500 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-rose-400 disabled:opacity-40">{message.confirmation.confirmLabel}</button><button type="button" disabled={busy} onClick={()=>setMessages(current=>current.map(item=>item===message?{...item,confirmation:null}:item))} className="rounded-lg border border-white/10 px-3 py-1.5 text-[11px] font-semibold text-slate-300 hover:bg-white/5 disabled:opacity-40">{message.confirmation.cancelLabel}</button></div>:null}
                    </div>
                </div>):<div className="rounded-xl border border-violet-300/10 bg-violet-400/[0.05] px-3 py-2 text-xs leading-5 text-slate-400">
                    {effectiveAuthenticated
                        ? 'Try “Create an About Us page in Cosmic React”, “Open Orders for my store”, or “Rename Contact to Get a Quote”.'
                        : 'Try “What can Luna do?”, “Show me the plans”, or “Start building a website”.'}
                </div>}
                {busy&&<div className="flex items-center gap-2 text-xs font-semibold text-violet-300" role="status" aria-live="polite"><span className="h-3.5 w-3.5 shrink-0 animate-spin rounded-full border-2 border-violet-300/25 border-t-violet-300" aria-hidden="true"/><span>{status}</span></div>}
                <div ref={endRef} className="h-px" aria-hidden="true"/>
            </div>

            <div className="border-t border-white/10 p-4">
                <textarea
                    ref={inputRef}
                    value={input}
                    onChange={event=>setInput(event.target.value)}
                    onKeyDown={event=>{if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();send();}}}
                    rows={3}
                    placeholder="Ask Luna…"
                    className="w-full resize-none rounded-xl border border-white/10 bg-black/25 px-3 py-3 text-sm leading-6 text-white outline-none placeholder:text-slate-600 focus:border-violet-300/40"
                />
                <div className="mt-3 flex items-center justify-between">
                    <span className="text-[10px] text-slate-600">{effectiveAuthenticated ? 'Luna requests use credits' : 'Sign in or start a build for full Luna access'}</span>
                    <button type="button" onClick={()=>send()} disabled={busy||!input.trim()} className="rounded-lg bg-violet-500 px-4 py-2 text-xs font-bold text-white hover:bg-violet-400 disabled:opacity-40">{busy?'Working…':'Send'}</button>
                </div>
            </div>
        </section>}
    </>;
}
