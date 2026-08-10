import { useEffect, useState } from "react";
import StarterKitSparkPreview from "./StarterKitSparkPreview";

const viewportOptions = [
    { id: "desktop", label: "Desktop", width: "100%" },
    { id: "tablet", label: "Tablet", width: "760px" },
    { id: "mobile", label: "Mobile", width: "390px" },
];

function humanize(value) {
    return String(value || "")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function TemplatePreviewModal({ template, onClose, onUse }) {
    const [viewport, setViewport] = useState("desktop");
    const activeViewport = viewportOptions.find((item) => item.id === viewport) || viewportOptions[0];

    useEffect(() => {
        if (!template) return undefined;

        setViewport(window.innerWidth < 640 ? "mobile" : window.innerWidth < 1024 ? "tablet" : "desktop");

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";

        const handleKeyDown = (event) => {
            if (event.key === "Escape") onClose();
        };

        window.addEventListener("keydown", handleKeyDown);

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener("keydown", handleKeyDown);
        };
    }, [template, onClose]);

    if (!template) return null;

    const fixedThemeFamily = template.themeFamily
        || template.theme_family
        || template.themeId
        || "midnight";

    return (
        <div className="fixed inset-0 z-[950] bg-[#08080b]">
            <section
                role="dialog"
                aria-modal="true"
                aria-labelledby="starter-kit-preview-title"
                className="flex h-screen w-screen flex-col overflow-hidden bg-[#101014] text-white"
            >
                <header className="sticky top-0 z-30 flex shrink-0 flex-col gap-3 border-b border-white/10 bg-[#101014]/95 px-4 py-3 shadow-xl shadow-black/20 backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div className="min-w-0">
                        <p className="text-[10px] font-semibold uppercase tracking-[0.22em] text-violet-300">
                            Starter Kit Preview · {humanize(fixedThemeFamily)} Theme
                        </p>
                        <h2 id="starter-kit-preview-title" className="mt-1 truncate text-lg font-semibold sm:text-xl">
                            {template.name}
                        </h2>
                        <p className="mt-1 hidden text-xs text-slate-500 sm:block">
                            {template.sparkCount || template.previewSparks?.length || 0} curated Sparks · fixed {humanize(fixedThemeFamily)} color family · installs as My Brand
                        </p>
                    </div>

                    <div className="flex min-w-0 shrink-0 items-center gap-2">
                        <div className="hidden min-w-0 overflow-x-auto rounded-xl border border-white/10 bg-white/[0.035] p-1 sm:flex" aria-label="Preview viewport">
                            {viewportOptions.map((option) => (
                                <button
                                    key={option.id}
                                    type="button"
                                    onClick={() => setViewport(option.id)}
                                    aria-pressed={viewport === option.id}
                                    className={`rounded-lg px-3 py-1.5 text-[11px] font-semibold transition ${
                                        viewport === option.id
                                            ? "bg-white text-slate-950"
                                            : "text-slate-400 hover:text-white"
                                    }`}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>

                        <button
                            type="button"
                            onClick={() => onUse(template)}
                            className="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-slate-200 sm:px-5"
                        >
                            Install Starter Kit
                        </button>

                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white"
                        >
                            Close
                        </button>
                    </div>
                </header>

                <div className="min-h-0 flex-1 overflow-y-auto bg-[#dfe3e8]">
                    <div
                        className="mx-auto min-h-full overflow-hidden bg-white shadow-2xl transition-[width] duration-300"
                        style={{ width: activeViewport.width, maxWidth: "100%" }}
                    >
                        <StarterKitSparkPreview template={template} />
                    </div>
                </div>
            </section>
        </div>
    );
}
