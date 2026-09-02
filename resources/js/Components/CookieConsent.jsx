import { useEffect, useState } from 'react';
import axios from 'axios';

const VERSION = '2026-08-05';
const KEY = `cosmic-cookie-consent:${VERSION}`;

export default function CookieConsent() {
  const embeddedMarketplaceDemo = typeof window !== 'undefined'
    && new URLSearchParams(window.location.search).get('embed') === '1'
    && /\/templates\/[^/]+\/[^/]+\/demo(?:\/|$)/.test(window.location.pathname);
  const [open, setOpen] = useState(false);
  const [customize, setCustomize] = useState(false);
  const [analytics, setAnalytics] = useState(false);
  const [marketing, setMarketing] = useState(false);

  useEffect(() => { setOpen(!localStorage.getItem(KEY)); }, []);

  const save = async (a, m) => {
    const choice = { necessary: true, analytics: a, marketing: m, version: VERSION, recorded_at: new Date().toISOString() };
    localStorage.setItem(KEY, JSON.stringify(choice));
    setOpen(false);
    try { await axios.post('/legal/consent', choice); } catch (_) { /* Browser choice remains authoritative for optional scripts. */ }
    window.dispatchEvent(new CustomEvent('cosmic:consent-changed', { detail: choice }));
  };

  if (!open || embeddedMarketplaceDemo) return null;
  return <div className="fixed inset-x-4 bottom-4 z-[300] mx-auto max-w-3xl rounded-2xl border border-white/15 bg-slate-950/95 p-5 text-slate-200 shadow-2xl backdrop-blur"><div className="flex flex-col gap-4 sm:flex-row sm:items-start"><div className="flex-1"><h2 className="font-semibold text-white">Your privacy choices</h2><p className="mt-1 text-sm leading-6 text-slate-400">Necessary storage keeps Cosmic CMS secure and working. Optional analytics and marketing stay off unless you allow them.</p><div className="mt-2 flex gap-4 text-xs"><a href="/privacy" className="text-violet-300">Privacy</a><a href="/cookies" className="text-violet-300">Cookies</a></div>{customize && <div className="mt-4 grid gap-2 text-sm"><label className="flex items-center justify-between rounded-lg bg-white/5 px-3 py-2"><span>Necessary <small className="text-slate-500">Always on</small></span><input type="checkbox" checked readOnly /></label><label className="flex items-center justify-between rounded-lg bg-white/5 px-3 py-2"><span>Analytics</span><input type="checkbox" checked={analytics} onChange={e => setAnalytics(e.target.checked)} /></label><label className="flex items-center justify-between rounded-lg bg-white/5 px-3 py-2"><span>Marketing</span><input type="checkbox" checked={marketing} onChange={e => setMarketing(e.target.checked)} /></label></div>}</div><div className="flex flex-wrap gap-2 sm:w-48 sm:flex-col"><button onClick={() => save(true, true)} className="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-950">Accept all</button><button onClick={() => save(false, false)} className="rounded-lg border border-white/15 px-3 py-2 text-sm font-semibold">Reject optional</button><button onClick={() => customize ? save(analytics, marketing) : setCustomize(true)} className="rounded-lg px-3 py-2 text-sm text-violet-300">{customize ? 'Save choices' : 'Customize'}</button></div></div></div>;
}
