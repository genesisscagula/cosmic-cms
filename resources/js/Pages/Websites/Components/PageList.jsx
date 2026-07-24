import PageListRow from "./PageListRow";

export default function PageList({ pages }) { return <div className="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.035]">{pages.map((page, index) => <div key={page.id} className={index ? "border-t border-white/10" : ""}><PageListRow page={page} /></div>)}</div>; }
