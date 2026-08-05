import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function AcceptInvitation({ invitation }) {
    const user = usePage().props.auth?.user;
    const form = useForm({
        name: invitation.name || '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(route('workspace-invitations.accept', invitation.token));
    };

    const wrongAccount = user && user.email?.toLowerCase() !== invitation.email.toLowerCase();

    return (
        <div className="min-h-screen bg-[#09090b] px-5 py-12 text-slate-100">
            <Head title="Accept client invitation" />
            <main className="mx-auto max-w-xl">
                <div className="mb-8 flex items-center gap-3">
                    <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 font-black text-slate-950">C</span>
                    <div><p className="font-semibold text-white">CosmicReact</p><p className="text-xs text-slate-500">Secure client access</p></div>
                </div>

                <section className="rounded-3xl border border-white/10 bg-white/[0.04] p-6 shadow-2xl shadow-black/30 sm:p-8">
                    <p className="text-sm font-medium text-violet-300">{invitation.workspace}</p>
                    <h1 className="mt-2 text-3xl font-semibold text-white">You’ve been invited</h1>
                    <p className="mt-3 text-sm leading-6 text-slate-400">
                        Accept access to {invitation.website_count} assigned website{invitation.website_count === 1 ? '' : 's'} using <strong className="text-slate-200">{invitation.email}</strong>.
                    </p>

                    {wrongAccount ? (
                        <div className="mt-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 p-4 text-sm text-rose-200">
                            You are signed in as {user.email}. Log out, then reopen this invitation using {invitation.email}.
                        </div>
                    ) : (
                        <form onSubmit={submit} className="mt-6 space-y-4">
                            {!user && !invitation.existing_user && (
                                <>
                                    <label className="block text-sm text-slate-300">Your name<input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-violet-400" required /></label>
                                    <label className="block text-sm text-slate-300">Create password<input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} className="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-violet-400" required /></label>
                                    <label className="block text-sm text-slate-300">Confirm password<input type="password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} className="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-violet-400" required /></label>
                                </>
                            )}

                            {invitation.existing_user && !user && (
                                <div className="rounded-2xl border border-amber-400/20 bg-amber-400/10 p-4 text-sm text-amber-100">
                                    An account already exists for this email. <Link href={route('login')} className="font-semibold underline">Sign in</Link>, then reopen this invitation link.
                                </div>
                            )}

                            {Object.values(form.errors).length > 0 && <p className="text-sm text-rose-300">{Object.values(form.errors)[0]}</p>}
                            {(!invitation.existing_user || user) && <button disabled={form.processing} className="w-full rounded-xl bg-white px-5 py-3 text-sm font-semibold text-slate-950 disabled:opacity-50">{form.processing ? 'Accepting…' : 'Accept invitation'}</button>}
                        </form>
                    )}
                </section>
            </main>
        </div>
    );
}
