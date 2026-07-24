import { Head, Link } from '@inertiajs/react';

export default function Welcome() {

    return (

        <>

            <Head title="Cosmic CMS | AI Website Builder" />

            <div className="relative min-h-screen overflow-hidden bg-[#09090b] text-slate-100">

                {/* Background */}

                <div className="absolute inset-0 -z-10">

                    <div className="absolute top-[-180px] left-1/2 h-[520px] w-[900px] -translate-x-1/2 rounded-full bg-violet-500/20 blur-3xl"></div>

                    <div className="absolute right-[-150px] top-40 h-[400px] w-[400px] rounded-full bg-cyan-400/15 blur-3xl"></div>

                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.12),transparent_52%)]"></div>

                </div>

                {/* =========================
                    Header
                ========================== */}

                <header className="sticky top-0 z-50 border-b border-white/10 bg-[#09090b]/85 backdrop-blur-xl">

                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-6 lg:px-8">

                        {/* Logo */}

                        <Link href="/" className="flex items-center gap-3">

                            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 text-lg font-black text-white shadow-lg shadow-violet-500/25">

                                ✦

                            </div>

                            <div>

                                <h1 className="text-xl font-black tracking-tight text-white">

                                    Cosmic <span className="text-violet-300">CMS</span>

                                </h1>

                                <p className="-mt-1 text-xs font-medium text-slate-400">

                                    AI Website Builder

                                </p>

                            </div>

                        </Link>

                        {/* Navigation */}

                        <nav className="hidden items-center gap-10 lg:flex">

                            <Link
                                href="#features"
                                className="text-sm font-semibold text-slate-400 transition hover:text-emerald-300"
                            >
                                Features
                            </Link>

                            <Link
                                href="#blocks"
                                className="text-sm font-semibold text-slate-400 transition hover:text-emerald-300"
                            >
                                Blocks
                            </Link>

                            <Link
                                href="#pricing"
                                className="text-sm font-semibold text-slate-400 transition hover:text-emerald-300"
                            >
                                Pricing
                            </Link>

                            <Link
                                href="#docs"
                                className="text-sm font-semibold text-slate-400 transition hover:text-emerald-300"
                            >
                                Documentation
                            </Link>

                        </nav>

                        {/* Right */}

                        <div className="flex items-center gap-3">

                            <Link
                                href="/login"
                                className="rounded-xl px-5 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/5 hover:text-white"
                            >
                                Login
                            </Link>

                            <Link
                                href="/register"
                                className="rounded-xl bg-gradient-to-r from-emerald-400 to-cyan-300 px-6 py-2.5 text-sm font-bold text-slate-950 shadow-lg shadow-emerald-400/20 transition hover:scale-105"
                            >
                                Start Free
                            </Link>

                        </div>

                    </div>

                </header>

                <main className="relative">

                    <section className="mx-auto max-w-7xl px-6 pb-16 pt-20 lg:px-8">

                        <div className="mx-auto max-w-4xl text-center">

                            {/* Badge */}

                            <div className="mt-12 mb-8 inline-flex items-center gap-2 rounded-full border border-emerald-300/25 bg-emerald-400/10 px-5 py-2">

                                <span className="h-2 w-2 rounded-full bg-emerald-500"></span>

                                <span className="text-sm font-semibold text-emerald-200">
                                    AI Powered Website Builder
                                </span>

                            </div>

                            {/* Heading */}

                            <h1 className="text-5xl font-black leading-tight tracking-tight text-white sm:text-6xl lg:text-7xl">

                                Build Professional Websites

                                <span className="block text-emerald-300">

                                    10× Faster

                                </span>

                            </h1>

                            {/* Description */}

                            <p className="mx-auto mt-8 max-w-2xl text-lg leading-8 text-slate-300">

                                Create stunning websites using reusable blocks,
                                AI-generated content, dynamic themes, and one-click
                                static export.

                                Built for freelancers, agencies, and modern developers.

                            </p>

                            {/* Buttons */}

                            <div className="mt-12 flex flex-col justify-center gap-4 sm:flex-row">

                                <Link
                                    href="/register"
                                    className="rounded-xl bg-gradient-to-r from-emerald-400 to-cyan-300 px-8 py-4 text-lg font-bold text-slate-950 shadow-xl shadow-emerald-400/20 transition duration-300 hover:-translate-y-1 hover:shadow-2xl"
                                >

                                    Start Building Free

                                </Link>

                                <Link
                                    href="#demo"
                                    className="rounded-xl border border-white/15 bg-white/5 px-8 py-4 text-lg font-bold text-white transition duration-300 hover:border-cyan-300/50 hover:bg-white/10 hover:text-cyan-200"
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

                    <section className="px-6 pb-24 lg:px-8">

                        <div className="mx-auto max-w-7xl">

                            <div className="relative">

                                {/* Glow */}

                                <div className="absolute -inset-10 rounded-[40px] bg-gradient-to-r from-emerald-400/20 via-cyan-400/20 to-violet-400/20 blur-3xl"></div>

                                {/* Browser */}

                                <div className="relative overflow-hidden rounded-3xl border border-white/10 bg-[#11141b] shadow-2xl shadow-black/50">

                                    {/* Browser Top */}

                                    <div className="flex items-center justify-between border-b border-white/10 bg-[#171a22] px-6 py-4">

                                        <div className="flex items-center gap-2">

                                            <div className="h-3 w-3 rounded-full bg-red-400"></div>

                                            <div className="h-3 w-3 rounded-full bg-yellow-400"></div>

                                            <div className="h-3 w-3 rounded-full bg-green-400"></div>

                                        </div>

                                        <div className="rounded-full border border-white/10 bg-[#0d0f14] px-6 py-2 text-xs text-slate-500">

                                            https://preview.cosmiccms.dev

                                        </div>

                                        <div></div>

                                    </div>

                                    {/* Dashboard */}

                                    <div className="grid lg:grid-cols-[260px_1fr]">

                                        {/* Sidebar */}

                                        <aside className="border-r border-white/10 bg-[#12151d] p-6">

                                            <div className="mb-8 flex items-center gap-3">

                                                <div className="h-10 w-10 rounded-xl bg-gradient-to-br from-violet-500 to-cyan-400"></div>

                                                <div>

                                                    <div className="h-3 w-20 rounded bg-slate-600"></div>

                                                    <div className="mt-2 h-2 w-14 rounded bg-slate-700"></div>

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
                                                                ? "bg-gradient-to-r from-violet-500 to-indigo-500 text-white shadow-lg shadow-violet-500/20"
                                                                : "border border-white/10 bg-white/[0.03] text-slate-400"
                                                        }`}
                                                    >

                                                        {item}

                                                    </div>

                                                ))}

                                            </div>

                                        </aside>

                                        {/* Canvas */}

                                        <div className="bg-[#0d1017] p-8">

                                            <div className="rounded-2xl border border-white/10 bg-[#171b25] p-8 shadow-sm">

                                                {/* Hero */}

                                                <div className="mb-10">

                                                    <div className="h-3 w-28 rounded bg-emerald-300/70"></div>

                                                    <div className="mt-4 h-8 w-2/3 rounded bg-slate-500"></div>

                                                    <div className="mt-3 h-4 w-full rounded bg-slate-700"></div>

                                                    <div className="mt-2 h-4 w-5/6 rounded bg-slate-700"></div>

                                                </div>

                                                {/* Cards */}

                                                <div className="grid gap-6 md:grid-cols-3">

                                                    {[1,2,3].map((card)=>(

                                                        <div
                                                            key={card}
                                                            className="rounded-2xl border border-white/10 bg-[#11141b] p-6"
                                                        >

                                                            <div className="h-12 w-12 rounded-xl bg-emerald-400/15"></div>

                                                            <div className="mt-5 h-4 w-24 rounded bg-slate-600"></div>

                                                            <div className="mt-3 h-3 w-full rounded bg-slate-700"></div>

                                                            <div className="mt-2 h-3 w-5/6 rounded bg-slate-700"></div>

                                                        </div>

                                                    ))}

                                                </div>

                                                {/* Bottom */}

                                                <div className="mt-10 rounded-2xl border border-dashed border-emerald-300/50 bg-emerald-400/[0.06] p-8 text-center">

                                                    <p className="text-lg font-bold text-emerald-200">

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

                    <section className="border-y border-white/10 bg-[#0d0f14] py-20">

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
                                        className="flex h-16 items-center justify-center rounded-xl border border-slate-800 bg-[#14171e] font-bold tracking-widest text-slate-400 transition hover:-translate-y-1 hover:border-emerald-300/40 hover:text-emerald-200"
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
                                        className="rounded-2xl border border-slate-800 bg-gradient-to-b from-[#171b24] to-[#11141a] p-8 text-center shadow-sm transition hover:-translate-y-1 hover:border-violet-300/30 hover:shadow-xl"
                                    >

                                        <div className="text-4xl font-black text-emerald-300">

                                            {item.number}

                                        </div>

                                        <div className="mt-3 text-sm font-semibold uppercase tracking-wider text-slate-400">

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
                        className="bg-[#09090b] py-24"
                    >

                        <div className="mx-auto max-w-7xl px-6 lg:px-8">

                            {/* Section Header */}

                            <div className="mx-auto max-w-3xl text-center">

                                <span className="inline-flex rounded-full border border-emerald-300/25 bg-emerald-400/10 px-4 py-2 text-sm font-semibold text-emerald-200">

                                    Why Cosmic CMS

                                </span>

                                <h2 className="mt-6 text-5xl font-black tracking-tight text-white">

                                    Everything You Need
                                    <span className="block bg-gradient-to-r from-emerald-300 via-cyan-300 to-violet-300 bg-clip-text text-transparent">
                                        To Build Modern Websites
                                    </span>

                                </h2>

                                <p className="mt-6 text-lg leading-8 text-slate-300">

                                    Designed for freelancers, agencies, and developers who
                                    want to build faster without sacrificing flexibility.

                                </p>

                            </div>

                            {/* Bento Grid */}

                            <div className="mt-20 grid gap-6 lg:grid-cols-3">

                                {/* AI */}

                                <div className="rounded-3xl border border-slate-800 bg-[radial-gradient(circle_at_top_left,rgba(52,211,153,0.18),transparent_38%),linear-gradient(145deg,#151c22,#10141a)] p-8 shadow-sm transition duration-300 hover:-translate-y-2 hover:border-emerald-300/40 hover:shadow-2xl hover:shadow-emerald-950/30">

                                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-500 text-3xl text-slate-950 shadow-lg shadow-emerald-400/20">

                                        🤖

                                    </div>

                                    <h3 className="mt-8 text-2xl font-black text-white">

                                        AI Content

                                    </h3>

                                    <p className="mt-4 leading-7 text-slate-300">

                                        Generate headlines, descriptions,
                                        testimonials and marketing copy in seconds.

                                    </p>

                                </div>

                                {/* Themes */}

                                <div className="rounded-3xl border border-slate-800 bg-[radial-gradient(circle_at_top_left,rgba(34,211,238,0.16),transparent_38%),linear-gradient(145deg,#151b25,#10141a)] p-8 shadow-sm transition duration-300 hover:-translate-y-2 hover:border-cyan-300/40 hover:shadow-2xl hover:shadow-cyan-950/30">

                                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-300 to-sky-500 text-3xl text-slate-950 shadow-lg shadow-cyan-400/20">

                                        🎨

                                    </div>

                                    <h3 className="mt-8 text-2xl font-black text-white">

                                        Dynamic Themes

                                    </h3>

                                    <p className="mt-4 leading-7 text-slate-300">

                                        Switch colors, branding and styling with a single click.

                                    </p>

                                </div>

                                {/* Blocks */}

                                <div className="rounded-3xl border border-slate-800 bg-[radial-gradient(circle_at_top_left,rgba(139,92,246,0.18),transparent_38%),linear-gradient(145deg,#19162a,#10141a)] p-8 shadow-sm transition duration-300 hover:-translate-y-2 hover:border-violet-300/40 hover:shadow-2xl hover:shadow-violet-950/30">

                                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-400 to-indigo-500 text-3xl text-white shadow-lg shadow-violet-400/20">

                                        🧩

                                    </div>

                                    <h3 className="mt-8 text-2xl font-black text-white">

                                        Reusable Blocks

                                    </h3>

                                    <p className="mt-4 leading-7 text-slate-300">

                                        Build pages using production-ready components that can
                                        be reused across unlimited projects.

                                    </p>

                                </div>

                                {/* Export */}

                                <div className="lg:col-span-2 rounded-3xl border border-slate-800 bg-[radial-gradient(circle_at_top_right,rgba(34,211,238,0.16),transparent_35%),linear-gradient(145deg,#131c2d,#0d111a)] p-10 text-white shadow-xl shadow-black/30">

                                    <span className="rounded-full border border-cyan-200/15 bg-cyan-300/10 px-4 py-2 text-sm font-semibold text-cyan-100">

                                        One Click Deploy

                                    </span>

                                    <h3 className="mt-8 text-4xl font-black">

                                        Static HTML Export

                                    </h3>

                                    <p className="mt-6 max-w-2xl leading-8 text-slate-300">

                                        Export your entire website as optimized static HTML files
                                        ready to deploy anywhere.

                                        No runtime.

                                        No database.

                                        Just blazing-fast websites.

                                    </p>

                                    <div className="mt-10 flex gap-4">

                                        <div className="rounded-xl border border-white/10 bg-white/10 px-5 py-3">

                                            HTML

                                        </div>

                                        <div className="rounded-xl border border-white/10 bg-white/10 px-5 py-3">

                                            CSS

                                        </div>

                                        <div className="rounded-xl border border-white/10 bg-white/10 px-5 py-3">

                                            Assets

                                        </div>

                                    </div>

                                </div>

                                {/* Performance */}

                                <div className="rounded-3xl border border-slate-800 bg-[radial-gradient(circle_at_top_left,rgba(251,191,36,0.15),transparent_38%),linear-gradient(145deg,#201b16,#10141a)] p-8 shadow-sm transition duration-300 hover:-translate-y-2 hover:border-amber-300/40 hover:shadow-2xl hover:shadow-amber-950/30">

                                    <div className="text-5xl font-black text-amber-300">

                                        ⚡

                                    </div>

                                    <h3 className="mt-6 text-2xl font-black text-white">

                                        Lightning Fast

                                    </h3>

                                    <p className="mt-4 leading-7 text-slate-300">

                                        Static websites load instantly,
                                        improve SEO,
                                        and provide an exceptional user experience.

                                    </p>

                                </div>

                            </div>

                        </div>

                    </section>

                    <section className="border-t border-white/10 px-6 py-20 lg:px-8">

                        <div className="mx-auto max-w-7xl">

                            <div className="relative overflow-hidden rounded-3xl border border-slate-800 bg-[radial-gradient(circle_at_20%_20%,rgba(52,211,153,0.18),transparent_32%),radial-gradient(circle_at_80%_30%,rgba(34,211,238,0.14),transparent_30%),linear-gradient(145deg,#151c24,#0d1118)] px-6 py-14 text-center shadow-2xl shadow-black/30 sm:px-10 sm:py-20">

                                <div className="absolute -bottom-24 left-1/2 h-48 w-[32rem] -translate-x-1/2 rounded-full bg-emerald-400/10 blur-3xl"></div>

                                <div className="relative">

                                    <span className="inline-flex rounded-full border border-emerald-300/25 bg-emerald-400/10 px-4 py-2 text-sm font-semibold text-emerald-200">
                                        Ready when you are
                                    </span>

                                    <h2 className="mx-auto mt-6 max-w-3xl text-4xl font-black tracking-tight text-white sm:text-5xl">
                                        Build your next website
                                        <span className="block text-emerald-300">without the usual chaos.</span>
                                    </h2>

                                    <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                                        Create, customize, generate content, and publish from one focused Cosmic CMS workspace.
                                    </p>

                                    <div className="mt-10 flex flex-col justify-center gap-4 sm:flex-row">

                                        <Link
                                            href="/register"
                                            className="rounded-xl bg-gradient-to-r from-emerald-400 to-cyan-300 px-8 py-4 text-lg font-bold text-slate-950 shadow-xl shadow-emerald-400/20 transition duration-300 hover:-translate-y-1"
                                        >
                                            Start Building Free
                                        </Link>

                                        <Link
                                            href="#features"
                                            className="rounded-xl border border-white/15 bg-white/5 px-8 py-4 text-lg font-bold text-white transition duration-300 hover:border-cyan-300/50 hover:bg-white/10 hover:text-cyan-200"
                                        >
                                            Explore Features
                                        </Link>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </section>

                </main>

                {/* =========================
                    Footer
                ========================== */}

                <footer className="border-t border-white/10 bg-[#07080c] text-slate-300">

                    <div className="mx-auto max-w-7xl px-6 py-20 lg:px-8">

                        <div className="grid gap-16 lg:grid-cols-[2fr_1fr_1fr_1fr_1fr]">

                            {/* Brand */}

                            <div>

                                <Link href="/" className="flex items-center gap-3">

                                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 text-xl font-black text-white shadow-lg shadow-violet-500/20">

                                        ✦

                                    </div>

                                    <div>

                                        <h3 className="text-2xl font-black text-white">

                                            Cosmic <span className="text-violet-300">CMS</span>

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
                                            className="flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/[0.04] transition hover:border-emerald-300/50 hover:bg-emerald-400 hover:text-slate-950"
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

                        <div className="mt-20 flex flex-col items-center justify-between gap-6 border-t border-white/10 pt-8 text-sm text-slate-500 md:flex-row">

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
