import PageListRow from "./PageListRow";

export default function PageList({ pages, onDelete, onAddChild }) {
    const byParent = new Map();
    pages.forEach((page) => {
        const key = page.parent_id || 0;
        byParent.set(key, [...(byParent.get(key) || []), page]);
    });

    const rows = [];
    const appendRows = (page, depth = 0) => {
        rows.push({ page, depth });
        (byParent.get(page.id) || []).forEach((child) => appendRows(child, depth + 1));
    };
    (byParent.get(0) || []).forEach((page) => appendRows(page));

    return <div className="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.035]">{rows.map(({ page, depth }, index) => <div key={page.id} className={index ? "border-t border-white/10" : ""}><PageListRow page={page} depth={depth} onDelete={onDelete} onAddChild={onAddChild} /></div>)}</div>;
}
