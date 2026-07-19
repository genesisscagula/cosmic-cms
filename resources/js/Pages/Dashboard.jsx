import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';

export default function Dashboard({ websites }) {
    // I-handle ang form data para sa pagdugang og website
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        domain: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('websites.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Cosmic CMS Hub 🌌
                    </h2>
                    {/* BUTTON PARA MO-DOWNLOAD SA ZIP INSTALLER */}
                    <a
                        href={route('bridge.download')}
                        className="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:border-emerald-900 focus:ring ring-emerald-300 transition ease-in-out duration-150 shadow-sm"
                    >
                        📥 Download Client Bridge ZIP
                    </a>
                </div>
            }
        >
            <Head title="CMS Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    
                    {/* SECTION 1: FORM PARA MAKA-CREATE OG WEBSITE */}
                    <div className="p-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Connect New Live Website</h3>
                        <form onSubmit={handleSubmit} className="flex flex-col md:flex-row gap-4 items-end">
                            <div className="flex-1 w-full">
                                <label className="block text-sm font-medium text-gray-700">Website Name</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    placeholder="e.g., Glass Supply Portfolio"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    required
                                />
                                {errors.name && <div className="text-red-500 text-xs mt-1">{errors.name}</div>}
                            </div>

                            <div className="flex-1 w-full">
                                <label className="block text-sm font-medium text-gray-700">Live Domain URL (Optional)</label>
                                <input
                                    type="url"
                                    value={data.domain}
                                    onChange={e => setData('domain', e.target.value)}
                                    placeholder="https://myclient-glass.com"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                                {errors.domain && <div className="text-red-500 text-xs mt-1">{errors.domain}</div>}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full md:w-auto px-6 py-2 bg-indigo-600 text-white font-semibold text-sm rounded-md shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                            >
                                {processing ? 'Creating...' : '+ Add Site'}
                            </button>
                        </form>
                    </div>

                    {/* SECTION 2: LISTAHAN SA MGA WEBSITES SA USER */}
                    <div className="p-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Your Managed Websites</h3>
                        
                        {websites && websites.length === 0 ? (
                            <p className="text-gray-500 text-sm">No websites connected yet. Create one above!</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-widest">Site Details</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-widest">API Secret Key (Bridge Token)</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-widest">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {websites && websites.map((site) => (
                                            <tr key={site.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-semibold text-gray-900">{site.name}</div>
                                                    <div className="text-xs text-gray-500">{site.domain || 'No live domain linked'}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <code className="text-xs bg-gray-100 text-pink-600 px-2 py-1 rounded border font-mono">
                                                        {site.api_token}
                                                    </code>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    {/* GI-REPLACE ANG ALERT OG TINUOD NGA INERTIA LINK PADULONG SA PAGES ROUTE */}
                                                    <Link 
                                                        href={route('pages.index', site.id)}
                                                        className="text-indigo-600 hover:text-indigo-900 font-semibold"
                                                    >
                                                        Manage Pages &rarr;
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}