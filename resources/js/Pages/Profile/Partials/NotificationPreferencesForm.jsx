import { useForm } from '@inertiajs/react';

const labels = {
    billing: ['Billing and receipts', 'Payments, renewals, failed charges, and receipts.'],
    team: ['Team activity', 'Invitations, role changes, and member access updates.'],
    client: ['Client activity', 'Client invitations and preview access events.'],
    handoff: ['Website handoffs', 'Transfer requests, acceptance, cancellation, and expiry.'],
    security: ['Security alerts', 'Important account and access alerts. This cannot be disabled.'],
    product_updates: ['Product updates', 'Occasional Cosmic CMS feature and launch announcements.'],
};

export default function NotificationPreferencesForm({ preferences }) {
    const { data, setData, patch, processing, recentlySuccessful } = useForm({ preferences });

    const submit = (event) => {
        event.preventDefault();
        patch(route('profile.notifications.update'), { preserveScroll: true });
    };

    return (
        <section>
            <header>
                <h2 className="text-lg font-medium text-gray-900">Email notifications</h2>
                <p className="mt-1 text-sm text-gray-600">Choose which account events Cosmic CMS sends to your email.</p>
            </header>
            <form onSubmit={submit} className="mt-6 space-y-4">
                {Object.entries(labels).map(([key, [title, description]]) => (
                    <label key={key} className="flex items-start justify-between gap-5 rounded-xl border border-gray-200 p-4">
                        <span>
                            <span className="block text-sm font-semibold text-gray-900">{title}</span>
                            <span className="mt-1 block text-sm text-gray-500">{description}</span>
                        </span>
                        <input
                            type="checkbox"
                            checked={Boolean(data.preferences?.[key])}
                            disabled={key === 'security'}
                            onChange={(event) => setData('preferences', { ...data.preferences, [key]: event.target.checked })}
                            className="mt-1 h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                    </label>
                ))}
                <div className="flex items-center gap-4">
                    <button disabled={processing} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Save notification settings</button>
                    {recentlySuccessful && <span className="text-sm text-emerald-600">Saved.</span>}
                </div>
            </form>
        </section>
    );
}
