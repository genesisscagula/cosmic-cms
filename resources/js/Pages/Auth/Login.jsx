import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Link, useForm } from '@inertiajs/react';

const fieldClass = 'mt-2 block w-full rounded-xl border border-slate-400 bg-[#0f1013] px-3.5 py-3 text-sm text-white shadow-sm placeholder:text-slate-600 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/25';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', password: '', remember: false });
    const submit = (event) => {
        event.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout title="Welcome back" subtitle="Sign in to continue building your websites.">
            {status && <div className="mb-5 rounded-xl border border-emerald-400/25 bg-emerald-400/10 px-3.5 py-3 text-sm text-emerald-200">{status}</div>}
            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="Email address" className="text-sm font-medium text-slate-200" />
                    <TextInput id="email" type="email" name="email" value={data.email} className={fieldClass} autoComplete="username" isFocused onChange={(event) => setData('email', event.target.value)} />
                    <InputError message={errors.email} className="mt-2 text-rose-300" />
                </div>
                <div>
                    <div className="flex items-center justify-between gap-4">
                        <InputLabel htmlFor="password" value="Password" className="text-sm font-medium text-slate-200" />
                        {canResetPassword && <Link href={route('password.request')} className="text-xs font-medium text-emerald-300 hover:text-emerald-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">Forgot password?</Link>}
                    </div>
                    <TextInput id="password" type="password" name="password" value={data.password} className={fieldClass} autoComplete="current-password" onChange={(event) => setData('password', event.target.value)} />
                    <InputError message={errors.password} className="mt-2 text-rose-300" />
                </div>
                <label className="flex w-fit cursor-pointer items-center gap-2.5 text-sm text-slate-400">
                    <Checkbox name="remember" checked={data.remember} onChange={(event) => setData('remember', event.target.checked)} className="rounded border-slate-600 bg-[#0f1013] text-emerald-500 focus:ring-emerald-400" />
                    Remember me
                </label>
                <button type="submit" disabled={processing} className="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-emerald-400 to-cyan-300 px-4 py-3 text-sm font-semibold text-slate-950 shadow-lg shadow-emerald-400/15 transition hover:from-emerald-300 hover:to-cyan-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-2 focus-visible:ring-offset-[#18181b] disabled:cursor-not-allowed disabled:opacity-60">
                    {processing ? 'Signing in…' : 'Sign in'}
                </button>
            </form>
            <p className="mt-6 text-center text-sm text-slate-400">New to Cosmic CMS? <Link href={route('register')} className="font-medium text-emerald-300 hover:text-emerald-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">Create an account</Link></p>
        </GuestLayout>
    );
}
