export default function Home() {
    return (
        <section className="space-y-8">
            <div>
                <p className="text-sm font-medium text-violet-300">Cosmic workspace</p>
                <h1 className="mt-2 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Build what comes next.</h1>
                <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-400">Start with an idea, shape it with reusable blocks, and publish when it is ready.</p>
            </div>
            <div className="grid gap-4 md:grid-cols-3">
                {["Create a website", "Generate with AI", "Explore templates"].map((label) => (
                    <button key={label} type="button" className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 text-left transition hover:border-violet-400/40 hover:bg-white/[0.06]">
                        <span className="text-sm font-medium text-white">{label}</span>
                        <span className="mt-2 block text-sm text-slate-500">Workspace action placeholder</span>
                    </button>
                ))}
            </div>
        </section>
    );
}
