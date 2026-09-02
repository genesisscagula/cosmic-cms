import { Link } from '@inertiajs/react';

export default function PublicBreadcrumbs({ items = [] }) {
    if (!items.length) return null;

    return (
        <nav aria-label="Breadcrumb" className="flex max-w-full flex-wrap items-center gap-x-2 gap-y-1.5 text-xs font-semibold text-slate-500 sm:text-sm">
            <Link href="/" className="rounded transition hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">Home</Link>
            {items.map((item, index) => {
                const isLast = index === items.length - 1;
                return (
                    <span key={`${item.label}-${index}`} className="inline-flex min-w-0 items-center gap-2">
                        <span aria-hidden="true" className="shrink-0 text-slate-300">/</span>
                        {item.href && !isLast ? (
                            <Link href={item.href} className="rounded transition hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{item.label}</Link>
                        ) : (
                            <span aria-current={isLast ? 'page' : undefined} className={`${isLast ? 'text-slate-800' : ''} max-w-[68vw] truncate sm:max-w-none`}>{item.label}</span>
                        )}
                    </span>
                );
            })}
        </nav>
    );
}
