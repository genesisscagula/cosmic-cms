import { Link } from '@inertiajs/react';
import SeoHead from '@/Components/Seo/SeoHead';
import PublicHeader from '@/Components/Public/PublicHeader';

const Check = ({ children }) => <li className="flex gap-3"><span className="mt-0.5 font-bold text-emerald-700">✓</span><span>{children}</span></li>;

export default function SeoLanding({ page }) {
    const relatedPages = [
        ['/ai-website-builder', 'AI Website Builder'],
        ['/ai-website-generator', 'AI Website Generator'],
        ['/modern-website-builder', 'Modern Website Builder'],
        ['/website-builder-for-small-business', 'Small Business Website Builder'],
        ['/no-code-website-builder', 'No-Code Website Builder'],
    ].filter(([path]) => path !== page.path);

    const schema = [
        {
            '@context': 'https://schema.org',
            '@type': 'WebPage',
            name: page.title,
            url: `https://www.cosmiccms.com${page.path}`,
            description: page.description,
        },
        {
            '@context': 'https://schema.org',
            '@type': 'SoftwareApplication',
            name: 'Cosmic CMS',
            applicationCategory: 'BusinessApplication',
            operatingSystem: 'Web',
            url: 'https://www.cosmiccms.com/',
            description: page.description,
        },
        {
            '@context': 'https://schema.org',
            '@type': 'BreadcrumbList',
            itemListElement: [
                { '@type': 'ListItem', position: 1, name: 'Home', item: 'https://www.cosmiccms.com/' },
                { '@type': 'ListItem', position: 2, name: page.h1, item: `https://www.cosmiccms.com${page.path}` },
            ],
        },
        {
            '@context': 'https://schema.org',
            '@type': 'FAQPage',
            mainEntity: page.faqs.map((faq) => ({
                '@type': 'Question',
                name: faq.q,
                acceptedAnswer: { '@type': 'Answer', text: faq.a },
            })),
        },
    ];

    return <>
        <SeoHead title={page.title} description={page.description} path={page.path} schema={schema} />
        <div className="min-h-screen bg-white text-slate-900">
            <PublicHeader />

            <main id="main-content">
                <section className="border-b border-slate-200 bg-gradient-to-b from-emerald-50/70 to-white">
                    <div className="mx-auto max-w-5xl px-5 py-20 text-center sm:px-6 lg:py-28">
                        <p className="text-sm font-bold uppercase tracking-[.18em] text-emerald-700">{page.eyebrow}</p>
                        <h1 className="mx-auto mt-5 max-w-4xl text-4xl font-black tracking-tight text-slate-950 sm:text-6xl">{page.h1}</h1>
                        <p className="mx-auto mt-7 max-w-3xl text-lg leading-8 text-slate-600">{page.intro}</p>
                        <div className="mt-9 flex flex-col justify-center gap-3 sm:flex-row"><Link href="/start" className="rounded-xl bg-emerald-700 px-7 py-3.5 font-bold text-white">Generate a free website concept →</Link><Link href="/pricing" className="rounded-xl border border-slate-300 bg-white px-7 py-3.5 font-bold text-slate-800">View pricing</Link></div>
                    </div>
                </section>

                <section className="cosmic-defer-render mx-auto grid max-w-7xl gap-10 px-5 py-20 sm:px-6 lg:grid-cols-2 lg:px-8">
                    <div><p className="text-sm font-bold uppercase tracking-wider text-emerald-700">Why Cosmic CMS</p><h2 className="mt-3 text-3xl font-extrabold tracking-tight">From idea to an editable website faster</h2><p className="mt-5 text-lg leading-8 text-slate-600">{page.body}</p></div>
                    <ul className="space-y-5 rounded-3xl border border-slate-200 bg-slate-50 p-8 text-base font-semibold text-slate-700 shadow-sm">{page.benefits.map((item) => <Check key={item}>{item}</Check>)}</ul>
                </section>

                <section className="cosmic-defer-render border-y border-slate-200 bg-slate-50"><div className="mx-auto max-w-7xl px-5 py-20 sm:px-6 lg:px-8"><h2 className="text-center text-3xl font-extrabold">How it works</h2><div className="mt-10 grid gap-5 md:grid-cols-3">{['Describe your business and the website you need.','Cosmic generates a structured, responsive starting point.','Edit the content and design, then prepare it for launch.'].map((x,i)=><div key={x} className="rounded-2xl border border-slate-200 bg-white p-7"><span className="text-sm font-bold text-emerald-700">0{i+1}</span><p className="mt-3 font-bold leading-7">{x}</p></div>)}</div></div></section>

                <section className="cosmic-defer-render mx-auto max-w-4xl px-5 py-20 sm:px-6"><h2 className="text-3xl font-extrabold">Frequently asked questions</h2><div className="mt-8 divide-y divide-slate-200 border-y border-slate-200">{page.faqs.map(faq=><article key={faq.q} className="py-6"><h3 className="text-lg font-semibold">{faq.q}</h3><p className="mt-2 leading-7 text-slate-600">{faq.a}</p></article>)}</div></section>

                <section className="cosmic-defer-render border-t border-slate-200 bg-white">
                    <div className="mx-auto max-w-7xl px-5 py-16 sm:px-6 lg:px-8">
                        <h2 className="text-2xl font-extrabold text-slate-950">Explore related website builder guides</h2>
                        <p className="mt-3 max-w-2xl leading-7 text-slate-600">Compare related Cosmic CMS workflows and choose the path that best matches the website you want to create.</p>
                        <div className="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {relatedPages.map(([href, label]) => <Link key={href} href={href} className="rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm font-bold text-slate-800 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800">{label} →</Link>)}
                        </div>
                    </div>
                </section>

                <section className="cosmic-defer-render bg-slate-950"><div className="mx-auto max-w-4xl px-5 py-16 text-center sm:px-6"><h2 className="text-3xl font-extrabold text-white">Start building with Cosmic CMS</h2><p className="mx-auto mt-4 max-w-2xl text-slate-300">Turn a short business description into a website concept you can refine in the Cosmic builder.</p><Link href="/start" className="mt-7 inline-flex rounded-xl bg-emerald-600 px-7 py-3.5 font-bold text-white">Get started</Link></div></section>
            </main>
            <footer className="border-t border-slate-200 px-5 py-8 text-center text-sm text-slate-500">© {new Date().getFullYear()} Cosmic CMS · <Link href="/privacy">Privacy</Link> · <Link href="/terms">Terms</Link></footer>
        </div>
    </>;
}
