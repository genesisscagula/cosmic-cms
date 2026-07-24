import { useEffect, useRef } from "react";
import { useForm } from "@inertiajs/react";

export default function NewWebsiteModal({ open, onClose, template = null }) {
    const nameInput = useRef(null);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        name: "",
        domain: "",
        template: null,
    });

    useEffect(() => {
        if (open) setData("template", template?.slug || null);
    }, [open, template, setData]);

    useEffect(() => {
        if (!open) return undefined;

        nameInput.current?.focus();
        const handleKeyDown = (event) => {
            if (event.key === "Escape" && !processing) onClose();
        };

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [open, onClose, processing]);

    if (!open) return null;

    const closeModal = () => {
        if (processing) return;
        reset();
        clearErrors();
        onClose();
    };

    const submit = (event) => {
        event.preventDefault();
        post(route("websites.store"), {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm" role="presentation">
            <button type="button" aria-label="Close New Website dialog" onClick={closeModal} className="absolute inset-0 cursor-default" />
            <section role="dialog" aria-modal="true" aria-labelledby="new-website-title" className="relative w-full max-w-md rounded-2xl border border-white/10 bg-[#151519] p-5 text-slate-100 shadow-2xl shadow-black/50 sm:p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.16em] text-violet-300">Workspace</p>
                        <h2 id="new-website-title" className="mt-2 text-xl font-semibold tracking-tight text-white">Create a website</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-400">{template ? `Start with the ${template.name} starter and make it your own.` : "Start with a name and an optional live domain. You can add pages next."}</p>
                    </div>
                    <button type="button" onClick={closeModal} disabled={processing} className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-lg text-slate-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Close">×</button>
                </div>

                <form className="mt-6 space-y-4" onSubmit={submit}>
                    <div>
                        <label htmlFor="website-name" className="text-sm font-medium text-slate-200">Website name</label>
                        <input ref={nameInput} id="website-name" type="text" value={data.name} onChange={(event) => setData("name", event.target.value)} maxLength={255} required disabled={processing} placeholder="e.g. Northstar Studio" className="mt-2 h-11 w-full rounded-xl border border-white/10 bg-black/25 px-3 text-sm text-white outline-none transition placeholder:text-slate-500 hover:border-white/20 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20 disabled:cursor-not-allowed disabled:opacity-60" />
                        {errors.name && <p className="mt-2 text-xs font-medium text-red-300" role="alert">{errors.name}</p>}
                    </div>

                    <div>
                        <label htmlFor="website-domain" className="text-sm font-medium text-slate-200">Domain <span className="text-slate-500">(optional)</span></label>
                        <input id="website-domain" type="url" value={data.domain} onChange={(event) => setData("domain", event.target.value)} disabled={processing} placeholder="https://example.com" className="mt-2 h-11 w-full rounded-xl border border-white/10 bg-black/25 px-3 text-sm text-white outline-none transition placeholder:text-slate-500 hover:border-white/20 focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20 disabled:cursor-not-allowed disabled:opacity-60" />
                        {errors.domain && <p className="mt-2 text-xs font-medium text-red-300" role="alert">{errors.domain}</p>}
                    </div>

                    {errors.template && <p className="text-xs font-medium text-red-300" role="alert">{errors.template}</p>}

                    <div className="flex flex-col-reverse gap-2 border-t border-white/10 pt-5 sm:flex-row sm:justify-end">
                        <button type="button" onClick={closeModal} disabled={processing} className="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400 disabled:cursor-not-allowed disabled:opacity-50">Cancel</button>
                        <button type="submit" disabled={processing} className="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-400 focus:ring-offset-2 focus:ring-offset-[#151519] disabled:cursor-not-allowed disabled:opacity-60">{processing ? "Creating…" : "Create Website"}</button>
                    </div>
                </form>
            </section>
        </div>
    );
}
