import PageListRow from "./PageListRow";

const flattenPages = (pages, parentId = null, depth = 0) => {
    const rows = [];
    pages.filter((page) => (page.parent_id ?? null) === parentId).forEach((page) => {
        rows.push({ page, depth });
        rows.push(...flattenPages(pages, page.id, depth + 1));
    });
    return rows;
};

export default function PageList({ pages = [], onDelete, onAddChild, onEditTitle, onClone }) {
    const rows = flattenPages(pages);
    return <div id="cosmic-page-list" className="cosmic-page-list overflow-hidden rounded-2xl">{rows.map(({ page, depth }, index) => <div key={page.id} className={index ? "border-t border-white/10" : ""}><PageListRow page={page} depth={depth} onDelete={onDelete} onAddChild={onAddChild} onEditTitle={onEditTitle} onClone={onClone} /></div>)}</div>;
}
