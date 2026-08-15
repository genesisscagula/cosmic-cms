export function RepeatableControls({ onAdd, onRemove, canRemove = true, addLabel = "Add item", removeLabel = "Remove last", className = "" }) {
    return (
        <div className={`mt-6 flex flex-wrap items-center justify-center gap-2 ${className}`} data-cosmic-repeatable-controls>
            <button type="button" onClick={onAdd} className="rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-900 shadow-sm transition hover:bg-slate-100">+ {addLabel}</button>
            <button type="button" onClick={onRemove} disabled={!canRemove} className="rounded-full border border-rose-500 bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:opacity-100">− {removeLabel}</button>
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

export function RepeatableRemoveButton({ onRemove, disabled = false, label = "Remove" }) {
    return <button type="button" onClick={onRemove} disabled={disabled} className="mt-3 rounded-full border border-rose-500 bg-rose-600 px-3 py-1.5 text-[11px] font-semibold text-white shadow-sm transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:opacity-100">− {label}</button>;
}

export const removeAt = (items = [], index = -1, min = 1) => items.length > min ? items.filter((_, i) => i !== index) : items;


export function BoundedCountControls({ count = 1, min = 1, max = 3, onChange, addLabel = "Add item", removeLabel = "Remove last", className = "" }) {
    const safe = Math.max(min, Math.min(max, Number(count) || min));
    return (
        <div className={`mt-5 flex flex-wrap items-center justify-center gap-2 ${className}`} data-cosmic-bounded-controls>
            <button type="button" disabled={safe >= max} onClick={() => onChange(Math.min(max, safe + 1))} className="rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-900 shadow-sm transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 disabled:opacity-100">+ {addLabel}</button>
            <button type="button" disabled={safe <= min} onClick={() => onChange(Math.max(min, safe - 1))} className="rounded-full border border-rose-500 bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:opacity-100">− {removeLabel}</button>
        </div>
    );
}
