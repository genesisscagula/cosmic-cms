import { useEffect, useState } from "react";
import axios from "axios";

const INDUSTRY_OPTIONS = [
    ["automotive", "Automotive"], ["bakery", "Bakery"], ["cleaning", "Cleaning"],
    ["coffee", "Coffee shop"], ["construction", "Construction"], ["dentist", "Dental clinic"],
    ["education", "Education"], ["electrician", "Electrician"], ["finance", "Finance"],
    ["fitness", "Fitness"], ["hotel", "Hotel"], ["landscaping", "Landscaping"],
    ["lawyer", "Law firm"], ["medical", "Medical"], ["plumbing", "Plumbing"],
    ["real-estate", "Real estate"], ["restaurant", "Restaurant"], ["roofing", "Roofing"],
    ["salon", "Salon"], ["technology", "Technology"], ["travel", "Travel"],
];

export default function BusinessProfileModal({ website, onClose, onSaved }) {
    const [data, setData] = useState({
        industry: website.industry || "",
        location: website.location || "",
        business_description: website.business_description || "",
    });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        const closeOnEscape = (event) => event.key === "Escape" && !saving && onClose();
        window.addEventListener("keydown", closeOnEscape);
        return () => window.removeEventListener("keydown", closeOnEscape);
    }, [onClose, saving]);

    const save = async (event) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            const response = await axios.put(route("websites.profile.update", website.id), data);
            onSaved(response.data.website);
            onClose();
        } catch (error) {
            setErrors(error.response?.data?.errors || { general: "Unable to save the business profile. Please try again." });
        } finally {
            setSaving(false);
        }
    };

    const fieldError = (field) => errors[field]?.[0];
    const hasSavedCustomIndustry = data.industry && !INDUSTRY_OPTIONS.some(([value]) => value === data.industry);

    return (
        <div className="fixed inset-0 z-[160] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
            <button type="button" aria-label="Close business profile" onClick={onClose} className="absolute inset-0" disabled={saving} />
            <form onSubmit={save} role="dialog" aria-modal="true" aria-labelledby="business-profile-title" className="relative w-full max-w-xl rounded-2xl border border-white/10 bg-[#18181b] p-5 shadow-2xl shadow-black/60 sm:p-6">
                <div className="flex items-start justify-between gap-4">
                    <div><p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-violet-300">Business profile</p><h2 id="business-profile-title" className="mt-1 text-xl font-semibold text-white">Help Cosmic understand your business</h2><p className="mt-2 text-sm leading-6 text-slate-400">This profile is used as background context for page and section generation. It never replaces your optional custom request.</p></div>
                    <button type="button" onClick={onClose} disabled={saving} className="rounded-lg px-2 py-1 text-lg text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400" aria-label="Close">×</button>
                </div>
                <div className="mt-6 space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-slate-200">Industry<select value={data.industry} onChange={(event) => setData({ ...data, industry: event.target.value })} required className="mt-2 w-full rounded-xl border border-white/10 bg-[#19191d] px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20"><option value="">Select an industry</option>{hasSavedCustomIndustry && <option value={data.industry}>{data.industry}</option>}{INDUSTRY_OPTIONS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                        <label className="block text-sm font-medium text-slate-200">Location<input value={data.location} onChange={(event) => setData({ ...data, location: event.target.value })} maxLength={255} required placeholder="e.g. New York, NY" className="mt-2 w-full rounded-xl border border-white/10 bg-black/25 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" /></label>
                    </div>
                    {fieldError("industry") || fieldError("location") ? <p className="-mt-2 text-xs text-rose-300">{fieldError("industry") || fieldError("location")}</p> : null}
                    <label className="block text-sm font-medium text-slate-200">About your business<textarea value={data.business_description} onChange={(event) => setData({ ...data, business_description: event.target.value })} maxLength={2000} rows={6} required placeholder="Describe your services, customers, and what makes your business different." className="mt-2 w-full resize-y rounded-xl border border-white/10 bg-black/25 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" /></label>
                    {fieldError("business_description") ? <p className="-mt-2 text-xs text-rose-300">{fieldError("business_description")}</p> : null}
                    {errors.general ? <p className="text-xs text-rose-300">{errors.general}</p> : null}
                </div>
                <div className="mt-6 flex justify-end gap-2 border-t border-white/10 pt-4"><button type="button" onClick={onClose} disabled={saving} className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/10">Cancel</button><button type="submit" disabled={saving} className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 disabled:opacity-50">{saving ? "Saving..." : "Save profile"}</button></div>
            </form>
        </div>
    );
}
