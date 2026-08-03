import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;
    const planName = user.plan_key ? `${user.plan_key.charAt(0).toUpperCase()}${user.plan_key.slice(1)}` : 'No active plan';
    const planStatus = user.plan_status ? user.plan_status.replaceAll('_', ' ') : 'Not active';

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Profile
                </h2>
            }
        >
            <Head title="Profile" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="rounded-lg bg-white p-5 shadow">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Current plan</p>
                            <p className="mt-2 text-lg font-semibold text-gray-900">{planName}</p>
                        </div>
                        <div className="rounded-lg bg-white p-5 shadow">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Subscription status</p>
                            <p className="mt-2 text-lg font-semibold capitalize text-gray-900">{planStatus}</p>
                        </div>
                        <div className="rounded-lg bg-white p-5 shadow">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Business location</p>
                            <p className="mt-2 text-lg font-semibold text-gray-900">{user.location || 'Not set'}</p>
                        </div>
                    </div>

                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
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
