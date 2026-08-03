import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

export default function Success({ onboarding, status }) {
    return (
        <GuestLayout
            wide
            title="Your workspace is ready"
            subtitle={`Welcome to Cosmic CMS. Your ${onboarding.plan_name} plan is active and your business setup is complete.`}
        >
            <Head title="Welcome to Cosmic CMS" />

            {status && (
                <div className="mb-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">
                    {status}
                </div>
            )}

            <div className="grid items-stretch gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                <section className="rounded-2xl border border-emerald-400/20 bg-emerald-400/[0.06] p-6 sm:p-8">
                    <div className="inline-flex rounded-full border border-emerald-300/20 bg-emerald-300/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-emerald-200">
                        Setup complete
                    </div>

                    <h2 className="mt-5 text-3xl font-semibold tracking-tight text-white">
                        Welcome, your website is ready to edit.
                    </h2>
                    <p className="mt-3 max-w-xl text-sm leading-6 text-slate-300">
                        Your payment was verified, your workspace was created, and your onboarding details were saved to your profile and website settings.
                    </p>

                    <div className="mt-7 grid gap-3 sm:grid-cols-2">
                        <div className="rounded-xl border border-white/10 bg-black/20 p-4">
                            <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Current plan</p>
                            <p className="mt-2 text-lg font-semibold text-white">{onboarding.plan_name}</p>
                        </div>
                        <div className="rounded-xl border border-white/10 bg-black/20 p-4">
                            <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Website</p>
                            <p className="mt-2 truncate text-lg font-semibold text-white">{onboarding.website_name}</p>
                        </div>
                    </div>

                    {onboarding.trial_transferred && (
                        <div className="mt-4 rounded-xl border border-cyan-300/15 bg-cyan-300/[0.06] px-4 py-3 text-sm text-cyan-100">
                            Your trial landing page was safely transferred into this workspace as your Home page.
                        </div>
                    )}

                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Link
                            href={route('dashboard')}
                            className="inline-flex items-center justify-center rounded-xl bg-emerald-400 px-5 py-3.5 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300"
                        >
                            Open Dashboard
                        </Link>
                        {onboarding.website_id && (
                            <Link
                                href={route('pages.index', onboarding.website_id)}
                                className="inline-flex items-center justify-center rounded-xl border border-white/10 px-5 py-3.5 text-sm font-semibold text-slate-200 transition hover:border-white/20 hover:bg-white/5"
                            >
                                Manage Website
                            </Link>
                        )}
                    </div>
                </section>

                <aside className="rounded-2xl border border-white/10 bg-[#111318] p-6">
                    <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">What happens next</p>
                    <div className="mt-5 space-y-4 text-sm leading-6 text-slate-300">
                        <div className="rounded-xl border border-white/10 bg-white/[0.025] p-4">
                            <p className="font-semibold text-white">1. Review your Home page</p>
                            <p className="mt-1 text-slate-400">Open the website editor and fine-tune your content, images, and sections.</p>
                        </div>
                        <div className="rounded-xl border border-white/10 bg-white/[0.025] p-4">
                            <p className="font-semibold text-white">2. Complete your business details</p>
                            <p className="mt-1 text-slate-400">Your onboarding information is already available in Profile and Website Settings.</p>
                        </div>
                        <div className="rounded-xl border border-white/10 bg-white/[0.025] p-4">
                            <p className="font-semibold text-white">3. Publish when ready</p>
                            <p className="mt-1 text-slate-400">Connect your preferred domain and publish your website from the dashboard.</p>
                        </div>
                    </div>
                </aside>
            </div>
        </GuestLayout>
    );
}
