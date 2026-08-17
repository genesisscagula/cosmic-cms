import { useEffect, useMemo, useRef, useState } from "react";

const scopeSelector = (scope, kind = "control") => {
    if (scope === "pricing-card") return '[class~="group/pricing-card"]';
    if (scope === "pricing-feature") return '[class~="group/pricing-feature"]';
    if (scope === "pricing-section") return '[class~="group/pricing-section"]';
    if (scope === "section") return '[class~="group/repeatable-section"]';
    if (scope === "card") return '[data-cosmic-repeatable-card], [class~="group"], [class*="group/"]';
    if (scope === "item") return '[data-cosmic-repeatable-item], [class~="group"], [class*="group/"]';
    return kind === "remove"
        ? '[data-cosmic-repeatable-item], [data-cosmic-repeatable-card], [class~="group"], [class*="group/"]'
        : '[class~="group/repeatable-section"]';
};

const readableControlColor = (node, host) => {
    if (typeof window === "undefined") return null;
    const candidates = [node?.closest("section"), host, node?.parentElement].filter(Boolean);
    const parse = (value = "") => {
        const match = value.match(/rgba?\((\d+(?:\.\d+)?)[,\s]+(\d+(?:\.\d+)?)[,\s]+(\d+(?:\.\d+)?)(?:[,\s\/]+([\d.]+))?\)/i);
        if (!match) return null;
        const alpha = match[4] === undefined ? 1 : Number(match[4]);
        if (!Number.isFinite(alpha) || alpha < 0.08) return null;
        return [Number(match[1]), Number(match[2]), Number(match[3])];
    };
    for (const candidate of candidates) {
        const rgb = parse(window.getComputedStyle(candidate).backgroundColor);
        if (!rgb) continue;
        const [r,g,b] = rgb.map((channel) => {
            const c = channel / 255;
            return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
        });
        const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
        return luminance < 0.42 ? "#ffffff" : "#334155";
    }
    return null;
};

function useContextualVisibility(scope, kind = "control") {
    const ref = useRef(null);
    const [visible, setVisible] = useState(false);
    const [controlColor, setControlColor] = useState(null);
    const selector = useMemo(() => scopeSelector(scope, kind), [scope, kind]);

    useEffect(() => {
        const node = ref.current;
        if (!node || typeof window === "undefined") return undefined;

        const coarse = window.matchMedia?.("(hover: none), (pointer: coarse)");
        const updateForInput = () => {
            if (coarse?.matches) setVisible(true);
        };
        updateForInput();
        coarse?.addEventListener?.("change", updateForInput);

        const host = node.closest(selector);
        const refreshColor = () => setControlColor(readableControlColor(node, host));
        refreshColor();
        if (!host) {
            // Never leave an editor action permanently visible on desktop just
            // because a block missed a group class. Patch 2 audits those hosts.
            if (!coarse?.matches) setVisible(false);
            return () => coarse?.removeEventListener?.("change", updateForInput);
        }

        const show = () => setVisible(true);
        const hide = (event) => {
            if (coarse?.matches) return;
            const next = event?.relatedTarget;
            if (next && host.contains(next)) return;
            if (host.matches(":focus-within")) return;
            setVisible(false);
        };
        const onFocusIn = () => setVisible(true);
        const onFocusOut = () => {
            if (coarse?.matches) return;
            window.requestAnimationFrame(() => {
                if (!host.matches(":hover") && !host.matches(":focus-within")) setVisible(false);
            });
        };

        const observerTarget = node.closest("section") || host;
        const observer = typeof MutationObserver !== "undefined" ? new MutationObserver(refreshColor) : null;
        observer?.observe(observerTarget, { attributes: true, attributeFilter: ["class", "style"] });

        host.addEventListener("mouseenter", show);
        host.addEventListener("mouseleave", hide);
        host.addEventListener("focusin", onFocusIn);
        host.addEventListener("focusout", onFocusOut);

        if (!coarse?.matches && (host.matches(":hover") || host.matches(":focus-within"))) setVisible(true);

        return () => {
            host.removeEventListener("mouseenter", show);
            host.removeEventListener("mouseleave", hide);
            host.removeEventListener("focusin", onFocusIn);
            host.removeEventListener("focusout", onFocusOut);
            observer?.disconnect();
            coarse?.removeEventListener?.("change", updateForInput);
        };
    }, [selector]);

    return { ref, visible, controlColor };
}

export function RepeatableControls({ onAdd, onRemove, canAdd = true, canRemove = true, addLabel = "Add item", removeLabel = "Remove last", className = "", showRemove = true, addButtonClassName = "", hoverScope = "section" }) {
    const { ref, visible, controlColor } = useContextualVisibility(hoverScope, "control");

    return (
        <div
            ref={ref}
            className={`mt-5 flex flex-wrap items-center justify-start gap-2 transition-all duration-150 ${visible ? "visible opacity-100" : "invisible pointer-events-none opacity-0"} ${className}`}
            data-cosmic-repeatable-controls
            data-hover-scope={hoverScope}
            style={controlColor ? { color: controlColor } : undefined}
        >
            <button
                type="button"
                onClick={onAdd}
                disabled={!canAdd}
                className={`inline-flex min-h-8 items-center rounded-md border border-dashed border-current/55 bg-transparent px-3 py-1.5 text-[11px] font-semibold text-current shadow-none transition hover:border-current/90 hover:bg-current/[.06] disabled:cursor-not-allowed disabled:opacity-35 ${addButtonClassName}`}
            >
                + {addLabel}
            </button>
            {showRemove && (
                <button
                    type="button"
                    onClick={onRemove}
                    disabled={!canRemove}
                    className="inline-flex min-h-8 items-center rounded-md border border-dashed border-current/35 bg-transparent px-3 py-1.5 text-[11px] font-semibold text-current opacity-65 shadow-none transition hover:border-rose-400/70 hover:text-rose-500 hover:opacity-100 disabled:cursor-not-allowed disabled:opacity-25"
                >
                    − {removeLabel}
                </button>
            )}
        </div>
    );
}

export const cloneLast = (items = [], fallback = {}) => {
    const source = items.length ? items[items.length - 1] : fallback;
    return [...items, { ...source }];
};

export const removeLast = (items = [], min = 1) => items.length > min ? items.slice(0, -1) : items;

export const slotWords = ["one","two","three","four","five","six","seven","eight"];
export function cloneFlatSlot(data, prefixes, count, max = 8) {
    if (count >= max) return {};
    const from = slotWords[Math.max(0, count - 1)];
    const to = slotWords[count];
    const patch = {};
    prefixes.forEach((prefix) => { patch[`${prefix}_${to}`] = data[`${prefix}_${from}`] ?? ""; });
    return patch;
}

export function RepeatableRemoveButton({ onRemove, disabled = false, label = "Remove", overlay = false, placement = "card", hoverScope = "default" }) {
    const { ref, visible } = useContextualVisibility(hoverScope, "remove");

    const icon = (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
            <path d="M3 6h18" />
            <path d="M8 6V4h8v2" />
            <path d="M19 6l-1 14H6L5 6" />
            <path d="M10 11v5" />
            <path d="M14 11v5" />
        </svg>
    );

    const visibilityClass = visible ? "visible opacity-100" : "invisible pointer-events-none opacity-0";

    if (overlay) {
        return (
            <button
                ref={ref}
                type="button"
                onClick={onRemove}
                disabled={disabled}
                title={label}
                aria-label={label}
                data-cosmic-repeatable-remove
                data-hover-scope={hoverScope}
                className={`${placement === "row" ? "right-0 top-1/2 -translate-y-1/2" : "right-3 top-3"} absolute z-20 inline-flex h-8 w-8 items-center justify-center rounded-full border border-current/25 bg-white/90 text-slate-500 shadow-sm backdrop-blur-sm transition hover:border-rose-300 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-2 focus:ring-current/20 disabled:pointer-events-none disabled:opacity-25 ${visibilityClass}`}
            >
                {icon}
            </button>
        );
    }

    return (
        <button
            ref={ref}
            type="button"
            onClick={onRemove}
            disabled={disabled}
            title={label}
            aria-label={label}
            data-cosmic-repeatable-remove
            data-hover-scope={hoverScope}
            className={`mt-2 inline-flex h-8 w-8 items-center justify-center rounded-full border border-current/25 bg-transparent text-current transition hover:border-rose-400/70 hover:text-rose-500 focus:outline-none focus:ring-2 focus:ring-current/20 disabled:pointer-events-none disabled:opacity-25 ${visibilityClass}`}
        >
            {icon}
        </button>
    );
}

export const removeAt = (items = [], index = -1, min = 1) => items.length > min ? items.filter((_, i) => i !== index) : items;

export function BoundedCountControls({ count = 1, min = 1, max = 3, onChange, addLabel = "Add item", removeLabel = "Remove last", className = "", hoverScope = "section", showRemove = true }) {
    const safe = Math.max(min, Math.min(max, Number(count) || min));
    const { ref, visible, controlColor } = useContextualVisibility(hoverScope, "control");
    return (
        <div ref={ref} className={`mt-5 flex flex-wrap items-center justify-start gap-2 transition-all ${visible ? "visible opacity-100" : "invisible pointer-events-none opacity-0"} ${className}`} data-cosmic-bounded-controls data-hover-scope={hoverScope} style={controlColor ? { color: controlColor } : undefined}>
            <button type="button" disabled={safe >= max} onClick={() => onChange(Math.min(max, safe + 1))} className="rounded-md border border-dashed border-current/55 bg-transparent px-3 py-1.5 text-[11px] font-semibold text-current transition hover:border-current/90 hover:bg-current/[.06] disabled:cursor-not-allowed disabled:opacity-35">+ {addLabel}</button>
            {showRemove && <button type="button" disabled={safe <= min} onClick={() => onChange(Math.max(min, safe - 1))} className="rounded-md border border-dashed border-current/35 bg-transparent px-3 py-1.5 text-[11px] font-semibold text-current opacity-65 transition hover:border-rose-400/70 hover:text-rose-500 hover:opacity-100 disabled:cursor-not-allowed disabled:opacity-25">− {removeLabel}</button>}
        </div>
    );
}
