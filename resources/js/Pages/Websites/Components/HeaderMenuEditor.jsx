import { useState } from 'react';

const cloneMenu = (menu) => JSON.parse(JSON.stringify(Array.isArray(menu) ? menu : []));
const collectionForPath = (menu, path) => {
    let collection = menu;
    path.slice(0, -1).forEach((index) => {
        collection = collection[index]?.children || [];
    });
    return collection;
};

function updateItem(menu, path, updater) {
    const next = cloneMenu(menu);
    let collection = next;

    path.forEach((index, depth) => {
        if (depth === path.length - 1) {
            collection[index] = updater(collection[index] || {});
            return;
        }
        collection[index].children = Array.isArray(collection[index].children) ? collection[index].children : [];
        collection = collection[index].children;
    });

    return next;
}

function removeItem(menu, path) {
    const next = cloneMenu(menu);
    const collection = collectionForPath(next, path);
    collection.splice(path.at(-1), 1);
    return next;
}

function moveItem(menu, path, direction) {
    const next = cloneMenu(menu);
    const collection = collectionForPath(next, path);
    const index = path.at(-1);
    const target = index + direction;
    if (target < 0 || target >= collection.length) return next;
    [collection[index], collection[target]] = [collection[target], collection[index]];
    return next;
}

const blankMenuItem = () => ({ label: 'New link', url: '#', children: [] });

export default function HeaderMenuEditor({ menu, onChange, targetOptions = [] }) {
    const [draggedIndex, setDraggedIndex] = useState(null);
    const id = 'published-page-slugs';

    const addChild = (path) => {
        onChange(updateItem(menu, path, (item) => ({
            ...item,
            children: [...(Array.isArray(item.children) ? item.children : []), blankMenuItem()],
        })));
    };

    const renderItem = (item, path, depth = 0) => {
        const siblings = collectionForPath(menu, path);
        const isRoot = depth === 0;

        return (
            <div key={path.join('-')} className={depth ? 'ml-4 border-l border-violet-400/30 pl-3 sm:ml-6' : ''}>
                <div
                    draggable={isRoot}
                    onDragStart={() => isRoot && setDraggedIndex(path[0])}
                    onDragOver={(event) => { if (isRoot) event.preventDefault(); }}
                    onDrop={() => {
                        if (!isRoot || draggedIndex === null || draggedIndex === path[0]) return;
                        const next = cloneMenu(menu);
                        const [moved] = next.splice(draggedIndex, 1);
                        next.splice(path[0], 0, moved);
                        onChange(next);
                        setDraggedIndex(null);
                    }}
                    onDragEnd={() => setDraggedIndex(null)}
                    className={`grid grid-cols-1 gap-2 rounded-lg border bg-white/[0.025] p-2.5 sm:grid-cols-[auto_minmax(0,0.9fr)_minmax(0,1.1fr)_auto] ${isRoot ? 'cursor-grab active:cursor-grabbing' : 'border-white/10'}`}
                >
                    <div className="flex items-center gap-1 text-slate-500">
                        {isRoot && <span className="hidden select-none text-base sm:inline" aria-hidden="true">⠿</span>}
                        <span className="text-[10px] font-semibold uppercase tracking-[0.12em]">{depth === 0 ? 'Menu' : depth === 1 ? 'Submenu' : 'Nested'}</span>
                    </div>
                    <label className="min-w-0">
                        <span className="mb-1 block text-[10px] font-medium uppercase tracking-[0.12em] text-slate-500">Menu label</span>
                        <input value={item.label || ''} onChange={(event) => onChange(updateItem(menu, path, (current) => ({ ...current, label: event.target.value })))} className="w-full rounded-md border border-white/10 bg-black/20 px-3 py-2 text-sm text-white outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" />
                    </label>
                    <label className="min-w-0">
                        <span className="mb-1 block text-[10px] font-medium uppercase tracking-[0.12em] text-slate-500">Link target</span>
                        <input list={id} value={item.url || ''} onChange={(event) => onChange(updateItem(menu, path, (current) => ({ ...current, url: event.target.value })))} placeholder="home, about, #contact, or https://..." className="w-full rounded-md border border-white/10 bg-black/20 px-3 py-2 text-sm text-white outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-400/20" />
                    </label>
                    <div className="flex items-end justify-end gap-1">
                        <button type="button" onClick={() => onChange(moveItem(menu, path, -1))} disabled={path.at(-1) === 0} className="rounded px-1.5 py-2 text-xs text-slate-400 hover:bg-white/10 hover:text-white disabled:opacity-30" aria-label="Move item up">↑</button>
                        <button type="button" onClick={() => onChange(moveItem(menu, path, 1))} disabled={path.at(-1) === siblings.length - 1} className="rounded px-1.5 py-2 text-xs text-slate-400 hover:bg-white/10 hover:text-white disabled:opacity-30" aria-label="Move item down">↓</button>
                        <button type="button" onClick={() => onChange(removeItem(menu, path))} className="rounded px-1.5 py-2 text-xs text-rose-300 hover:bg-rose-400/10 hover:text-rose-200" aria-label="Remove menu item">Remove</button>
                    </div>
                </div>
                {depth < 2 && <button type="button" onClick={() => addChild(path)} className="mt-1.5 text-xs font-semibold text-violet-200 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">+ {depth === 0 ? 'Add submenu' : 'Add nested link'}</button>}
                {(item.children || []).map((child, index) => renderItem(child, [...path, index], depth + 1))}
            </div>
        );
    };

    return (
        <div className="mt-3 space-y-2">
            <datalist id={id}>{targetOptions.map((page) => <option key={page.slug} value={page.slug}>{page.title}</option>)}</datalist>
            {(menu || []).map((item, index) => renderItem(item, [index]))}
            <button type="button" onClick={() => onChange([...(menu || []), blankMenuItem()])} className="inline-flex items-center rounded-lg border border-dashed border-violet-400/45 px-3 py-2 text-xs font-semibold text-violet-200 transition hover:border-violet-300 hover:bg-violet-400/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400">+ Add menu link</button>
        </div>
    );
}
