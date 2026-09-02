import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicSiteLayout from '@/Components/Public/PublicSiteLayout';
import PublicInnerHero from '@/Components/Public/PublicInnerHero';
import PublicCard from '@/Components/Public/PublicCard';
import PublicCta from '@/Components/Public/PublicCta';

export default function InnerPage({ page }) {
    const cards = page?.cards ?? [];
    return (
        <PublicSiteLayout>
            <SeoHead title={page?.seoTitle ?? page?.title ?? 'Cosmic CMS'} description={page?.description ?? ''} />
            <PublicInnerHero
                eyebrow={page?.eyebrow}
                title={page?.title}
                highlight={page?.highlight}
                description={page?.description}
                breadcrumbs={[{ label: page?.breadcrumb ?? page?.title }]}
            >
                <div className="flex flex-wrap gap-3">
                    <Link href="/start" className="inline-flex min-h-12 items-center rounded-xl bg-emerald-700 px-6 text-sm font-bold text-white shadow-lg shadow-emerald-900/10 transition hover:bg-emerald-800">Create free demo →</Link>
                    <Link href="/pricing" className="inline-flex min-h-12 items-center rounded-xl border border-slate-300 bg-white px-6 text-sm font-bold text-slate-800 transition hover:bg-slate-50">View pricing</Link>
                </div>
            </PublicInnerHero>

            <section className="mx-auto max-w-[1240px] px-5 py-16 sm:px-6 lg:px-8 lg:py-20">
                <div className="grid gap-5 md:grid-cols-3">
                    {cards.map((card, index) => (
                        <PublicCard key={card.title} icon={card.icon ?? ['✦', '↗', '▦'][index % 3]} eyebrow={card.eyebrow} title={card.title}>
                            <p>{card.description}</p>
                        </PublicCard>
                    ))}
                </div>
                {page?.note && (
                    <div className="mt-8 rounded-2xl border border-emerald-100 bg-emerald-50/60 p-5 text-sm leading-7 text-emerald-950">
                        {page.note}
                    </div>
                )}
            </section>

            <PublicCta />
        </PublicSiteLayout>
    );
}
