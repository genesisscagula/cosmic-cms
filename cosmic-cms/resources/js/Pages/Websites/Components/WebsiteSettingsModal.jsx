import { useEffect, useState } from 'react';
import axios from 'axios';

export default function WebsiteSettingsModal({ website, onClose, onSaved }) {
    const [data, setData] = useState({ name: website.name || '', domain: website.domain || '', contact_email: website.contact_email || '' });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        const closeOnEscape = (event) => event.key === 'Escape' && !saving && onClose();
        window.addEventListener('keydown', closeOnEscape);
        return () => window.removeEventListener('keydown', closeOnEscape);
    }, [onClose, saving]);

    const save = async (event) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            const response = await axios.put(route('websites.settings.update', website.id), data);
            onSaved(response.data.website);
            onClose();
        } catch (error) {
            setErrors(error.response?.data?.errors || { general: 'Unable to save website settings. Please try again.' });
        } finally {
            setSaving(false);
        }
    };

    return <div className="fixed inset-0 z-[160] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
        <button type="button" aria-label="Close settings" onClick={onClose} className="absolute inset-0" disabled={saving} />
        <form onSubmit={save} role="dialog" aria-modal="true" aria-labelledby="website-settings-title" className="relative w-full max-w-lg rounded-2xl border border-white/10 bg-[#18181b] p-5 shadow-2xl shadow-black/60 sm:p-6">
            <div className="flex items-start justify-between gap-4"><div><p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-violet-300">Website settings</p><h2 id="website-settings-title" className="mt-1 text-xl font-semibold text-white">Website details</h2><p className="mt-2 text-sm leading-6 text-slate-400">Manage the identity and contact destination for this website.</p></div><button type="button" onClick={onClose} disabled={saving} className="rounded-lg px-2 py-1 text-lg text-slate-400 transition hover:bg-white/10 hover:text-white">×</button></div>
            <div className="mt-6 space-y-4"><label className="block text-sm font-medium text-slate-200">Website name<input value={data.name} onChange={(event) => setData({ ...data, name: event.target.value })} className="mt-2 w-full rounded-xl border border-white/10 bg-black/25 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" /></label>{errors.name && <p className="-mt-2 text-xs text-rose-300">{errors.name[0]}</p>}<label className="block text-sm font-medium text-slate-200">Live website URL<span className="mt-1 block text-xs font-normal text-slate-500">Used for connector verification. Include https:// or http://.</span><input type="url" value={data.domain} onChange={(event) => setData({ ...data, domain: event.target.value })} placeholder="https://example.com" className="mt-2 w-full rounded-xl border border-white/10 bg-black/25 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" /></label>{errors.domain && <p className="-mt-2 text-xs text-rose-300">{errors.domain[0]}</p>}<label className="block text-sm font-medium text-slate-200">Inquiry recipient email<span className="mt-1 block text-xs font-normal text-slate-500">Used by the Deployment Connector when its server mail service is configured.</span><input type="email" value={data.contact_email} onChange={(event) => setData({ ...data, contact_email: event.target.value })} placeholder="you@example.com" className="mt-2 w-full rounded-xl border border-white/10 bg-black/25 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" /></label>{errors.contact_email && <p className="-mt-2 text-xs text-rose-300">{errors.contact_email[0]}</p>}<p className="rounded-xl border border-amber-300/15 bg-amber-300/[0.05] p-3 text-xs leading-5 text-amber-100/80">After changing the recipient email, download and replace the connector again before sending live form email.</p>{errors.general && <p className="text-xs text-rose-300">{errors.general}</p>}</div>
            <div className="mt-6 flex justify-end gap-2 border-t border-white/10 pt-4"><button type="button" onClick={onClose} disabled={saving} className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-white/10">Cancel</button><button type="submit" disabled={saving} className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 disabled:opacity-50">{saving ? 'Saving...' : 'Save settings'}</button></div>
        </form>
    </div>;
}
