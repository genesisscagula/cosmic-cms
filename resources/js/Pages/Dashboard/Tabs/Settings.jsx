import InputError from "@/Components/InputError";
import { useForm } from "@inertiajs/react";
import { useEffect, useMemo, useState } from "react";

const timezones = ["Asia/Manila", "Australia/Sydney", "Pacific/Auckland", "America/New_York", "Europe/London", "UTC"];
const languages = [{ value: "en", label: "English" }, { value: "en-PH", label: "English (Philippines)" }];

const fieldClass = "mt-2 w-full rounded-xl border border-white/10 bg-white/[0.04] px-3.5 py-2.5 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-violet-400/60 focus:ring-2 focus:ring-violet-400/10";
const labelClass = "text-xs font-semibold uppercase tracking-[0.16em] text-slate-400";

function initialData(website) {
    return {
        _method: "put",
        name: website?.name || "",
        domain: website?.domain || "",
        industry: website?.industry || "",
        location: website?.location || "",
        business_description: website?.business_description || "",
        contact_email: website?.contact_email || "",
        contact_phone: website?.contact_phone || "",
        timezone: website?.timezone || "Asia/Manila",
        locale: website?.locale || "en",
        business_name: website?.business_name || "",
        address: website?.address || "",
        company_name: website?.company_name || "",
        owner_name: website?.owner_name || "",
        registration_number: website?.registration_number || "",
        vat_number: website?.vat_number || "",
        logo: null,
        favicon: null,
        remove_logo: false,
        remove_favicon: false,
    };
}

export default function Settings({ dashboard }) {
    const websites = dashboard?.settings?.websites || [];
    const [selectedId, setSelectedId] = useState(dashboard?.settings?.default_website_id || websites[0]?.id || null);
    const website = useMemo(() => websites.find((item) => item.id === Number(selectedId)) || websites[0] || null, [websites, selectedId]);
    const [logoPreview, setLogoPreview] = useState(website?.logo_url || null);
    const [faviconPreview, setFaviconPreview] = useState(website?.favicon_url || null);
    const { data, setData, post, processing, errors, recentlySuccessful, reset, clearErrors } = useForm(initialData(website));

    useEffect(() => {
        reset();
        clearErrors();
        const next = initialData(website);
        Object.entries(next).forEach(([key, value]) => setData(key, value));
        setLogoPreview(website?.logo_url || null);
        setFaviconPreview(website?.favicon_url || null);
    }, [website?.id]);

    const chooseImage = (field, file) => {
        if (!file) return;
        setData(field, file);
        setData(field === "logo" ? "remove_logo" : "remove_favicon", false);
        const url = URL.createObjectURL(file);
        field === "logo" ? setLogoPreview(url) : setFaviconPreview(url);
    };

    const removeImage = (field) => {
        setData(field, null);
        setData(field === "logo" ? "remove_logo" : "remove_favicon", true);
        field === "logo" ? setLogoPreview(null) : setFaviconPreview(null);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route("websites.settings.update", website.id), { forceFormData: true, preserveScroll: true });
    };

    if (!website) {
        return <section><p className="text-sm font-medium text-violet-300">Workspace</p><h1 className="mt-2 text-3xl font-semibold text-white">Settings</h1><div className="mt-8 rounded-2xl border border-dashed border-white/10 p-10 text-center text-sm text-slate-400">Create a website first to manage its settings.</div></section>;
    }

    return (
        <section>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div><p className="text-sm font-medium text-violet-300">Workspace</p><h1 className="mt-2 text-3xl font-semibold text-white">Settings</h1><p className="mt-3 text-sm text-slate-400">Manage the public identity, contact details, localization, and legal information for your website.</p></div>
                {websites.length > 1 && <label className="min-w-64"><span className={labelClass}>Website</span><select className={fieldClass} value={selectedId || ""} onChange={(e) => setSelectedId(Number(e.target.value))}>{websites.map((item) => <option key={item.id} value={item.id} className="bg-slate-950">{item.name}</option>)}</select></label>}
            </div>

            <form onSubmit={submit} className="mt-8 space-y-6">
                <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-5 sm:p-6">
                    <h2 className="text-lg font-semibold text-white">Website Settings</h2><p className="mt-1 text-sm text-slate-500">Core website information used throughout the dashboard, builder, and published pages.</p>
                    <div className="mt-6 grid gap-5 md:grid-cols-2">
                        {[['name','Website Name'],['business_name','Business Name'],['industry','Industry'],['location','Location'],['contact_email','Contact Email'],['contact_phone','Phone'],['domain','Website URL'],['address','Business Address']].map(([key,label]) => <label key={key}><span className={labelClass}>{label}</span><input type={key === 'contact_email' ? 'email' : key === 'domain' ? 'url' : 'text'} className={fieldClass} value={data[key]} onChange={(e) => setData(key,e.target.value)} required={key === 'name'} /><InputError className="mt-2" message={errors[key]} /></label>)}
                        <label><span className={labelClass}>Timezone</span><select className={fieldClass} value={data.timezone} onChange={(e) => setData('timezone',e.target.value)}>{timezones.map((zone)=><option key={zone} className="bg-slate-950">{zone}</option>)}</select><InputError className="mt-2" message={errors.timezone}/></label>
                        <label><span className={labelClass}>Language</span><select className={fieldClass} value={data.locale} onChange={(e) => setData('locale',e.target.value)}>{languages.map((language)=><option key={language.value} value={language.value} className="bg-slate-950">{language.label}</option>)}</select><InputError className="mt-2" message={errors.locale}/></label>
                    </div>
                    <label className="mt-5 block"><span className={labelClass}>Business Description</span><textarea rows="5" className={fieldClass} value={data.business_description} onChange={(e)=>setData('business_description',e.target.value)} /><InputError className="mt-2" message={errors.business_description}/></label>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <BrandUpload title="Logo" help="JPG, PNG, WebP, or SVG up to 3 MB." preview={logoPreview} field="logo" error={errors.logo} onChoose={chooseImage} onRemove={removeImage} />
                    <BrandUpload title="Favicon" help="Square PNG, ICO, JPG, or WebP up to 1 MB." preview={faviconPreview} field="favicon" error={errors.favicon} onChoose={chooseImage} onRemove={removeImage} compact />
                </div>

                <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-5 sm:p-6">
                    <h2 className="text-lg font-semibold text-white">Business Information</h2><p className="mt-1 text-sm text-slate-500">Optional company and registration details for invoices, legal pages, and business records.</p>
                    <div className="mt-6 grid gap-5 md:grid-cols-2">
                        {[['company_name','Registered Company'],['owner_name','Owner / Representative'],['registration_number','Registration Number'],['vat_number','VAT / Tax Number']].map(([key,label]) => <label key={key}><span className={labelClass}>{label}</span><input className={fieldClass} value={data[key]} onChange={(e)=>setData(key,e.target.value)} /><InputError className="mt-2" message={errors[key]}/></label>)}
                    </div>
                </div>

                <div className="flex items-center justify-end gap-4 rounded-2xl border border-white/10 bg-[#111113] px-5 py-4"><span className={`text-sm transition ${recentlySuccessful ? 'text-emerald-300' : 'text-slate-500'}`}>{recentlySuccessful ? 'Settings saved.' : 'Changes apply to the selected website.'}</span><button disabled={processing} className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-50">{processing ? 'Saving…' : 'Save Settings'}</button></div>
            </form>
        </section>
    );
}

function BrandUpload({ title, help, preview, field, error, onChoose, onRemove, compact = false }) {
    return <div className="rounded-2xl border border-white/10 bg-white/[0.025] p-5 sm:p-6"><h2 className="text-lg font-semibold text-white">{title}</h2><p className="mt-1 text-sm text-slate-500">{help}</p><div className="mt-5 flex items-center gap-4">{preview ? <img src={preview} alt={`${title} preview`} className={`${compact ? 'h-16 w-16' : 'h-20 w-32'} rounded-xl border border-white/10 bg-white object-contain p-2`} /> : <div className={`${compact ? 'h-16 w-16' : 'h-20 w-32'} flex items-center justify-center rounded-xl border border-dashed border-white/15 text-xs text-slate-600`}>No {title.toLowerCase()}</div>}<div><input type="file" accept={field === 'logo' ? 'image/jpeg,image/png,image/webp,image/svg+xml' : 'image/png,image/x-icon,image/jpeg,image/webp'} onChange={(e)=>onChoose(field,e.target.files?.[0])} className="block max-w-64 text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-violet-500/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-violet-300" />{preview && <button type="button" onClick={()=>onRemove(field)} className="mt-2 text-xs font-medium text-rose-300 hover:text-rose-200">Remove {title.toLowerCase()}</button>}</div></div><InputError className="mt-2" message={error}/></div>;
}
