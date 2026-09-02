export default function PublicCard({ icon = '✦', eyebrow, title, children, className = '' }) {
    return (
        <article className={`min-w-0 rounded-[22px] border border-slate-200 bg-white p-5 shadow-[0_20px_60px_-38px_rgba(15,23,42,.35)] transition duration-300 sm:rounded-[24px] sm:p-6 md:hover:-translate-y-1 md:hover:border-emerald-200 md:hover:shadow-[0_26px_70px_-36px_rgba(5,150,105,.28)] ${className}`}>
            <span className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-sm font-extrabold text-emerald-700 ring-1 ring-emerald-100" aria-hidden="true">{icon}</span>
            {eyebrow && <p className="mt-5 text-[10px] font-extrabold uppercase tracking-[.16em] text-emerald-700 sm:text-[11px] sm:tracking-[.18em]">{eyebrow}</p>}
            {title && <h3 className="mt-2 text-lg font-semibold tracking-[-.025em] text-[#07132c] sm:text-xl">{title}</h3>}
            {children && <div className="mt-3 text-sm leading-6 text-slate-600 sm:text-[15px] sm:leading-7">{children}</div>}
        </article>
    );
}
