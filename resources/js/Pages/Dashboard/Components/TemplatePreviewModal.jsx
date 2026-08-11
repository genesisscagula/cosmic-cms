import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
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

    return createPortal(
        <div className="cosmic-template-preview fixed inset-0 z-[950]" data-cosmic-starter-kit-portal="true">
            <section
                role="dialog"
                aria-modal="true"
                aria-labelledby="starter-kit-preview-title"
                className="cosmic-template-preview-panel cosmic-website-preview cosmic-starter-kit-preview-modal flex h-full w-full flex-col overflow-hidden"
            >
                <header className="cosmic-template-preview-header sticky top-0 z-30 flex shrink-0 flex-col gap-3 border-b px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div className="min-w-0">
                        <p className="cosmic-template-eyebrow text-[10px] font-bold uppercase tracking-[0.2em]">
                            Starter Kit Preview · {humanize(fixedThemeFamily)} Theme
                        </p>
                        <h2 id="starter-kit-preview-title" className="mt-1 truncate text-lg font-semibold sm:text-xl">
                            {template.name}
                        </h2>
                        <p className="cosmic-template-muted mt-1 hidden text-xs sm:block">
                            {template.sparkCount || template.previewBlocks?.length || template.previewSparks?.length || 0} curated Sparks · fixed {humanize(fixedThemeFamily)} color family · installs as My Brand
                        </p>
                    </div>

                    <div className="flex min-w-0 shrink-0 items-center gap-2">
                        <div className="cosmic-template-secondary hidden min-w-0 overflow-x-auto rounded-xl border p-1 sm:flex" aria-label="Preview viewport">
                            {viewportOptions.map((option) => (
                                <button
                                    key={option.id}
                                    type="button"
                                    onClick={() => setViewport(option.id)}
                                    aria-pressed={viewport === option.id}
                                    className={`rounded-lg px-3 py-1.5 text-[11px] font-semibold transition ${
                                        viewport === option.id
                                            ? "cosmic-template-primary"
                                            : "cosmic-template-muted"
                                    }`}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>

                        <button
                            type="button"
                            onClick={() => onUse(template)}
                            className="cosmic-template-primary rounded-xl px-4 py-2.5 text-sm font-bold sm:px-5"
                        >
                            Install Starter Kit
                        </button>

                        <button
                            type="button"
                            onClick={onClose}
                            className="cosmic-template-secondary rounded-xl border px-4 py-2.5 text-sm font-semibold"
                        >
                            Close
                        </button>
                    </div>
                </header>

                <div className="cosmic-template-preview-scroll min-h-0 flex-1 overflow-y-auto">
                    <div
                        className="mx-auto min-h-full overflow-hidden bg-white transition-[width] duration-300"
                        style={{ width: activeViewport.width, maxWidth: "100%" }}
                    >
                        <StarterKitSparkPreview template={template} />
                    </div>
                </div>
            </section>
        </div>,
        document.body,
    );
}
