import { Head, Link } from '@inertiajs/react';

export default function Welcome() {

    return (

        <>

            <Head title="Cosmic CMS | AI Website Builder" />

            <div className="relative min-h-screen overflow-hidden bg-white text-slate-900">

                {/* Background */}

                <div className="absolute inset-0 -z-10">

                    <div className="absolute top-[-180px] left-1/2 -translate-x-1/2 h-[520px] w-[900px] rounded-full bg-emerald-400/15 blur-3xl"></div>

                    <div className="absolute right-[-150px] top-40 h-[400px] w-[400px] rounded-full bg-cyan-400/10 blur-3xl"></div>

                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.08),transparent_55%)]"></div>

                </div>

                {/* =========================
                    Header
                ========================== */}

                <header className="sticky top-0 z-50 border-b border-slate-200/70 bg-white/80 backdrop-blur-xl">

                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-6 lg:px-8">

                        {/* Logo */}

                        <Link href="/" className="flex items-center gap-3">

                            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-lg font-black text-white shadow-lg shadow-emerald-500/25">

                                ✦

                            </div>

                            <div>

                                <h1 className="text-xl font-black tracking-tight">

                                    Cosmic <span className="text-emerald-600">CMS</span>

                                </h1>

                                <p className="-mt-1 text-xs font-medium text-slate-500">

                                    AI Website Builder

                                </p>

                            </div>

                        </Link>

                        {/* Navigation */}

                        <nav className="hidden items-center gap-10 lg:flex">

                            <Link
                                href="#features"
                                className="text-sm font-semibold text-slate-600 transition hover:text-emerald-600"
                            >
                                Features
                            </Link>

                            <Link
                                href="#blocks"
                                className="text-sm font-semibold text-slate-600 transition hover:text-emerald-600"
                            >
                                Blocks
                            </Link>

                            <Link
                                href="#pricing"
                                className="text-sm font-semibold text-slate-600 transition hover:text-emerald-600"
                            >
                                Pricing
                            </Link>

                            <Link
                                href="#docs"
                                className="text-sm font-semibold text-slate-600 transition hover:text-emerald-600"
                            >
                                Documentation
                            </Link>

                        </nav>

                        {/* Right */}

                        <div className="flex items-center gap-3">

                            <Link
                                href="/login"
                                className="rounded-xl px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                            >
                                Login
                            </Link>

                            <Link
                                href="/register"
                                className="rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-500/30 transition hover:scale-105"
                            >
                                Start Free
                            </Link>

                        </div>

                    </div>

                </header>

                <main className="relative">

                    <section className="mx-auto max-w-7xl px-6 pt-24 pb-20 lg:px-8">

                        <div className="mx-auto max-w-4xl text-center">

                            {/* Badge */}

                            <div className="mb-8 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-5 py-2">

                                <span className="h-2 w-2 rounded-full bg-emerald-500"></span>

                                <span className="text-sm font-semibold text-emerald-700">
                                    AI Powered Website Builder
                                </span>

                            </div>

                            {/* Heading */}

                            <h1 className="text-5xl font-black tracking-tight text-slate-900 sm:text-6xl lg:text-7xl leading-tight">

                                Build Professional Websites

                                <span className="block bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 bg-clip-text text-transparent">

                                    10× Faster

                                </span>

                            </h1>

                            {/* Description */}

                            <p className="mx-auto mt-8 max-w-2xl text-lg leading-8 text-slate-600">

                                Create stunning websites using reusable blocks,
                                AI-generated content, dynamic themes, and one-click
                                static export.

                                Built for freelancers, agencies, and modern developers.

                            </p>

                            {/* Buttons */}

                            <div className="mt-12 flex flex-col justify-center gap-4 sm:flex-row">

                                <Link
                                    href="/register"
                                    className="rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-8 py-4 text-lg font-bold text-white shadow-xl shadow-emerald-500/30 transition duration-300 hover:-translate-y-1 hover:shadow-2xl"
                                >

                                    Start Building Free

                                </Link>

                                <Link
                                    href="#demo"
                                    className="rounded-xl border border-slate-300 bg-white px-8 py-4 text-lg font-bold text-slate-800 transition duration-300 hover:border-emerald-500 hover:text-emerald-600"
                                >

                                    Live Demo

                                </Link>

                            </div>

                            {/* Trust */}

                            <div className="mt-12 flex items-center justify-center gap-3 text-sm text-slate-500">

                                <div className="flex text-yellow-400">

                                    ★★★★★

                                </div>

                                <span>

                                    Trusted by developers building modern websites.

                                </span>

                            </div>

                        </div>

                    </section>


                    {/* =========================
                        Dashboard Showcase
                    ========================== */}

                    <section className="pb-32 px-6 lg:px-8">

                        <div className="mx-auto max-w-7xl">

                            <div className="relative">

                                {/* Glow */}

                                <div className="absolute -inset-10 rounded-[40px] bg-gradient-to-r from-emerald-400/20 via-cyan-400/20 to-blue-400/20 blur-3xl"></div>

                                {/* Browser */}

                                <div className="relative overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">

                                    {/* Browser Top */}

                                    <div className="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">

                                        <div className="flex items-center gap-2">

                                            <div className="h-3 w-3 rounded-full bg-red-400"></div>

                                            <div className="h-3 w-3 rounded-full bg-yellow-400"></div>

                                            <div className="h-3 w-3 rounded-full bg-green-400"></div>

                                        </div>

                                        <div className="rounded-full bg-white px-6 py-2 text-xs text-slate-500 border border-slate-200">

                                            https://preview.cosmiccms.dev

                                        </div>

                                        <div></div>

                                    </div>

                                    {/* Dashboard */}

                                    <div className="grid lg:grid-cols-[260px_1fr]">

                                        {/* Sidebar */}

                                        <aside className="border-r border-slate-200 bg-slate-50 p-6">

                                            <div className="mb-8 flex items-center gap-3">

                                                <div className="h-10 w-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600"></div>

                                                <div>

                                                    <div className="h-3 w-20 rounded bg-slate-300"></div>

                                                    <div className="mt-2 h-2 w-14 rounded bg-slate-200"></div>

                                                </div>

                                            </div>

                                            <div className="space-y-3">

                                                {[
                                                    "Hero",
                                                    "Features",
                                                    "Services",
                                                    "Pricing",
                                                    "Gallery",
                                                    "Testimonials",
                                                    "Contact"
                                                ].map((item) => (

                                                    <div
                                                        key={item}
                                                        className={`rounded-xl px-4 py-3 ${
                                                            item === "Hero"
                                                                ? "bg-emerald-500 text-white"
                                                                : "bg-white text-slate-600 border border-slate-200"
                                                        }`}
                                                    >

                                                        {item}

                                                    </div>

                                                ))}

                                            </div>

                                        </aside>

                                        {/* Canvas */}

                                        <div className="bg-slate-100 p-8">

                                            <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">

                                                {/* Hero */}

                                                <div className="mb-10">

                                                    <div className="h-3 w-28 rounded bg-emerald-200"></div>

                                                    <div className="mt-4 h-8 w-2/3 rounded bg-slate-300"></div>

                                                    <div className="mt-3 h-4 w-full rounded bg-slate-200"></div>

                                                    <div className="mt-2 h-4 w-5/6 rounded bg-slate-200"></div>

                                                </div>

                                                {/* Cards */}

                                                <div className="grid gap-6 md:grid-cols-3">

                                                    {[1,2,3].map((card)=>(

                                                        <div
                                                            key={card}
                                                            className="rounded-2xl border border-slate-200 p-6"
                                                        >

                                                            <div className="h-12 w-12 rounded-xl bg-emerald-100"></div>

                                                            <div className="mt-5 h-4 w-24 rounded bg-slate-300"></div>

                                                            <div className="mt-3 h-3 w-full rounded bg-slate-200"></div>

                                                            <div className="mt-2 h-3 w-5/6 rounded bg-slate-200"></div>

                                                        </div>

                                                    ))}

                                                </div>

                                                {/* Bottom */}

                                                <div className="mt-10 rounded-2xl border border-dashed border-emerald-300 bg-emerald-50 p-8 text-center">

                                                    <p className="text-lg font-bold text-emerald-700">

                                                        Drag • Drop • AI Generate • Publish

                                                    </p>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </section>


                    {/* =========================
                        Trusted By
                    ========================== */}

                    <section className="py-24 border-y border-slate-200 bg-slate-50/70">

                        <div className="mx-auto max-w-7xl px-6 lg:px-8">

                            {/* Heading */}

                            <div className="text-center">

                                <p className="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">

                                    Trusted By Developers & Agencies

                                </p>

                            </div>

                            {/* Logo Cloud */}

                            <div className="mt-12 grid grid-cols-2 gap-8 opacity-70 md:grid-cols-3 lg:grid-cols-6">

                                {[
                                    "LARAVEL",
                                    "REACT",
                                    "TAILWIND",
                                    "PHP",
                                    "WORDPRESS",
                                    "AZURE"
                                ].map((logo) => (

                                    <div
                                        key={logo}
                                        className="flex h-16 items-center justify-center rounded-xl border border-slate-200 bg-white font-bold tracking-widest text-slate-400 transition hover:-translate-y-1 hover:border-emerald-300 hover:text-emerald-600"
                                    >

                                        {logo}

                                    </div>

                                ))}

                            </div>

                            {/* Stats */}

                            <div className="mt-20 grid gap-6 md:grid-cols-4">

                                {[
                                    {
                                        number: "100+",
                                        title: "Reusable Blocks"
                                    },
                                    {
                                        number: "1 Click",
                                        title: "Static Export"
                                    },
                                    {
                                        number: "AI",
                                        title: "Content Generator"
                                    },
                                    {
                                        number: "∞",
                                        title: "Unlimited Websites"
                                    }
                                ].map((item) => (

                                    <div
                                        key={item.title}
                                        className="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
                                    >

                                        <div className="text-4xl font-black text-emerald-600">

                                            {item.number}

                                        </div>

                                        <div className="mt-3 text-sm font-semibold uppercase tracking-wider text-slate-500">

                                            {item.title}

                                        </div>

                                    </div>

                                ))}

                            </div>

                        </div>

                    </section>


                    {/* =========================
                        Bento Features
                    ========================== */}

                    <section
                        id="features"
                        className="py-32 bg-white"
                    >

                        <div className="mx-auto max-w-7xl px-6 lg:px-8">

                            {/* Section Header */}

                            <div className="mx-auto max-w-3xl text-center">

                                <span className="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">

                                    Why Cosmic CMS

                                </span>

                                <h2 className="mt-6 text-5xl font-black tracking-tight text-slate-900">

                                    Everything You Need
                                    <span className="block text-emerald-600">
                                        To Build Modern Websites
                                    </span>

                                </h2>

                                <p className="mt-6 text-lg leading-8 text-slate-600">

                                    Designed for freelancers, agencies, and developers who
                                    want to build faster without sacrificing flexibility.

                                </p>

                            </div>

                            {/* Bento Grid */}

                            <div className="mt-20 grid gap-6 lg:grid-cols-3">

                                {/* AI */}

                                <div className="rounded-3xl border border-slate-200 bg-gradient-to-br from-emerald-50 to-white p-8 shadow-sm transition duration-300 hover:-translate-y-2 hover:shadow-2xl">

                                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-500 text-3xl text-white">

                                        🤖

                                    </div>

                                    <h3 className="mt-8 text-2xl font-black">

                                        AI Content

                                    </h3>

                                    <p className="mt-4 leading-7 text-slate-600">

                                        Generate headlines, descriptions,
                                        testimonials and marketing copy in seconds.

                                    </p>

                                </div>

                                {/* Themes */}

                                <div className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm transition duration-300 hover:-translate-y-2 hover:shadow-2xl">

                                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-cyan-500 text-3xl text-white">

                                        🎨

                                    </div>

                                    <h3 className="mt-8 text-2xl font-black">

                                        Dynamic Themes

                                    </h3>

                                    <p className="mt-4 leading-7 text-slate-600">

                                        Switch colors, branding and styling with a single click.

                                    </p>

                                </div>

                                {/* Blocks */}

                                <div className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm transition duration-300 hover:-translate-y-2 hover:shadow-2xl">

                                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-500 text-3xl text-white">

                                        🧩

                                    </div>

                                    <h3 className="mt-8 text-2xl font-black">

                                        Reusable Blocks

                                    </h3>

                                    <p className="mt-4 leading-7 text-slate-600">

                                        Build pages using production-ready components that can
                                        be reused across unlimited projects.

                                    </p>

                                </div>

                                {/* Export */}

                                <div className="lg:col-span-2 rounded-3xl border border-slate-200 bg-slate-900 p-10 text-white shadow-xl">

                                    <span className="rounded-full bg-white/10 px-4 py-2 text-sm font-semibold">

                                        One Click Deploy

                                    </span>

                                    <h3 className="mt-8 text-4xl font-black">

                                        Static HTML Export

                                    </h3>

                                    <p className="mt-6 max-w-2xl text-slate-300 leading-8">

                                        Export your entire website as optimized static HTML files
                                        ready to deploy anywhere.

                                        No runtime.

                                        No database.

                                        Just blazing-fast websites.

                                    </p>

                                    <div className="mt-10 flex gap-4">

                                        <div className="rounded-xl bg-white/10 px-5 py-3">

                                            HTML

                                        </div>

                                        <div className="rounded-xl bg-white/10 px-5 py-3">

                                            CSS

                                        </div>

                                        <div className="rounded-xl bg-white/10 px-5 py-3">

                                            Assets

                                        </div>

                                    </div>

                                </div>

                                {/* Performance */}

                                <div className="rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-8 shadow-sm transition duration-300 hover:-translate-y-2 hover:shadow-2xl">

                                    <div className="text-5xl font-black text-emerald-600">

                                        ⚡

                                    </div>

                                    <h3 className="mt-6 text-2xl font-black">

                                        Lightning Fast

                                    </h3>

                                    <p className="mt-4 leading-7 text-slate-600">

                                        Static websites load instantly,
                                        improve SEO,
                                        and provide an exceptional user experience.

                                    </p>

                                </div>

                            </div>

                        </div>

                    </section>

                </main>

                {/* =========================
                    Footer
                ========================== */}

                <footer className="border-t border-slate-200 bg-slate-950 text-slate-300">

                    <div className="mx-auto max-w-7xl px-6 py-20 lg:px-8">

                        <div className="grid gap-16 lg:grid-cols-[2fr_1fr_1fr_1fr_1fr]">

                            {/* Brand */}

                            <div>

                                <Link href="/" className="flex items-center gap-3">

                                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-xl font-black text-white">

                                        ✦

                                    </div>

                                    <div>

                                        <h3 className="text-2xl font-black text-white">

                                            Cosmic <span className="text-emerald-400">CMS</span>

                                        </h3>

                                        <p className="text-sm text-slate-400">

                                            AI Website Builder

                                        </p>

                                    </div>

                                </Link>

                                <p className="mt-6 max-w-sm leading-7 text-slate-400">

                                    Build modern websites using reusable blocks,
                                    AI-generated content,
                                    dynamic themes,
                                    and one-click static export.

                                </p>

                                <div className="mt-8 flex gap-3">

                                    {["G", "X", "D", "Y"].map((item) => (

                                        <a
                                            key={item}
                                            href="#"
                                            className="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-800 bg-slate-900 transition hover:border-emerald-500 hover:bg-emerald-500 hover:text-white"
                                        >

                                            {item}

                                        </a>

                                    ))}

                                </div>

                            </div>

                            {/* Product */}

                            <div>

                                <h4 className="font-bold text-white">

                                    Product

                                </h4>

                                <ul className="mt-6 space-y-4">

                                    <li><Link href="#" className="hover:text-white">Features</Link></li>

                                    <li><Link href="#" className="hover:text-white">Blocks</Link></li>

                                    <li><Link href="#" className="hover:text-white">Themes</Link></li>

                                    <li><Link href="#" className="hover:text-white">Pricing</Link></li>

                                </ul>

                            </div>

                            {/* Resources */}

                            <div>

                                <h4 className="font-bold text-white">

                                    Resources

                                </h4>

                                <ul className="mt-6 space-y-4">

                                    <li><Link href="#" className="hover:text-white">Documentation</Link></li>

                                    <li><Link href="#" className="hover:text-white">API</Link></li>

                                    <li><Link href="#" className="hover:text-white">Blog</Link></li>

                                    <li><Link href="#" className="hover:text-white">Changelog</Link></li>

                                </ul>

                            </div>

                            {/* Company */}

                            <div>

                                <h4 className="font-bold text-white">

                                    Company

                                </h4>

                                <ul className="mt-6 space-y-4">

                                    <li><Link href="#" className="hover:text-white">About</Link></li>

                                    <li><Link href="#" className="hover:text-white">Contact</Link></li>

                                    <li><Link href="#" className="hover:text-white">Careers</Link></li>

                                    <li><Link href="#" className="hover:text-white">Support</Link></li>

                                </ul>

                            </div>

                            {/* Legal */}

                            <div>

                                <h4 className="font-bold text-white">

                                    Legal

                                </h4>

                                <ul className="mt-6 space-y-4">

                                    <li><Link href="#" className="hover:text-white">Privacy Policy</Link></li>

                                    <li><Link href="#" className="hover:text-white">Terms of Service</Link></li>

                                    <li><Link href="#" className="hover:text-white">License</Link></li>

                                    <li><Link href="#" className="hover:text-white">Cookies</Link></li>

                                </ul>

                            </div>

                        </div>

                        {/* Bottom */}

                        <div className="mt-20 flex flex-col items-center justify-between gap-6 border-t border-slate-800 pt-8 text-sm text-slate-500 md:flex-row">

                            <p>

                                © 2026 Cosmic CMS. All rights reserved.

                            </p>

                            <div className="flex items-center gap-6">

                                <span>

                                    Built with Laravel + React + Tailwind CSS

                                </span>

                            </div>

                        </div>

                    </div>

                </footer>

            </div>

        </>

    );

}