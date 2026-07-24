import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Link, useForm } from '@inertiajs/react';

const fieldClass = 'mt-2 block w-full rounded-xl border-slate-700 bg-[#0f1013] px-3.5 py-3 text-sm text-white shadow-none placeholder:text-slate-600 focus:border-emerald-400 focus:ring-emerald-400';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', email: '', password: '', password_confirmation: '' });
    const submit = (event) => {
        event.preventDefault();
        post(route('register'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <GuestLayout title="Create your workspace" subtitle="Start with a website, then shape it in the Cosmic Builder.">
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="name" value="Your name" className="text-sm font-medium text-slate-200" />
                    <TextInput id="name" name="name" value={data.name} className={fieldClass} autoComplete="name" isFocused onChange={(event) => setData('name', event.target.value)} required />
                    <InputError message={errors.name} className="mt-2 text-rose-300" />
                </div>
                <div>
                    <InputLabel htmlFor="email" value="Email address" className="text-sm font-medium text-slate-200" />
                    <TextInput id="email" type="email" name="email" value={data.email} className={fieldClass} autoComplete="username" onChange={(event) => setData('email', event.target.value)} required />
                    <InputError message={errors.email} className="mt-2 text-rose-300" />
                </div>
                <div>
                    <InputLabel htmlFor="password" value="Password" className="text-sm font-medium text-slate-200" />
                    <TextInput id="password" type="password" name="password" value={data.password} className={fieldClass} autoComplete="new-password" onChange={(event) => setData('password', event.target.value)} required />
                    <InputError message={errors.password} className="mt-2 text-rose-300" />
                </div>
                <div>
                    <InputLabel htmlFor="password_confirmation" value="Confirm password" className="text-sm font-medium text-slate-200" />
                    <TextInput id="password_confirmation" type="password" name="password_confirmation" value={data.password_confirmation} className={fieldClass} autoComplete="new-password" onChange={(event) => setData('password_confirmation', event.target.value)} required />
                    <InputError message={errors.password_confirmation} className="mt-2 text-rose-300" />
                </div>
                <button type="submit" disabled={processing} className="mt-2 flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-emerald-400 to-cyan-300 px-4 py-3 text-sm font-semibold text-slate-950 shadow-lg shadow-emerald-400/15 transition hover:from-emerald-300 hover:to-cyan-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-2 focus-visible:ring-offset-[#18181b] disabled:cursor-not-allowed disabled:opacity-60">
                    {processing ? 'Creating workspace…' : 'Create account'}
                </button>
            </form>
            <p className="mt-6 text-center text-sm text-slate-400">Already have an account? <Link href={route('login')} className="font-medium text-emerald-300 hover:text-emerald-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">Sign in</Link></p>
        </GuestLayout>
    );
}
