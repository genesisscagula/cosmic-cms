import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    avatarUrl,
    className = '',
}) {
    const user = usePage().props.auth.user;
    const [previewUrl, setPreviewUrl] = useState(avatarUrl);

    const { data, setData, post, errors, processing, recentlySuccessful } = useForm({
        _method: 'patch',
        name: user.name,
        business_name: user.business_name || '',
        email: user.email,
        location: user.location || '',
        phone: user.phone || '',
        industry: user.industry || '',
        timezone: user.timezone || 'Asia/Manila',
        locale: user.locale || 'en',
        avatar: null,
        remove_avatar: false,
    });

    useEffect(() => {
        return () => {
            if (previewUrl && previewUrl !== avatarUrl) URL.revokeObjectURL(previewUrl);
        };
    }, [previewUrl, avatarUrl]);

    const chooseAvatar = (file) => {
        if (previewUrl && previewUrl !== avatarUrl) URL.revokeObjectURL(previewUrl);
        setData('avatar', file || null);
        setData('remove_avatar', false);
        setPreviewUrl(file ? URL.createObjectURL(file) : avatarUrl);
    };

    const removeAvatar = () => {
        if (previewUrl && previewUrl !== avatarUrl) URL.revokeObjectURL(previewUrl);
        setData('avatar', null);
        setData('remove_avatar', true);
        setPreviewUrl(null);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('profile.update'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">Profile Information</h2>
                <p className="mt-1 text-sm text-gray-600">
                    Update your account identity, avatar, contact details, timezone, and language.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel value="Avatar" />
                    <div className="mt-2 flex flex-wrap items-center gap-4">
                        {previewUrl ? (
                            <img src={previewUrl} alt="Avatar preview" className="h-20 w-20 rounded-full object-cover" />
                        ) : (
                            <div className="flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 text-xl font-semibold text-gray-500">
                                {user.name?.charAt(0)?.toUpperCase() || 'U'}
                            </div>
                        )}
                        <div className="space-y-2">
                            <input
                                id="avatar"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(event) => chooseAvatar(event.target.files?.[0])}
                                className="block text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                            <p className="text-xs text-gray-500">JPG, PNG, or WebP. Maximum 2 MB.</p>
                            {(avatarUrl || previewUrl) && (
                                <button type="button" onClick={removeAvatar} className="text-sm font-medium text-red-600 hover:text-red-700">
                                    Remove avatar
                                </button>
                            )}
                        </div>
                    </div>
                    <InputError className="mt-2" message={errors.avatar} />
                </div>

                <div className="grid gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="name" value="Name" />
                        <TextInput id="name" className="mt-1 block w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required isFocused autoComplete="name" />
                        <InputError className="mt-2" message={errors.name} />
                    </div>
                    <div>
                        <InputLabel htmlFor="email" value="Email" />
                        <TextInput id="email" type="email" className="mt-1 block w-full" value={data.email} onChange={(e) => setData('email', e.target.value)} required autoComplete="username" />
                        <InputError className="mt-2" message={errors.email} />
                    </div>
                    <div>
                        <InputLabel htmlFor="business_name" value="Business Name" />
                        <TextInput id="business_name" className="mt-1 block w-full" value={data.business_name} onChange={(e) => setData('business_name', e.target.value)} />
                        <InputError className="mt-2" message={errors.business_name} />
                    </div>
                    <div>
                        <InputLabel htmlFor="phone" value="Phone" />
                        <TextInput id="phone" className="mt-1 block w-full" value={data.phone} onChange={(e) => setData('phone', e.target.value)} autoComplete="tel" />
                        <InputError className="mt-2" message={errors.phone} />
                    </div>
                    <div>
                        <InputLabel htmlFor="industry" value="Industry" />
                        <TextInput id="industry" className="mt-1 block w-full" value={data.industry} onChange={(e) => setData('industry', e.target.value)} />
                        <InputError className="mt-2" message={errors.industry} />
                    </div>
                    <div>
                        <InputLabel htmlFor="location" value="Location" />
                        <TextInput id="location" className="mt-1 block w-full" value={data.location} onChange={(e) => setData('location', e.target.value)} placeholder="e.g. Ormoc City, Philippines" autoComplete="address-level2" />
                        <InputError className="mt-2" message={errors.location} />
                    </div>
                    <div>
                        <InputLabel htmlFor="timezone" value="Timezone" />
                        <select id="timezone" className="mt-1 block w-full rounded-md border-gray-300 shadow-sm" value={data.timezone} onChange={(e) => setData('timezone', e.target.value)}>
                            <option value="Asia/Manila">Asia/Manila</option>
                            <option value="Australia/Sydney">Australia/Sydney</option>
                            <option value="Pacific/Auckland">Pacific/Auckland</option>
                            <option value="America/New_York">America/New York</option>
                            <option value="Europe/London">Europe/London</option>
                            <option value="UTC">UTC</option>
                        </select>
                        <InputError className="mt-2" message={errors.timezone} />
                    </div>
                    <div>
                        <InputLabel htmlFor="locale" value="Language" />
                        <select id="locale" className="mt-1 block w-full rounded-md border-gray-300 shadow-sm" value={data.locale} onChange={(e) => setData('locale', e.target.value)}>
                            <option value="en">English</option>
                            <option value="en-PH">English (Philippines)</option>
                        </select>
                        <InputError className="mt-2" message={errors.locale} />
                    </div>
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800">
                            Your email address is unverified.{' '}
                            <Link href={route('verification.send')} method="post" as="button" className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Click here to re-send the verification email.
                            </Link>
                        </p>
                        {status === 'verification-link-sent' && <div className="mt-2 text-sm font-medium text-green-600">A new verification link has been sent to your email address.</div>}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Save Profile</PrimaryButton>
                    <Transition show={recentlySuccessful} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                        <p className="text-sm text-gray-600">Saved.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
