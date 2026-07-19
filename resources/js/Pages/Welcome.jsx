import { Head, Link } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Cosmic CMS | Preview" />
            <div className="bg-slate-50 text-slate-900 min-h-screen flex flex-col font-sans">
                <nav className="w-full px-8 py-6 flex justify-between items-center bg-white border-b border-slate-200">
                    <div className="text-2xl font-black tracking-tighter text-emerald-600">COSMIC<span className="text-slate-900">CMS</span></div>
                    <div className="space-x-6">
                        <Link href="/login" className="text-sm font-bold hover:text-emerald-600 transition">Log in</Link>
                        <Link href="/register" className="bg-emerald-600 px-6 py-2 rounded-full text-sm font-bold text-white hover:bg-emerald-500 transition">Register</Link>
                    </div>
                </nav>

                <main className="flex-grow flex flex-col items-center justify-center py-20 text-center">
                    {/* GI-CHANGE: font-black (900) ug font-sans (Inter) */}
                    <h1 className="text-6xl font-extrabold tracking-tighter mb-6 text-slate-900 leading-[1.1]" 
                        style={{ fontFamily: 'ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"' }}>
                        Build Sites <span className="text-emerald-500">Faster.</span>
                    </h1>
                    
                    <p className="text-slate-500 text-lg max-w-lg mb-8 font-medium">Ang pinaka-simple ug kusog nga Headless CMS para sa imong sunod nga project. Dili na maglisod, sopsop na lang!</p>
                    <Link href="/register" className="bg-slate-900 text-white px-10 py-4 rounded-full font-bold text-lg hover:bg-slate-800 transition">Get Started Now</Link>
                </main>

                <footer className="w-full bg-white border-t border-slate-200 py-12 px-8">
                    <div className="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-5 gap-8">
                        <div className="col-span-1 space-y-2">
                            <div className="w-32 h-16 bg-black flex items-center justify-center text-white font-bold text-lg">Logo</div>
                            <p className="text-sm italic text-slate-500">It's just logical.</p>
                        </div>
                        {[
                            { title: 'Services', links: ['Webinars', 'Hybrid Events'] },
                            { title: 'Solutions', links: ['Financial', 'Government'] },
                            { title: 'Resources', links: ['Blog', 'Case Studies'] },
                            { title: 'About', links: ['Contact', 'Our Team'] }
                        ].map((section) => (
                            <div key={section.title}>
                                <h4 className="font-bold text-sm mb-4 text-slate-900">{section.title}</h4>
                                <ul className="space-y-2 text-sm text-slate-600 font-medium">
                                    {section.links.map(link => <li key={link}><Link href="#" className="hover:text-black">{link}</Link></li>)}
                                </ul>
                            </div>
                        ))}
                    </div>
                    <div className="max-w-7xl mx-auto mt-12 pt-8 border-t border-slate-200 flex flex-col md:flex-row justify-between text-xs text-slate-400 font-medium">
                        <p>&copy; 2026 CMS. All rights reserved.</p>
                        <div className="space-x-4 mt-4 md:mt-0">
                            <Link href="#" className="hover:text-black">Terms & Conditions</Link>
                            <Link href="#" className="hover:text-black">Privacy Policy</Link>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}