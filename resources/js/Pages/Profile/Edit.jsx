import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

const formatDate = (value, timezone = 'Asia/Manila') => {
    if (!value) return 'Not available';

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
};

export default function Edit({ mustVerifyEmail, status, profileSummary }) {
    const user = usePage().props.auth.user;
    const initials = user.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Profile</h2>}
        >
            <Head title="Profile" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <section className="overflow-hidden rounded-2xl bg-white shadow">
                        <div className="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-4">
                                {profileSummary.avatar_url ? (
                                    <img
                                        src={profileSummary.avatar_url}
                                        alt={`${user.name} avatar`}
                                        className="h-20 w-20 rounded-full object-cover ring-4 ring-indigo-50"
                                    />
                                ) : (
                                    <div className="flex h-20 w-20 items-center justify-center rounded-full bg-indigo-100 text-2xl font-bold text-indigo-700 ring-4 ring-indigo-50">
                                        {initials || 'U'}
                                    </div>
                                )}

                                <div>
                                    <p className="text-2xl font-semibold text-gray-900">{user.name}</p>
                                    <p className="mt-1 text-sm text-gray-600">{user.email}</p>
                                    <p className="mt-2 inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                        {user.business_name || 'Personal workspace'}
                                    </p>
                                </div>
                            </div>

                            <div className="grid gap-3 text-sm sm:min-w-[360px] sm:grid-cols-2">
                                <div className="rounded-xl bg-gray-50 p-4">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Member since</p>
                                    <p className="mt-2 font-medium text-gray-900">
                                        {formatDate(profileSummary.member_since, user.timezone)}
                                    </p>
                                </div>
                                <div className="rounded-xl bg-gray-50 p-4">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Last login</p>
                                    <p className="mt-2 font-medium text-gray-900">
                                        {formatDate(profileSummary.last_login_at, user.timezone)}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="rounded-lg bg-white p-5 shadow">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Timezone</p>
                            <p className="mt-2 text-lg font-semibold text-gray-900">{user.timezone || 'Asia/Manila'}</p>
                        </div>
                        <div className="rounded-lg bg-white p-5 shadow">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Language</p>
                            <p className="mt-2 text-lg font-semibold text-gray-900">
                                {user.locale === 'en-PH' ? 'English (Philippines)' : 'English'}
                            </p>
                        </div>
                        <div className="rounded-lg bg-white p-5 shadow">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Profile status</p>
                            <p className="mt-2 text-lg font-semibold text-gray-900">
                                {profileSummary.profile_completed_at ? 'Completed' : 'Needs review'}
                            </p>
                        </div>
                    </div>

                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            avatarUrl={profileSummary.avatar_url}
                            className="max-w-2xl"
                        />
                    </div>

                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <UpdatePasswordForm className="max-w-xl" />
                    </div>

                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <DeleteUserForm className="max-w-xl" />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
