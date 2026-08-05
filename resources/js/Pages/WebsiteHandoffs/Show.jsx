import { Head, router, usePage } from '@inertiajs/react';

export default function Show({ handoff }) {
    const { auth } = usePage().props;
    const accept = () => router.post(route('website-handoffs.accept', handoff.token));
    const expired = handoff.expires_at && new Date(handoff.expires_at) < new Date();

    return (
        <div className="min-h-screen bg-[#09090b] px-5 py-12 text-white">
            <Head title="Website handoff" />
            <div className="mx-auto max-w-2xl rounded-3xl border border-white/10 bg-white/[0.04] p-7 shadow-2xl">
                <p className="text-sm font-medium text-violet-300">Cosmic CMS · Website Handoff</p>
                <h1 className="mt-3 text-3xl font-semibold">{handoff.website?.name || 'Website'}</h1>
                <p className="mt-3 text-slate-400">
                    {handoff.from_user?.name || handoff.from_user?.email} wants to transfer this website to {handoff.recipient_email}.
                </p>

                <div className="mt-6 grid gap-3 rounded-2xl border border-white/10 bg-black/20 p-5 text-sm sm:grid-cols-2">
                    <div><p className="text-slate-500">Status</p><p className="mt-1 capitalize text-white">{expired && handoff.status === 'pending' ? 'Expired' : handoff.status}</p></div>
                    <div><p className="text-slate-500">Industry</p><p className="mt-1 text-white">{handoff.website?.industry || 'Not set'}</p></div>
                    <div><p className="text-slate-500">Recipient</p><p className="mt-1 break-all text-white">{handoff.recipient_email}</p></div>
                    <div><p className="text-slate-500">Expires</p><p className="mt-1 text-white">{handoff.expires_at ? new Date(handoff.expires_at).toLocaleString() : '—'}</p></div>
                </div>

                <div className="mt-6 rounded-2xl border border-amber-400/20 bg-amber-400/10 p-4 text-sm text-amber-100">
                    Accepting moves the website into your workspace. Existing live-site deployment credentials and domain connection are cleared for security.
                </div>

                {!auth?.user && <p className="mt-6 text-sm text-slate-400">Sign in using the invited email address to accept this handoff.</p>}
                {auth?.user && auth.user.email !== handoff.recipient_email && <p className="mt-6 text-sm text-rose-300">You are signed in with a different email address.</p>}

                <button
                    type="button"
                    onClick={accept}
                    disabled={!handoff.can_accept}
                    className="mt-6 w-full rounded-xl bg-violet-600 px-5 py-3 font-semibold text-white transition hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Accept website ownership
                </button>
            </div>
        </div>
    );
}
